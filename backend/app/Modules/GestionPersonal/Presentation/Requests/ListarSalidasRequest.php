<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeEntrevista;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeSalida;
use App\Modules\GestionPersonal\Domain\ValueObjects\MotivoDeSalida;
use Illuminate\Validation\Rule;

/** Filtros del listado de expedientes; todo valor desconocido se rechaza. */
class ListarSalidasRequest extends ActuacionRequest
{
    /** @return list<string> */
    protected function campos(): array
    {
        return ['estado', 'cierre', 'motivo', 'entrevista', 'colaborador_id', 'desde', 'hasta', 'page', 'per_page'];
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'estado' => ['nullable', 'string', Rule::in(array_map(fn (EstadoDeSalida $e) => $e->value, EstadoDeSalida::cases()))],
            'cierre' => ['nullable', 'string', Rule::in(['Pendiente', 'Cerrado'])],
            'motivo' => ['nullable', 'string', Rule::in(MotivoDeSalida::valores())],
            'entrevista' => ['nullable', 'string', Rule::in(array_map(fn (EstadoDeEntrevista $e) => $e->value, EstadoDeEntrevista::cases()))],
            'colaborador_id' => ['nullable', 'integer', 'min:1'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @return array{estado: string|null, cierre: string|null, motivo: string|null, entrevista: string|null, colaborador_id: int|null, desde: string|null, hasta: string|null} */
    public function filtros(): array
    {
        $v = $this->validated();

        return [
            'estado' => $v['estado'] ?? null,
            'cierre' => $v['cierre'] ?? null,
            'motivo' => $v['motivo'] ?? null,
            'entrevista' => $v['entrevista'] ?? null,
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
