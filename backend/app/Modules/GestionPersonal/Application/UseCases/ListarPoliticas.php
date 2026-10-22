<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\PoliticaResumenDTO;
use App\Modules\GestionPersonal\Application\Ports\RelojPort;
use App\Modules\GestionPersonal\Application\Services\ArmadorDeVersion;
use App\Modules\GestionPersonal\Domain\Repositories\PoliticaRepositoryInterface;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;

/** Los siete tipos con su situación de configuración; tolera cabeceras ausentes (organización nueva). */
final class ListarPoliticas
{
    public function __construct(
        private readonly PoliticaRepositoryInterface $politicas,
        private readonly ArmadorDeVersion $armador,
        private readonly RelojPort $reloj,
    ) {}

    /**
     * @param  string|null  $situacion  `SinConfigurar` | `Vigente` | `NoAdmiteSolicitudes` | `ConProgramada`
     * @return list<PoliticaResumenDTO>
     */
    public function ejecutar(ContextoDeTenant $contexto, ?TipoDePolitica $tipo = null, ?string $situacion = null): array
    {
        $ahora = $this->reloj->ahora();
        $cabeceras = $this->politicas->cabeceras($contexto->tenantId);
        $versiones = $this->politicas->versionesDe($contexto->tenantId, array_map(fn ($c) => $c->id, array_values($cabeceras)));

        $resumenes = [];
        foreach (TipoDePolitica::cases() as $caso) {
            if ($tipo !== null && $caso !== $tipo) {
                continue;
            }

            $cabecera = $cabeceras[$caso->value] ?? null;
            $resumen = $this->armador->resumen($caso, $cabecera === null ? [] : ($versiones[$cabecera->id] ?? []), $ahora, $contexto->zonaHoraria);

            if ($situacion !== null && ! $this->coincide($resumen, $situacion)) {
                continue;
            }
            $resumenes[] = $resumen;
        }

        return $resumenes;
    }

    private function coincide(PoliticaResumenDTO $resumen, string $situacion): bool
    {
        return $situacion === 'ConProgramada' ? $resumen->programada !== null : $resumen->situacion === $situacion;
    }
}
