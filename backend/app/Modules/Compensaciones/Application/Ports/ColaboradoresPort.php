<?php

namespace App\Modules\Compensaciones\Application\Ports;

/** Lectura de colaboradores (módulo Gestión de Personal) a través de su contrato público. */
interface ColaboradoresPort
{
    /** Id del colaborador asociado a la cuenta del usuario, o null si la cuenta no tiene ficha. */
    public function cuentaDeUsuario(int $usuarioId): ?int;

    /**
     * Fichas existentes entre los ids, indexadas por id.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array{id: int, codigo: string, nombre: string}>
     */
    public function nombres(array $ids): array;

    /**
     * Ids, entre los dados, con vínculo laboral vigente en la fecha (`Y-m-d`).
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    public function vigentes(array $ids, string $fecha): array;

    /**
     * Unidad actual de cada colaborador en la fecha (`Y-m-d`): colaborador_id => unidad_id o null.
     *
     * @param  array<int, int>  $ids
     * @return array<int, int|null>
     */
    public function unidadActual(array $ids, string $fecha): array;
}
