<?php

namespace App\Modules\Desarrollo\Domain\Repositories;

use App\Modules\Desarrollo\Domain\Entities\Microcontenido;
use App\Modules\Desarrollo\Domain\ValueObjects\DatosMicro;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoMicro;
use App\Modules\Desarrollo\Domain\ValueObjects\FiltroMicro;

/** Persistencia del repositorio de micro-contenidos: contenido, preguntas, vínculos con competencias e historial. */
interface MicrocontenidoRepositoryInterface
{
    /**
     * Crea el contenido en Borrador con su código MICRO-NNNN, sus preguntas, sus vínculos (con historial del Catálogo)
     * y la primera entrada del historial de estados, todo en una transacción. Devuelve el id.
     */
    public function crear(DatosMicro $datos, ?int $actorId): int;

    /**
     * Reemplaza los datos editables. Si `$datos->preguntas` no es null reemplaza las preguntas (borrado explícito de las
     * anteriores y descarte de los intentos en curso). Sincroniza los vínculos y registra el historial del Catálogo.
     */
    public function actualizar(int $id, DatosMicro $datos, ?int $actorId): void;

    public function obtener(int $id): ?Microcontenido;

    /**
     * Varios micro-contenidos (con preguntas) por id, indexados por id; los ids inexistentes no aparecen.
     *
     * @param  array<int, int>  $ids
     * @return array<int, Microcontenido>
     */
    public function obtenerVarios(array $ids): array;

    /**
     * @return array{items: array<int, Microcontenido>, total: int}
     */
    public function listar(FiltroMicro $filtro): array;

    /** Cambia el estado y registra la entrada del historial. */
    public function cambiarEstado(int $id, EstadoMicro $nuevo, ?int $actorId): void;

    /**
     * Elimina el contenido con sus opciones, preguntas, historial y vínculos (sin cascade). Solo sin progreso.
     */
    public function eliminar(int $id, ?int $actorId): void;

    /**
     * @return array{items: array<int, array{id: int, estado_anterior: string|null, estado_nuevo: string, user_id: int|null, fecha: string|null}>, total: int}
     */
    public function historial(int $id, int $pagina, int $porPagina): array;

    /**
     * Micro-contenidos Publicados vinculados a la competencia.
     *
     * @return array<int, Microcontenido>
     */
    public function publicadosDeCompetencia(int $competenciaId): array;
}
