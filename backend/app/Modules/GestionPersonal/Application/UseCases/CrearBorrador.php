<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\VersionDTO;
use App\Modules\GestionPersonal\Application\Ports\RelojPort;
use App\Modules\GestionPersonal\Application\Ports\TransaccionPort;
use App\Modules\GestionPersonal\Application\Services\ArmadorDeVersion;
use App\Modules\GestionPersonal\Domain\Exceptions\BorradorExistente;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\EscrituraDeshabilitada;
use App\Modules\GestionPersonal\Domain\Repositories\PoliticaRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\ClasificadorDeVersiones;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeVersion;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;

/** Crea el borrador activo del tipo, vacío o a partir de una versión publicada del mismo tipo. */
final class CrearBorrador
{
    public function __construct(
        private readonly PoliticaRepositoryInterface $politicas,
        private readonly ClasificadorDeVersiones $clasificador,
        private readonly ArmadorDeVersion $armador,
        private readonly TransaccionPort $transaccion,
        private readonly RelojPort $reloj,
    ) {}

    /**
     * @throws EscrituraDeshabilitada
     * @throws BorradorExistente
     * @throws DatosInvalidos
     */
    public function ejecutar(ContextoDeTenant $contexto, int $actorId, TipoDePolitica $tipo, ?int $baseVersionId): VersionDTO
    {
        if (! config('hrms.gestion_personal.politicas_habilitado', true)) {
            throw new EscrituraDeshabilitada('La configuración de políticas está deshabilitada temporalmente.');
        }

        return $this->transaccion->enTransaccion(function () use ($contexto, $actorId, $tipo, $baseVersionId) {
            $ahora = $this->reloj->ahora();
            $this->politicas->asegurarCabeceras($contexto->tenantId);
            $cabecera = $this->politicas->bloquear($contexto->tenantId, $tipo);
            $versiones = $this->politicas->versionesDe($contexto->tenantId, [$cabecera->id])[$cabecera->id] ?? [];

            $existente = $this->clasificador->borrador($versiones);
            if ($existente !== null) {
                throw new BorradorExistente($existente->id);
            }

            $descripcion = '';
            $parametros = [];
            if ($baseVersionId !== null) {
                $base = $this->politicas->version($contexto->tenantId, $cabecera->id, $baseVersionId);
                if ($base === null || ! $base->estaPublicada()) {
                    throw new DatosInvalidos(['base_version_id' => ['La versión base no existe o no está publicada.']]);
                }
                $descripcion = $base->descripcion;
                $parametros = $base->parametros;
            }

            $version = $this->politicas->crearBorrador($contexto->tenantId, $cabecera->id, $actorId, $descripcion, $parametros, $baseVersionId, $ahora);
            $this->politicas->registrarAccion($contexto->tenantId, $cabecera->id, $version->id, 'BorradorCreado', $actorId, null, null, null, $ahora);

            return $this->armador->dto($version, EstadoDeVersion::Borrador, $contexto->zonaHoraria);
        });
    }
}
