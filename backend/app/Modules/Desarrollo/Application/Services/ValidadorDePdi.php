<?php

namespace App\Modules\Desarrollo\Application\Services;

use App\Modules\Desarrollo\Application\Ports\AprobadoresInterface;
use App\Modules\Desarrollo\Application\Ports\ColaboradoresVigentesInterface;
use App\Modules\Desarrollo\Application\Ports\CompetenciasInterface;
use App\Modules\Desarrollo\Application\Ports\ConfiguracionDePdiInterface;
use App\Modules\Desarrollo\Application\Ports\PuestosParaRutasInterface;
use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Application\Ports\ValidadorDeVinculoInterface;
use App\Modules\Desarrollo\Domain\Entities\Pdi;
use App\Modules\Desarrollo\Domain\Exceptions\DatosInvalidosDePdi;
use App\Modules\Desarrollo\Domain\Exceptions\VinculoInvalido;
use App\Modules\Desarrollo\Domain\Services\ReglasDeCompromiso;
use App\Modules\Desarrollo\Domain\ValueObjects\DimensionDeCompromiso;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDePdi;

/**
 * Validaciones de un plan que necesitan otros módulos (puesto activo, elementos activos, administrador, vínculo vigente,
 * vínculos con el Plan de Formación) más las reglas estructurales del dominio. Reúne todos los errores por campo.
 */
final class ValidadorDePdi
{
    public function __construct(
        private readonly ReglasDeCompromiso $reglas,
        private readonly CompetenciasInterface $competencias,
        private readonly PuestosParaRutasInterface $puestos,
        private readonly AprobadoresInterface $aprobadores,
        private readonly ColaboradoresVigentesInterface $colaboradores,
        private readonly ValidadorDeVinculoInterface $vinculos,
        private readonly ConfiguracionDePdiInterface $configuracion,
        private readonly RelojInterface $reloj,
    ) {}

    /** @return list<DimensionDeCompromiso> */
    public function dimensionesDisponibles(): array
    {
        $dimensiones = [DimensionDeCompromiso::Competencia];
        if ($this->configuracion->conocimientosConGrado()) {
            $dimensiones[] = DimensionDeCompromiso::Conocimiento;
        }

        return [...$dimensiones, DimensionDeCompromiso::Experiencia, DimensionDeCompromiso::Meta];
    }

    /**
     * Reglas de la cabecera que deben cumplirse antes de construir la entidad.
     *
     * @param  array<string, mixed>  $datos
     */
    public function validarCabecera(int $tenantId, TipoDePdi $tipo, array $datos): void
    {
        $errores = [];

        if (trim((string) ($datos['objetivo'] ?? '')) === '') {
            $errores['objetivo'][] = 'El objetivo del plan es obligatorio.';
        }

        $puesto = $datos['puesto_objetivo_id'] ?? null;
        if ($tipo->requierePuestoObjetivo()) {
            if ($puesto === null) {
                $errores['puesto_objetivo_id'][] = 'El puesto objetivo es obligatorio en los planes de Carrera y Talento.';
            } elseif (! $this->puestos->estaActivo((int) $puesto)) {
                $errores['puesto_objetivo_id'][] = 'El puesto objetivo no existe o está inactivo.';
            }
        } elseif ($puesto !== null) {
            $errores['puesto_objetivo_id'][] = 'El plan de Mejora no tiene puesto objetivo.';
        }

        if (($datos['fecha_fin'] ?? '') < ($datos['fecha_inicio'] ?? '')) {
            $errores['fecha_fin'][] = 'La fecha de fin no puede ser anterior a la de inicio.';
        }

        if (! $this->aprobadores->esAdministradorActivo($tenantId, (int) ($datos['responsable_id'] ?? 0))) {
            $errores['responsable_id'][] = 'El responsable debe ser un administrador activo.';
        }

        if ($errores !== []) {
            throw DatosInvalidosDePdi::conErrores($errores);
        }
    }

    /** El colaborador debe tener vínculo vigente (solo al crear un plan). */
    public function validarColaboradorVigente(int $tenantId, int $usuarioId, int $colaboradorId): void
    {
        $hoy = $this->reloj->hoy($usuarioId)->format('Y-m-d');
        if ($this->colaboradores->vigentesPorIds($tenantId, [$colaboradorId], $hoy) === []) {
            throw DatosInvalidosDePdi::campo('colaborador_id', 'El colaborador no tiene un vínculo vigente.');
        }
    }

    /** Reglas estructurales, elementos activos y vínculos válidos de compromisos, acciones e hitos. */
    public function validarEstructura(Pdi $pdi): void
    {
        $errores = [];

        try {
            $this->reglas->validar($pdi->periodo, $pdi->compromisos, $pdi->hitos, $this->dimensionesDisponibles());
        } catch (DatosInvalidosDePdi $e) {
            $errores = $e->errores();
        }

        foreach ($pdi->compromisos as $i => $c) {
            if ($c->dimension === DimensionDeCompromiso::Competencia && $c->elementoId !== null && ! $this->competencias->estaActiva($c->elementoId)) {
                $errores["compromisos.{$i}.elemento_id"][] = 'La competencia no existe o está inactiva.';
            }

            foreach ($c->acciones as $j => $a) {
                if ($a->planAccionId === null || isset($errores["compromisos.{$i}.acciones.{$j}.plan_accion_id"])) {
                    continue;
                }
                try {
                    $this->vinculos->validar($pdi->tenantId, $pdi->colaboradorId, $a->tipo, $a->planAccionId);
                } catch (VinculoInvalido $e) {
                    $errores["compromisos.{$i}.acciones.{$j}.plan_accion_id"][] = $e->getMessage();
                }
            }
        }

        if ($errores !== []) {
            throw DatosInvalidosDePdi::conErrores($errores);
        }
    }
}
