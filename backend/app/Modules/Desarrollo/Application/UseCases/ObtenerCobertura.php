<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\DTO\CoberturaDTO;
use App\Modules\Desarrollo\Application\Ports\CompetenciasInterface;
use App\Modules\Desarrollo\Domain\Repositories\CatalogoFormativoRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeCobertura;

/** Cobertura formativa: competencias activas con al menos un recurso disponible. */
final class ObtenerCobertura
{
    public function __construct(
        private readonly CompetenciasInterface $competencias,
        private readonly CatalogoFormativoRepositoryInterface $repositorio,
        private readonly CalculadorDeCobertura $calculador,
    ) {}

    public function ejecutar(): CoberturaDTO
    {
        $activas = array_values(array_filter($this->competencias->todas(), fn (array $c) => $c['estado'] === 'Activo'));
        $configuraciones = $this->repositorio->configuracionesDe(array_column($activas, 'id'));

        $recursos = [];
        foreach ($activas as $c) {
            $recursos[$c['id']] = $configuraciones[$c['id']]->recursosDisponibles();
        }

        $cobertura = $this->calculador->calcular($recursos);

        return new CoberturaDTO($cobertura->activasTotales, $cobertura->activasCubiertas, $cobertura->porcentaje);
    }
}
