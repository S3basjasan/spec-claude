<?php

namespace App\Modules\GestionPersonal\Domain\Services;

use App\Modules\GestionPersonal\Domain\Entities\VersionDePolitica;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeVersion;
use DateTimeImmutable;

/**
 * Estado de cada versión derivado de instantes (data-model.md §Estados): ningún proceso periódico cambia etiquetas.
 */
final class ClasificadorDeVersiones
{
    /**
     * @param  list<VersionDePolitica>  $versiones  Todas las versiones de un mismo tipo y tenant.
     * @return array<int, EstadoDeVersion> id de versión => estado; los borradores descartados no aparecen.
     */
    public function estados(array $versiones, DateTimeImmutable $ahora): array
    {
        $vigente = $this->vigente($versiones, $ahora);
        $estados = [];

        foreach ($versiones as $version) {
            if ($version->descartadaEn !== null) {
                continue;
            }

            $estados[$version->id] = match (true) {
                $version->publicadaEn === null => EstadoDeVersion::Borrador,
                $version->retiradaEn !== null => EstadoDeVersion::Retirada,
                $version->inicioVigencia > $ahora => EstadoDeVersion::Programada,
                $vigente !== null && $version->id === $vigente->id => EstadoDeVersion::Vigente,
                default => EstadoDeVersion::Sustituida,
            };
        }

        return $estados;
    }

    /**
     * La publicada no retirada con mayor `inicio_vigencia` ≤ ahora (desempate: mayor número).
     *
     * @param  list<VersionDePolitica>  $versiones
     */
    public function vigente(array $versiones, DateTimeImmutable $ahora): ?VersionDePolitica
    {
        $vigente = null;
        foreach ($versiones as $version) {
            if ($version->publicadaEn === null || $version->retiradaEn !== null || $version->inicioVigencia > $ahora) {
                continue;
            }
            if ($vigente === null
                || $version->inicioVigencia > $vigente->inicioVigencia
                || ($version->inicioVigencia == $vigente->inicioVigencia && $version->numero > $vigente->numero)) {
                $vigente = $version;
            }
        }

        return $vigente;
    }

    /**
     * @param  list<VersionDePolitica>  $versiones
     */
    public function programada(array $versiones, DateTimeImmutable $ahora): ?VersionDePolitica
    {
        foreach ($versiones as $version) {
            if ($version->publicadaEn !== null && $version->retiradaEn === null && $version->inicioVigencia > $ahora) {
                return $version;
            }
        }

        return null;
    }

    /**
     * @param  list<VersionDePolitica>  $versiones
     */
    public function borrador(array $versiones): ?VersionDePolitica
    {
        foreach ($versiones as $version) {
            if ($version->esBorradorActivo()) {
                return $version;
            }
        }

        return null;
    }
}
