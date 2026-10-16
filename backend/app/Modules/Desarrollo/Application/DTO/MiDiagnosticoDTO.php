<?php

namespace App\Modules\Desarrollo\Application\DTO;

/** «Mi diagnóstico»: las brechas de la DNF más reciente del colaborador autenticado. */
final class MiDiagnosticoDTO
{
    /**
     * @param  array{id: int, codigo: string, fecha: string}  $dnf
     * @param  array{codigo: string, nombre: string}|null  $puesto
     * @param  array{con_brecha: int, cumplen: int, sin_evaluacion: int}  $resumen
     * @param  list<BrechaDTO>  $prioritarias
     * @param  list<BrechaDTO>  $brechas
     * @param  list<array{competencia_id: int, nombre: string, nivel_actual: string|null, estado: string}>  $cumplen
     * @param  array<int, bool>  $tieneOferta  competencia_id => tiene al menos un curso o micro-contenido disponible
     */
    public function __construct(
        public readonly array $dnf,
        public readonly ?array $puesto,
        public readonly array $resumen,
        public readonly array $prioritarias,
        public readonly array $brechas,
        public readonly array $cumplen,
        public readonly array $tieneOferta,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'dnf' => $this->dnf,
            'puesto' => $this->puesto,
            'resumen' => $this->resumen,
            'prioritarias' => array_map(fn (BrechaDTO $b) => $b->toArray(), $this->prioritarias),
            'brechas' => array_map(fn (BrechaDTO $b) => $b->toArray(), $this->brechas),
            'cumplen' => $this->cumplen,
            'tiene_oferta' => (object) $this->tieneOferta,
        ];
    }
}
