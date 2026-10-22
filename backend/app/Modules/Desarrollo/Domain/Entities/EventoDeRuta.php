<?php

namespace App\Modules\Desarrollo\Domain\Entities;

/** Evento del historial de una ruta, de sus niveles o de una inscripción. Un usuario nulo es el sistema. */
final class EventoDeRuta
{
    public const TIPO_RUTA = 'Ruta';

    public const TIPO_NIVEL = 'Nivel';

    public const TIPO_INSCRIPCION = 'Inscripcion';

    /**
     * @param  array<string, mixed>|null  $anterior
     * @param  array<string, mixed>|null  $nuevo
     */
    public function __construct(
        public readonly string $tipo,
        public readonly string $accion,
        public readonly ?array $anterior,
        public readonly ?array $nuevo,
        public readonly ?int $usuarioId,
        public readonly ?string $motivo = null,
        public readonly ?int $inscripcionId = null,
    ) {}

    public function conInscripcion(int $inscripcionId): self
    {
        return new self($this->tipo, $this->accion, $this->anterior, $this->nuevo, $this->usuarioId, $this->motivo, $inscripcionId);
    }
}
