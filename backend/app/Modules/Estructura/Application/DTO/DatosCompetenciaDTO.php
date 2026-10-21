<?php

namespace App\Modules\Estructura\Application\DTO;

final class DatosCompetenciaDTO
{
    /**
     * @param  array<int, string>|null  $categorias  4 nombres propios de la escala Categorías o null.
     * @param  array<int, string>  $grados  4 descripciones, de la más alta a la más básica.
     * @param  array<int, array<int, string>>|null  $comportamientos  4 listas de 4 textos (solo en la creación).
     * @param  array<int, string>|null  $preguntas  4 textos (solo en la creación).
     */
    public function __construct(
        public readonly string $nombre,
        public readonly string $descripcion,
        public readonly string $tipo,
        public readonly int $tipoGradoId,
        public readonly ?array $categorias,
        public readonly array $grados,
        public readonly ?array $comportamientos = null,
        public readonly ?array $preguntas = null,
    ) {}
}
