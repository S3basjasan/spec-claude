<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\VersionDTO;
use App\Modules\GestionPersonal\Application\Ports\RelojPort;
use App\Modules\GestionPersonal\Application\Services\ArmadorDeVersion;
use App\Modules\GestionPersonal\Domain\Exceptions\PoliticaNoEncontrada;
use App\Modules\GestionPersonal\Domain\Repositories\PoliticaRepositoryInterface;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;

/** Una versión (borrador o publicada) con su estado; un borrador descartado no existe para la consulta. */
final class ObtenerVersion
{
    public function __construct(
        private readonly PoliticaRepositoryInterface $politicas,
        private readonly ArmadorDeVersion $armador,
        private readonly RelojPort $reloj,
    ) {}

    /** @throws PoliticaNoEncontrada */
    public function ejecutar(ContextoDeTenant $contexto, TipoDePolitica $tipo, int $versionId): VersionDTO
    {
        $ahora = $this->reloj->ahora();
        $cabecera = $this->politicas->cabecera($contexto->tenantId, $tipo) ?? throw new PoliticaNoEncontrada;
        $version = $this->politicas->version($contexto->tenantId, $cabecera->id, $versionId);
        if ($version === null || $version->descartadaEn !== null) {
            throw new PoliticaNoEncontrada;
        }

        $versiones = $this->politicas->versionesDe($contexto->tenantId, [$cabecera->id])[$cabecera->id] ?? [];

        return $this->armador->dtoDe($version, $versiones, $ahora, $contexto->zonaHoraria);
    }
}
