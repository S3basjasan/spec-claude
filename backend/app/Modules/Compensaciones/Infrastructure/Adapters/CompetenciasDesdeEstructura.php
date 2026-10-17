<?php

namespace App\Modules\Compensaciones\Infrastructure\Adapters;

use App\Modules\Compensaciones\Application\Ports\CompetenciasPort;
use App\Modules\Compensaciones\Domain\ValueObjects\Normalizador;
use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;

/**
 * Lee el Diccionario de Competencias solo a través de la Facade pública de Estructura: sin JOIN ni lectura de
 * sus tablas. No guarda copias: el estado de una competencia puede cambiar entre llamadas (la Facade de
 * categorías conserva su instancia mientras vive la aplicación).
 */
class CompetenciasDesdeEstructura implements CompetenciasPort
{
    public function activas(): array
    {
        return array_values(array_filter($this->todas(), fn (array $c) => $c['estado'] === 'Activo'));
    }

    public function todas(): array
    {
        return array_map(fn (array $c) => [
            'id' => (int) $c['id'],
            'codigo' => (string) $c['codigo'],
            'nombre' => (string) $c['nombre'],
            'tipo' => (string) $c['tipo'],
            'estado' => (string) $c['estado'],
        ], EstructuraFacade::getTodasLasCompetencias());
    }

    public function porId(int $id): ?array
    {
        foreach ($this->todas() as $competencia) {
            if ($competencia['id'] === $id) {
                return $competencia;
            }
        }

        return null;
    }

    public function porIds(array $ids): array
    {
        $resultado = [];

        foreach ($this->todas() as $competencia) {
            if (in_array($competencia['id'], $ids, true)) {
                $resultado[$competencia['id']] = $competencia;
            }
        }

        return $resultado;
    }

    public function idsPorNombre(string $texto): array
    {
        $buscado = Normalizador::normalizar($texto);

        return array_values(array_map(
            fn (array $c) => $c['id'],
            array_filter($this->todas(), fn (array $c) => str_contains(Normalizador::normalizar($c['nombre']), $buscado)),
        ));
    }

    public function estaActiva(int $id): bool
    {
        return ($this->porId($id)['estado'] ?? null) === 'Activo';
    }
}
