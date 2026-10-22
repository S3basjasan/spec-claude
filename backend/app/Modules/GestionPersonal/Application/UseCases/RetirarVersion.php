<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\VersionDTO;
use App\Modules\GestionPersonal\Application\Ports\RelojPort;
use App\Modules\GestionPersonal\Application\Ports\TransaccionPort;
use App\Modules\GestionPersonal\Application\Services\ArmadorDeVersion;
use App\Modules\GestionPersonal\Domain\Exceptions\ClaveDeOperacionRequerida;
use App\Modules\GestionPersonal\Domain\Exceptions\ClaveDeOperacionReutilizada;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\EscrituraDeshabilitada;
use App\Modules\GestionPersonal\Domain\Exceptions\PoliticaNoEncontrada;
use App\Modules\GestionPersonal\Domain\Exceptions\VersionNoRetirable;
use App\Modules\GestionPersonal\Domain\Exceptions\VigenciaIniciada;
use App\Modules\GestionPersonal\Domain\Repositories\PoliticaRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\ReglasDePolitica;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;

/**
 * Retira una versión programada antes de su inicio. La comprobación usa el reloj del servidor y se repite dentro de la
 * transacción que bloquea la cabecera; la versión se conserva y su número no se reutiliza.
 */
final class RetirarVersion
{
    public function __construct(
        private readonly PoliticaRepositoryInterface $politicas,
        private readonly ReglasDePolitica $reglas,
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
     * @throws DatosInvalidos
     * @throws PoliticaNoEncontrada
     * @throws VersionNoRetirable
     * @throws VigenciaIniciada
     */
    public function ejecutar(ContextoDeTenant $contexto, int $actorId, TipoDePolitica $tipo, int $versionId, string $justificacion, ?string $clave): array
    {
        if (! config('hrms.gestion_personal.politicas_habilitado', true)) {
            throw new EscrituraDeshabilitada('La configuración de políticas está deshabilitada temporalmente.');
        }

        if ($clave === null || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $clave) !== 1) {
            throw new ClaveDeOperacionRequerida;
        }

        $justificacion = $this->reglas->texto($justificacion);
        $errores = $this->reglas->erroresDeRetiro($justificacion);
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        $hash = hash('sha256', ReglasDePolitica::json(['accion' => 'Retiro', 'tipo' => $tipo->value, 'version_id' => $versionId, 'justificacion' => $justificacion]));

        return $this->transaccion->enTransaccion(function () use ($contexto, $actorId, $tipo, $versionId, $justificacion, $clave, $hash) {
            $ahora = $this->reloj->ahora();
            $cabecera = $this->politicas->bloquear($contexto->tenantId, $tipo);
            $versiones = $this->politicas->versionesDe($contexto->tenantId, [$cabecera->id])[$cabecera->id] ?? [];

            $previa = $this->politicas->accionPorOperacion($contexto->tenantId, strtolower($clave));
            if ($previa !== null) {
                if ($previa->accion !== 'Retiro' || $previa->politicaId !== $cabecera->id || $previa->versionId !== $versionId || $previa->operacionHash !== $hash) {
                    throw new ClaveDeOperacionReutilizada;
                }
                $original = $this->politicas->version($contexto->tenantId, $cabecera->id, $versionId) ?? throw new PoliticaNoEncontrada;

                return ['version' => $this->armador->dtoDe($original, $versiones, $ahora, $contexto->zonaHoraria), 'repetida' => true];
            }

            $version = $this->politicas->version($contexto->tenantId, $cabecera->id, $versionId);
            if ($version === null || $version->descartadaEn !== null) {
                throw new PoliticaNoEncontrada;
            }
            if (! $version->estaPublicada() || $version->retiradaEn !== null) {
                throw new VersionNoRetirable;
            }
            if ($version->inicioVigencia <= $ahora) {
                throw new VigenciaIniciada;
            }

            $retirada = $this->politicas->retirar($contexto->tenantId, $versionId, $actorId, $ahora, $justificacion);
            $this->politicas->registrarAccion($contexto->tenantId, $cabecera->id, $versionId, 'Retiro', $actorId, $justificacion, strtolower($clave), $hash, $ahora);

            $versiones = $this->politicas->versionesDe($contexto->tenantId, [$cabecera->id])[$cabecera->id] ?? [];

            return ['version' => $this->armador->dtoDe($retirada, $versiones, $ahora, $contexto->zonaHoraria), 'repetida' => false];
        });
    }
}
