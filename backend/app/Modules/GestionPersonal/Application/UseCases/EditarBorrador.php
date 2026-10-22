<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\VersionDTO;
use App\Modules\GestionPersonal\Application\Ports\RelojPort;
use App\Modules\GestionPersonal\Application\Ports\TransaccionPort;
use App\Modules\GestionPersonal\Application\Services\ArmadorDeVersion;
use App\Modules\GestionPersonal\Application\Services\ResolvedorDeBorrador;
use App\Modules\GestionPersonal\Domain\Exceptions\BorradorModificado;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\EscrituraDeshabilitada;
use App\Modules\GestionPersonal\Domain\Exceptions\PoliticaNoEncontrada;
use App\Modules\GestionPersonal\Domain\Repositories\PoliticaRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\ReglasDePolitica;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeVersion;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;

/** Edita la descripción y los parámetros del borrador activo; la completitud se exige solo al publicar. */
final class EditarBorrador
{
    public function __construct(
        private readonly PoliticaRepositoryInterface $politicas,
        private readonly ResolvedorDeBorrador $borradores,
        private readonly ReglasDePolitica $reglas,
        private readonly ArmadorDeVersion $armador,
        private readonly TransaccionPort $transaccion,
        private readonly RelojPort $reloj,
    ) {}

    /**
     * @param  array<string, mixed>|null  $parametros  Si se envía reemplaza todos los parámetros del borrador.
     *
     * @throws EscrituraDeshabilitada
     * @throws PoliticaNoEncontrada
     * @throws BorradorModificado
     * @throws DatosInvalidos
     */
    public function ejecutar(ContextoDeTenant $contexto, int $actorId, TipoDePolitica $tipo, int $versionId, int $versionEsperada, mixed $descripcion, mixed $parametros): VersionDTO
    {
        if (! config('hrms.gestion_personal.politicas_habilitado', true)) {
            throw new EscrituraDeshabilitada('La configuración de políticas está deshabilitada temporalmente.');
        }

        return $this->transaccion->enTransaccion(function () use ($contexto, $actorId, $tipo, $versionId, $versionEsperada, $descripcion, $parametros) {
            $cabecera = $this->politicas->cabecera($contexto->tenantId, $tipo) ?? throw new PoliticaNoEncontrada;
            $actual = $this->borradores->obtener($contexto->tenantId, $cabecera->id, $versionId, $versionEsperada);

            $textoFinal = $descripcion === null ? $actual->descripcion : $this->reglas->texto($descripcion);
            $parametrosFinales = $parametros === null ? $actual->parametros : $parametros;

            if (! is_array($parametrosFinales)) {
                throw new DatosInvalidos(['parametros' => ['Los parámetros no son válidos.']]);
            }

            $errores = $this->reglas->erroresDeEdicion($tipo, $textoFinal, $parametrosFinales);
            if ($errores !== []) {
                throw new DatosInvalidos($errores);
            }

            $version = $this->politicas->actualizarBorrador(
                $contexto->tenantId, $versionId, $versionEsperada, $actorId, $textoFinal,
                $this->reglas->canonicos($tipo, $parametrosFinales), $this->reloj->ahora(),
            );

            return $this->armador->dto($version, EstadoDeVersion::Borrador, $contexto->zonaHoraria);
        });
    }
}
