<?php

namespace App\Modules\GestionPersonal\Application\Services;

/**
 * Tipos de contrato habilitados por el producto (lista fija; no configurable por tenant). El vínculo guarda la
 * clave estable; la etiqueta es solo de presentación.
 */
final class CatalogoDeContratos
{
    /** @return array<string, string> clave => etiqueta */
    public function todos(): array
    {
        /** @var array<string, string> $contratos */
        $contratos = config('hrms.gestion_personal.tipos_contrato', []);

        return $contratos;
    }

    /** @return list<string> */
    public function claves(): array
    {
        return array_map('strval', array_keys($this->todos()));
    }

    /** @return list<array{clave: string, etiqueta: string}> */
    public function opciones(): array
    {
        $opciones = [];
        foreach ($this->todos() as $clave => $etiqueta) {
            $opciones[] = ['clave' => (string) $clave, 'etiqueta' => $etiqueta];
        }

        return $opciones;
    }

    /** @return array{clave: string, etiqueta: string} */
    public function describir(string $clave): array
    {
        return ['clave' => $clave, 'etiqueta' => $this->todos()[$clave] ?? $clave];
    }
}
