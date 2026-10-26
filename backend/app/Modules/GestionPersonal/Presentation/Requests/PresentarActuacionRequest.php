<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

/**
 * Presentación de una novedad, una ausencia o un movimiento. `modo` fija los campos admitidos:
 * `novedad` (Colaborador, sin `colaborador_id`), `novedad_admin`, `ausencia` y `movimiento`.
 */
class PresentarActuacionRequest extends ActuacionRequest
{
    private const COMUNES = ['motivo', 'documento_ids'];

    /** @return list<string> */
    protected function campos(): array
    {
        return match ($this->route()?->defaults['modo'] ?? 'novedad_admin') {
            'novedad' => [...self::COMUNES, 'tipo', 'fecha_inicio', 'fecha_fin'],
            'ausencia' => [...self::COMUNES, 'colaborador_id', 'fecha_inicio', 'fecha_fin'],
            'movimiento' => [...self::COMUNES, 'colaborador_id', 'tipo', 'posicion_destino_id', 'fecha_efectiva'],
            default => [...self::COMUNES, 'colaborador_id', 'tipo', 'fecha_inicio', 'fecha_fin'],
        };
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'colaborador_id' => ['nullable', 'integer', 'min:1'],
            'tipo' => ['nullable', 'string', 'max:20'],
            'fecha_inicio' => ['nullable', 'string', 'max:10'],
            'fecha_fin' => ['nullable', 'string', 'max:10'],
            'fecha_efectiva' => ['nullable', 'string', 'max:10'],
            'posicion_destino_id' => ['nullable'],
            'motivo' => ['nullable', 'string', 'max:1100'],
            'documento_ids' => ['nullable', 'array', 'max:20'],
        ];
    }

    public function colaboradorId(): int
    {
        return (int) $this->input('colaborador_id', 0);
    }

    /** @return array<string, mixed> */
    public function datos(): array
    {
        return [
            'fecha_inicio' => $this->input('fecha_inicio'),
            'fecha_fin' => $this->input('fecha_fin'),
            'fecha_efectiva' => $this->input('fecha_efectiva'),
            'posicion_destino_id' => $this->input('posicion_destino_id'),
            'motivo' => $this->input('motivo'),
            'documento_ids' => (array) $this->input('documento_ids', []),
        ];
    }
}
