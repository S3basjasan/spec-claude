<?php

namespace App\Modules\GestionPersonal\Application\DTO;

use DateTimeImmutable;

final class PrevisualizacionDTO
{
    /**
     * @param  array<string, mixed>  $condiciones
     * @param  list<array{clave: string, etiqueta: string, anterior: mixed, nuevo: mixed}>  $diferencias
     * @param  array<string, mixed>  $efecto
     * @param  array<string, list<string>>  $errores
     */
    public function __construct(
        public readonly array $condiciones,
        public readonly array $diferencias,
        public readonly ?DateTimeImmutable $inicioVigencia,
        public readonly ?string $inicioLocal,
        public readonly array $efecto,
        public readonly array $errores,
    ) {}
}
