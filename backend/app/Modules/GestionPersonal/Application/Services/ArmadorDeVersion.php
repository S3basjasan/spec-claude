<?php

namespace App\Modules\GestionPersonal\Application\Services;

use App\Modules\GestionPersonal\Application\DTO\PoliticaResumenDTO;
use App\Modules\GestionPersonal\Application\DTO\VersionDTO;
use App\Modules\GestionPersonal\Domain\Entities\VersionDePolitica;
use App\Modules\GestionPersonal\Domain\Services\ClasificadorDeVersiones;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeVersion;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;
use DateTimeImmutable;
use DateTimeZone;

/** Arma las representaciones de versiones y resúmenes con el estado calculado para un instante. */
final class ArmadorDeVersion
{
    public function __construct(private readonly ClasificadorDeVersiones $clasificador) {}

    public function dto(VersionDePolitica $version, EstadoDeVersion $estado, string $zona): VersionDTO
    {
        $local = $version->inicioVigencia?->setTimezone(new DateTimeZone($zona))->format('Y-m-d\TH:i');

        return new VersionDTO($version, $estado, $local);
    }

    /**
     * Estado de una versión concreta entre las de su política.
     *
     * @param  list<VersionDePolitica>  $versiones
     */
    public function dtoDe(VersionDePolitica $version, array $versiones, DateTimeImmutable $ahora, string $zona): VersionDTO
    {
        $estados = $this->clasificador->estados($this->conVersion($versiones, $version), $ahora);

        return $this->dto($version, $estados[$version->id] ?? EstadoDeVersion::Borrador, $zona);
    }

    /**
     * @param  list<VersionDePolitica>  $versiones  Las no descartadas del tipo.
     */
    public function resumen(TipoDePolitica $tipo, array $versiones, DateTimeImmutable $ahora, string $zona): PoliticaResumenDTO
    {
        $vigente = $this->clasificador->vigente($versiones, $ahora);
        $programada = $this->clasificador->programada($versiones, $ahora);
        $borrador = $this->clasificador->borrador($versiones);

        $disponible = $vigente !== null && $tipo->tieneDisponibilidad() ? ($vigente->parametros['disponible'] ?? null) : null;
        $situacion = match (true) {
            $vigente === null => 'SinConfigurar',
            $disponible === false => 'NoAdmiteSolicitudes',
            default => 'Vigente',
        };

        return new PoliticaResumenDTO(
            $tipo,
            $situacion,
            is_bool($disponible) ? $disponible : null,
            $vigente === null ? null : $this->dto($vigente, EstadoDeVersion::Vigente, $zona),
            $programada === null ? null : $this->dto($programada, EstadoDeVersion::Programada, $zona),
            $borrador === null ? null : $this->dto($borrador, EstadoDeVersion::Borrador, $zona),
        );
    }

    /**
     * Versiones publicadas de más reciente a más antigua, con su estado.
     *
     * @param  list<VersionDePolitica>  $versiones
     * @return list<VersionDTO>
     */
    public function historial(array $versiones, DateTimeImmutable $ahora, string $zona): array
    {
        $estados = $this->clasificador->estados($versiones, $ahora);
        $publicadas = array_values(array_filter($versiones, fn (VersionDePolitica $v) => $v->estaPublicada()));
        usort($publicadas, fn (VersionDePolitica $a, VersionDePolitica $b) => $b->numero <=> $a->numero);

        return array_map(fn (VersionDePolitica $v) => $this->dto($v, $estados[$v->id], $zona), $publicadas);
    }

    /**
     * @param  list<VersionDePolitica>  $versiones
     * @return list<VersionDePolitica>
     */
    private function conVersion(array $versiones, VersionDePolitica $version): array
    {
        foreach ($versiones as $existente) {
            if ($existente->id === $version->id) {
                return array_map(fn (VersionDePolitica $v) => $v->id === $version->id ? $version : $v, $versiones);
            }
        }

        return [...$versiones, $version];
    }
}
