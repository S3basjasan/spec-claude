<?php

namespace App\Modules\GestionPersonal\Application\Ports;

use App\Modules\GestionPersonal\Domain\Exceptions\CategoriaInactiva;
use App\Modules\GestionPersonal\Domain\ValueObjects\Lectura;

/**
 * Lectura del catálogo para la gestión documental (GP-05). Las lecturas devuelven Lectura::noDisponible() ante un
 * fallo, nunca excepciones. Ningún método consulta documentos.
 */
interface CatalogoDeCategoriasPort
{
    /** @return Lectura Datos: list<array{id: int, nombre: string}> solo Activas, ordenadas por nombre. */
    public function activas(int $tenantId): Lectura;

    /**
     * @param  list<int>  $ids
     * @return Lectura Datos: [id => array{id: int, nombre: string, estado: string}] de cualquier estado, nombre vigente.
     */
    public function porIds(int $tenantId, array $ids): Lectura;

    /**
     * Debe ejecutarse dentro de la transacción que publica el documento: toma el bloqueo de la fila hasta su commit.
     *
     * @throws CategoriaInactiva Inexistente, de otro tenant o inactiva.
     */
    public function confirmarActiva(int $tenantId, int $categoriaId): void;
}
