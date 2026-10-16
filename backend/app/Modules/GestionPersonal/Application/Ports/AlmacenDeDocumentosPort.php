<?php

namespace App\Modules\GestionPersonal\Application\Ports;

use App\Modules\GestionPersonal\Domain\Exceptions\AlmacenamientoNoDisponible;

/**
 * Almacenamiento privado de los archivos. No ofrece ningún método que genere una dirección (firmada o pública).
 */
interface AlmacenDeDocumentosPort
{
    /**
     * Escribe el objeto en `clave`; nunca sobrescribe uno existente.
     *
     * @throws AlmacenamientoNoDisponible
     */
    public function guardar(string $rutaTemporal, string $clave): void;

    /**
     * Abre el flujo de lectura antes de enviar un solo byte.
     *
     * @return resource
     *
     * @throws AlmacenamientoNoDisponible
     */
    public function leerComoFlujo(string $clave);

    /** Elimina solo ese objeto; false si no se pudo. */
    public function eliminar(string $clave): bool;

    /**
     * @return iterable<array{clave: string, modificado_en: int}>
     */
    public function listar(string $prefijo): iterable;
}
