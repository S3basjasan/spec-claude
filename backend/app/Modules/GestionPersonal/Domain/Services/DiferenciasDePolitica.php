<?php

namespace App\Modules\GestionPersonal\Domain\Services;

use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;

/** Diferencias entre una propuesta y la versión vigente real (o todos los parámetros si no hay vigente). */
final class DiferenciasDePolitica
{
    public function __construct(private readonly CatalogoDePoliticas $catalogo) {}

    /**
     * @param  array<string, mixed>  $propuesta  `{descripcion, parametros}`
     * @param  array<string, mixed>|null  $vigente  `{descripcion, parametros}` o null
     * @return list<array{clave: string, etiqueta: string, anterior: mixed, nuevo: mixed}>
     */
    public function entre(TipoDePolitica $tipo, array $propuesta, ?array $vigente): array
    {
        $diferencias = [];

        $nuevaDescripcion = (string) ($propuesta['descripcion'] ?? '');
        $anteriorDescripcion = $vigente === null ? null : (string) ($vigente['descripcion'] ?? '');
        if ($nuevaDescripcion !== $anteriorDescripcion) {
            $diferencias[] = ['clave' => 'descripcion', 'etiqueta' => 'Descripción', 'anterior' => $anteriorDescripcion, 'nuevo' => $nuevaDescripcion];
        }

        foreach ($this->catalogo->parametros($tipo) as $definicion) {
            $clave = $definicion['clave'];
            $nuevo = $propuesta['parametros'][$clave] ?? null;
            $anterior = $vigente['parametros'][$clave] ?? null;

            if ($nuevo !== $anterior) {
                $diferencias[] = ['clave' => $clave, 'etiqueta' => $definicion['etiqueta'], 'anterior' => $anterior, 'nuevo' => $nuevo];
            }
        }

        return $diferencias;
    }
}
