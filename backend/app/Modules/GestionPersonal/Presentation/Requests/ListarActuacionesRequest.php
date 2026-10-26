<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeActuacion;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDeActuacion;
use Illuminate\Validation\Rule;

/** Filtros del listado del Administrador y de «Mis solicitudes»; todo valor desconocido se rechaza. */
class ListarActuacionesRequest extends ActuacionRequest
{
    /** @return list<string> */
    protected function campos(): array
    {
        return ['tipo', 'estado', 'grupo', 'colaborador_id', 'desde', 'hasta', 'page', 'per_page'];
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'tipo' => ['nullable', 'string', Rule::in(TipoDeActuacion::valores())],
            'estado' => ['nullable', 'string', Rule::in(EstadoDeActuacion::valores())],
            'grupo' => ['nullable', 'string', Rule::in(['pendientes', 'resueltas', 'programados', 'aplicados', 'incidencias'])],
            'colaborador_id' => ['nullable', 'integer', 'min:1'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @return array{tipo: string|null, estado: string|null, grupo: string|null, colaborador_id: int|null, desde: string|null, hasta: string|null} */
    public function filtros(): array
    {
        $v = $this->validated();

        return [
            'tipo' => $v['tipo'] ?? null,
            'estado' => $v['estado'] ?? null,
            'grupo' => $v['grupo'] ?? null,
            'colaborador_id' => isset($v['colaborador_id']) ? (int) $v['colaborador_id'] : null,
            'desde' => $v['desde'] ?? null,
            'hasta' => $v['hasta'] ?? null,
        ];
    }

    public function pagina(): int
    {
        return (int) ($this->validated('page') ?? 1);
    }

    public function porPagina(): int
    {
        return (int) ($this->validated('per_page') ?? 20);
    }
}
