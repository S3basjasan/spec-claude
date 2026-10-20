<?php

namespace App\Modules\Compensaciones\Application\Services;

use App\Modules\Compensaciones\Domain\Entities\Categoria;
use App\Modules\Compensaciones\Domain\Repositories\AuditoriaCategoriaRepository;

/**
 * Historial de cambios de una categoría: una fila por campo realmente modificado.
 */
final class AuditoriaDeCategoria
{
    public function __construct(private readonly AuditoriaCategoriaRepository $historial) {}

    /** Al crear se registran los campos iniciales con valor anterior nulo. */
    public function alCrear(Categoria $creada, int $actorId): void
    {
        $cambios = [
            'nombre' => [null, $creada->nombre->valor],
            'descripcion' => [null, $creada->descripcion],
            'competencia_id' => [null, (string) $creada->competenciaId],
            'puntuacion_base' => [null, (string) $creada->puntuacion->valor],
            'icono' => [null, $creada->icono->value],
            'indicadores' => [null, $creada->indicadoresComoJson()],
            'estado' => [null, $creada->estado->value],
        ];

        $this->historial->registrar((int) $creada->id, $cambios, $actorId);
    }

    /** Registra las diferencias entre dos versiones y devuelve cuántos campos cambiaron. */
    public function alModificar(Categoria $antes, Categoria $despues, int $actorId): int
    {
        $cambios = $antes->diferenciasCon($despues);

        if ($cambios !== []) {
            $this->historial->registrar((int) $antes->id, $cambios, $actorId);
        }

        return count($cambios);
    }
}
