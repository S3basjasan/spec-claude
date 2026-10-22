<?php

namespace App\Modules\Desarrollo\Application\Services;

use App\Modules\Desarrollo\Application\Ports\DepartamentosInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeUsuarioInterface;
use App\Modules\Desarrollo\Application\Ports\PuestosParaRutasInterface;
use App\Modules\Desarrollo\Domain\Entities\NivelDeRuta;
use App\Modules\Desarrollo\Domain\Entities\Ruta;
use App\Modules\Desarrollo\Domain\Repositories\RutasRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeCantera;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeDiferencial;
use DateTimeImmutable;
use Throwable;

/**
 * Arma las respuestas de ruta (detalle del administrador y niveles del colaborador). Las competencias y los requisitos de
 * texto se leen del puesto en cada consulta y el diferencial se calcula; nada de eso se guarda. Si Estructura falla, se
 * devuelven los datos propios con `fuentes_no_disponibles` y sin inventar requisitos.
 */
final class ArmadorDeRutas
{
    public function __construct(
        private readonly RutasRepositoryInterface $rutas,
        private readonly PuestosParaRutasInterface $puestos,
        private readonly DepartamentosInterface $departamentos,
        private readonly NombresDeUsuarioInterface $usuarios,
        private readonly CalculadorDeDiferencial $diferencial,
        private readonly CalculadorDeCantera $cantera,
    ) {}

    /** @return array<string, mixed> detalle completo para el administrador, con cantera */
    public function detalle(Ruta $ruta): array
    {
        $fuentes = [];
        $niveles = $this->niveles($ruta, true, $fuentes);

        $activas = $this->rutas->activasDeRuta((int) $ruta->id);
        $cantera = $this->cantera->contar($activas, array_map(fn (NivelDeRuta $n) => (int) $n->id, $ruta->niveles));
        foreach ($niveles as &$nivel) {
            $nivel['inscritos_activos'] = $cantera['por_nivel'][$nivel['id']] ?? 0;
        }
        unset($nivel);

        $nombres = $this->nombresDeUsuarios([$ruta->creadoPor, $ruta->actualizadoPor]);

        return [
            'id' => $ruta->id,
            'codigo' => $ruta->codigo(),
            'nombre' => $ruta->nombre,
            'tipo' => $ruta->tipo->value,
            'descripcion' => $ruta->descripcion,
            'estado' => $ruta->estado->value,
            'departamento_id' => $ruta->departamentoId,
            'departamento' => $this->nombreDeDepartamento($ruta->departamentoId),
            'version' => $ruta->version,
            'creada_por' => $ruta->creadoPor === null ? null : ($nombres[$ruta->creadoPor] ?? null),
            'creada_at' => $this->fecha($ruta->creadoEn),
            'modificada_por' => $ruta->actualizadoPor === null ? null : ($nombres[$ruta->actualizadoPor] ?? null),
            'modificada_at' => $this->fecha($ruta->actualizadoEn),
            'niveles' => $niveles,
            'inscritos_count' => $cantera['total'],
            'sin_nivel' => $cantera['sin_nivel'],
            'fuentes_no_disponibles' => array_values(array_unique($fuentes)),
        ];
    }

    /**
     * Niveles con sus competencias, conocimientos informativos y experiencia, sin datos de gestión (para el colaborador).
     *
     * @param  list<string>  $fuentes  se completa con las fuentes que fallaron
     * @return list<array<string, mixed>>
     */
    public function nivelesParaColaborador(Ruta $ruta, array &$fuentes): array
    {
        return $this->niveles($ruta, false, $fuentes);
    }

    /**
     * @param  list<string>  $fuentes
     * @return list<array<string, mixed>>
     */
    private function niveles(Ruta $ruta, bool $conGestion, array &$fuentes): array
    {
        $enUso = $conGestion ? $this->rutas->nivelesEnUso((int) $ruta->id) : [];
        $perfiles = [];
        foreach (array_unique($ruta->idsDePuestos()) as $puestoId) {
            try {
                $perfiles[$puestoId] = $this->puestos->perfil($puestoId);
            } catch (Throwable) {
                $fuentes[] = 'estructura';
                $perfiles[$puestoId] = false;
            }
        }

        $resultado = [];
        $anterior = null;
        foreach ($ruta->niveles as $nivel) {
            $perfil = $perfiles[$nivel->puestoId] ?? null;
            $disponible = is_array($perfil);
            $actual = [
                'competencias' => $disponible ? $perfil['competencias'] : [],
                'experiencia_anios' => $nivel->experienciaMinimaAnios,
            ];
            $dif = $this->diferencial->entre($anterior, $actual);

            $competencias = [];
            foreach ($actual['competencias'] as $c) {
                $competencias[] = $c + ['diferencial' => $dif['competencias'][$c['competencia_id']] ?? null];
            }

            $item = [
                'id' => $nivel->id,
                'orden' => $nivel->orden,
                'puesto_id' => $nivel->puestoId,
                'puesto' => $disponible
                    ? ['codigo' => $perfil['puesto']['codigo'], 'nombre' => $perfil['puesto']['nombre'], 'activo' => $perfil['puesto']['activo']]
                    : ['codigo' => null, 'nombre' => null, 'activo' => null],
                'nombre' => $nivel->nombre,
                'experiencia_minima_anios' => $nivel->experienciaMinimaAnios,
                'competencias' => $competencias,
                'competencias_quitadas' => $dif['quitadas'],
                'conocimientos' => [
                    'informativo' => true,
                    'formacion' => $disponible ? $perfil['requisitos']['formacion'] : [],
                    'capacitacion' => $disponible ? $perfil['requisitos']['capacitacion'] : [],
                ],
                'experiencia' => [
                    'anios_minimos' => $nivel->experienciaMinimaAnios,
                    'diferencial_anios' => $dif['experiencia_anios'],
                    'requisitos' => $disponible ? $perfil['requisitos']['experiencia'] : [],
                ],
            ];
            if ($conGestion) {
                $item['en_uso'] = isset($enUso[(int) $nivel->id]);
            }

            $resultado[] = $item;
            // Con Estructura caída no hay perfil: el diferencial del siguiente nivel no se inventa.
            $anterior = $disponible ? $actual : null;
        }

        return $resultado;
    }

    private function nombreDeDepartamento(?int $departamentoId): ?string
    {
        if ($departamentoId === null) {
            return null;
        }
        try {
            return $this->departamentos->porIds([$departamentoId])[$departamentoId] ?? null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  list<int|null>  $ids
     * @return array<int, string>
     */
    private function nombresDeUsuarios(array $ids): array
    {
        try {
            return $this->usuarios->nombresDe(array_values(array_filter($ids, fn ($i) => $i !== null)));
        } catch (Throwable) {
            return [];
        }
    }

    private function fecha(?DateTimeImmutable $fecha): ?string
    {
        return $fecha?->format(DATE_ATOM);
    }
}
