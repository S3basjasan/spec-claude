<?php

namespace App\Modules\GestionPersonal\Infrastructure\Adapters;

use App\Modules\GestionPersonal\Application\Ports\AlmacenDeDocumentosPort;
use App\Modules\GestionPersonal\Domain\Exceptions\AlmacenamientoNoDisponible;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Almacén privado sobre un disco de Laravel configurable. Nunca genera URL ni sobrescribe objetos. */
final class AlmacenDeDocumentosDisco implements AlmacenDeDocumentosPort
{
    private function disco(): Filesystem
    {
        return Storage::disk((string) config('hrms.gestion_personal.documentos.disco', 'local'));
    }

    public function guardar(string $rutaTemporal, string $clave): void
    {
        try {
            $disco = $this->disco();

            if ($disco->exists($clave)) {
                throw new AlmacenamientoNoDisponible;
            }

            $origen = fopen($rutaTemporal, 'rb');
            if ($origen === false) {
                throw new AlmacenamientoNoDisponible;
            }

            try {
                $ok = $disco->writeStream($clave, $origen);
            } finally {
                fclose($origen);
            }

            if ($ok === false) {
                throw new AlmacenamientoNoDisponible;
            }
        } catch (AlmacenamientoNoDisponible $e) {
            throw $e;
        } catch (Throwable) {
            throw new AlmacenamientoNoDisponible;
        }
    }

    public function leerComoFlujo(string $clave)
    {
        try {
            $flujo = $this->disco()->readStream($clave);
        } catch (Throwable) {
            throw new AlmacenamientoNoDisponible;
        }

        if (! is_resource($flujo)) {
            throw new AlmacenamientoNoDisponible;
        }

        return $flujo;
    }

    public function eliminar(string $clave): bool
    {
        try {
            return $this->disco()->delete($clave);
        } catch (Throwable) {
            return false;
        }
    }

    public function listar(string $prefijo): iterable
    {
        $disco = $this->disco();

        foreach ($disco->allFiles($prefijo) as $clave) {
            yield ['clave' => $clave, 'modificado_en' => $disco->lastModified($clave)];
        }
    }
}
