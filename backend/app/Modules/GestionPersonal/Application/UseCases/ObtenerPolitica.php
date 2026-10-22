<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\PoliticaDetalleDTO;
use App\Modules\GestionPersonal\Application\Ports\RelojPort;
use App\Modules\GestionPersonal\Application\Services\ArmadorDeVersion;
use App\Modules\GestionPersonal\Domain\Repositories\PoliticaRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\CatalogoDePoliticas;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;

/** Detalle de un tipo: resumen, definición de parámetros y reglas fijas, y las versiones publicadas. */
final class ObtenerPolitica
{
    public function __construct(
        private readonly PoliticaRepositoryInterface $politicas,
        private readonly ArmadorDeVersion $armador,
        private readonly CatalogoDePoliticas $catalogo,
        private readonly RelojPort $reloj,
    ) {}

    public function ejecutar(ContextoDeTenant $contexto, TipoDePolitica $tipo): PoliticaDetalleDTO
    {
        $ahora = $this->reloj->ahora();
        $cabecera = $this->politicas->cabecera($contexto->tenantId, $tipo);
        $versiones = $cabecera === null ? [] : ($this->politicas->versionesDe($contexto->tenantId, [$cabecera->id])[$cabecera->id] ?? []);

        return new PoliticaDetalleDTO(
            $this->armador->resumen($tipo, $versiones, $ahora, $contexto->zonaHoraria),
            $this->catalogo->definicion($tipo),
            $this->armador->historial($versiones, $ahora, $contexto->zonaHoraria),
        );
    }
}
