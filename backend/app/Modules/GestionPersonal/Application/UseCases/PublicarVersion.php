<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\DatosDePublicacionDTO;
use App\Modules\GestionPersonal\Application\DTO\VersionDTO;
use App\Modules\GestionPersonal\Application\Ports\RelojPort;
use App\Modules\GestionPersonal\Application\Ports\TransaccionPort;
use App\Modules\GestionPersonal\Application\Services\ArmadorDeVersion;
use App\Modules\GestionPersonal\Application\Services\ResolvedorDeBorrador;
use App\Modules\GestionPersonal\Domain\Exceptions\BorradorModificado;
use App\Modules\GestionPersonal\Domain\Exceptions\ClaveDeOperacionRequerida;
use App\Modules\GestionPersonal\Domain\Exceptions\ClaveDeOperacionReutilizada;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\EscrituraDeshabilitada;
use App\Modules\GestionPersonal\Domain\Exceptions\InicioEnElPasado;
use App\Modules\GestionPersonal\Domain\Exceptions\PoliticaNoEncontrada;
use App\Modules\GestionPersonal\Domain\Exceptions\ProgramadaExistente;
use App\Modules\GestionPersonal\Domain\Repositories\PoliticaRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\ClasificadorDeVersiones;
use App\Modules\GestionPersonal\Domain\Services\InicioDeVigencia;
use App\Modules\GestionPersonal\Domain\Services\ReglasDePolitica;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;

/**
 * Publica un borrador (inmediato o programado) en una transacción que bloquea la cabecera del tipo, relee el estado,
 * revalida todo, asigna el número, sella las condiciones y registra la acción. Idempotente por `Idempotency-Key`.
 */
final class PublicarVersion
{
    public function __construct(
        private readonly PoliticaRepositoryInterface $politicas,
        private readonly ResolvedorDeBorrador $borradores,
        private readonly ClasificadorDeVersiones $clasificador,
        private readonly ReglasDePolitica $reglas,
        private readonly InicioDeVigencia $inicios,
        private readonly ArmadorDeVersion $armador,
        private readonly TransaccionPort $transaccion,
        private readonly RelojPort $reloj,
    ) {}

    /**
     * @return array{version: VersionDTO, repetida: bool}
     *
     * @throws EscrituraDeshabilitada
     * @throws ClaveDeOperacionRequerida
     * @throws ClaveDeOperacionReutilizada
     * @throws PoliticaNoEncontrada
     * @throws BorradorModificado
     * @throws ProgramadaExistente
     * @throws InicioEnElPasado
     * @throws DatosInvalidos
     */
    public function ejecutar(ContextoDeTenant $contexto, int $actorId, TipoDePolitica $tipo, int $versionId, DatosDePublicacionDTO $datos): array
    {
        if (! config('hrms.gestion_personal.politicas_habilitado', true)) {
            throw new EscrituraDeshabilitada('La configuración de políticas está deshabilitada temporalmente.');
        }

        $clave = $datos->claveDeOperacion;
        if ($clave === null || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $clave) !== 1) {
            throw new ClaveDeOperacionRequerida;
        }

        $motivo = $this->reglas->texto($datos->motivo);
        $revisor = $this->reglas->texto($datos->revisor);
        $observaciones = $this->reglas->texto($datos->observaciones);
        $hash = hash('sha256', ReglasDePolitica::json([
            'accion' => 'Publicacion', 'tipo' => $tipo->value, 'version_id' => $versionId, 'version' => $datos->version,
            'motivo' => $motivo, 'revisor' => $revisor, 'observaciones' => $observaciones, 'inicio' => $datos->inicio,
        ]));

        return $this->transaccion->enTransaccion(function () use ($contexto, $actorId, $tipo, $versionId, $datos, $clave, $motivo, $revisor, $observaciones, $hash) {
            $ahora = $this->reloj->ahora();
            $this->politicas->asegurarCabeceras($contexto->tenantId);
            $cabecera = $this->politicas->bloquear($contexto->tenantId, $tipo);
            $versiones = $this->politicas->versionesDe($contexto->tenantId, [$cabecera->id])[$cabecera->id] ?? [];

            $previa = $this->politicas->accionPorOperacion($contexto->tenantId, strtolower($clave));
            if ($previa !== null) {
                if ($previa->accion !== 'Publicacion' || $previa->politicaId !== $cabecera->id || $previa->versionId !== $versionId || $previa->operacionHash !== $hash) {
                    throw new ClaveDeOperacionReutilizada;
                }
                $original = $this->politicas->version($contexto->tenantId, $cabecera->id, $versionId) ?? throw new PoliticaNoEncontrada;

                return ['version' => $this->armador->dtoDe($original, $versiones, $ahora, $contexto->zonaHoraria), 'repetida' => true];
            }

            $borrador = $this->borradores->obtener($contexto->tenantId, $cabecera->id, $versionId, $datos->version);

            if ($this->clasificador->programada($versiones, $ahora) !== null) {
                throw new ProgramadaExistente;
            }

            $inicio = $this->inicios->resolver($datos->inicio, $ahora, $contexto->zonaHoraria);

            $errores = $this->reglas->erroresDePublicacion($tipo, $borrador->descripcion, $borrador->parametros, $motivo, $revisor, $observaciones);
            if ($errores !== []) {
                throw new DatosInvalidos($errores);
            }

            $numero = $this->politicas->siguienteNumero($contexto->tenantId, $cabecera->id);
            $canonicos = $this->reglas->canonicos($tipo, $borrador->parametros);
            $sellado = $this->reglas->sellar($tipo, $numero, $inicio['utc']->format('Y-m-d\TH:i:s\Z'), $canonicos);

            $publicada = $this->politicas->publicar(
                $contexto->tenantId, $versionId, $datos->version, $numero, $actorId, $ahora, $inicio['utc'],
                $motivo, $revisor, $observaciones === '' ? null : $observaciones,
                $canonicos, $sellado['condiciones'], $sellado['json'], $sellado['hash'],
            );
            $this->politicas->registrarAccion($contexto->tenantId, $cabecera->id, $versionId, 'Publicacion', $actorId, $motivo, strtolower($clave), $hash, $ahora);

            $versiones = $this->politicas->versionesDe($contexto->tenantId, [$cabecera->id])[$cabecera->id] ?? [];

            return ['version' => $this->armador->dtoDe($publicada, $versiones, $ahora, $contexto->zonaHoraria), 'repetida' => false];
        });
    }
}
