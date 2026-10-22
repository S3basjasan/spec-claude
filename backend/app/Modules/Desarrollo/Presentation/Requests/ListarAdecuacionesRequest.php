<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Filtros y paginación del historial de adecuaciones. */
class ListarAdecuacionesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_desde'],
            'colaborador_id' => ['nullable', 'integer', 'min:1'],
            'puesto_objetivo_id' => ['nullable', 'integer', 'min:1'],
            'preparacion' => ['nullable', 'string', 'in:Lista,EnDesarrollo,NoLista,Incompleta'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_desde.date_format' => 'La fecha inicial no es válida.',
            'fecha_hasta.date_format' => 'La fecha final no es válida.',
            'fecha_hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
            'colaborador_id.integer' => 'El colaborador no es válido.',
            'puesto_objetivo_id.integer' => 'El puesto no es válido.',
            'preparacion.in' => 'La preparación no es válida.',
            'page.min' => 'La página debe ser 1 o mayor.',
            'per_page.between' => 'El tamaño de página debe estar entre 1 y 50.',
        ];
    }

    /** @return array{fecha_desde: string|null, fecha_hasta: string|null, colaborador_id: int|null, puesto_objetivo_id: int|null, preparacion: string|null} */
    public function filtros(): array
    {
        $v = $this->validated();

        return [
            'fecha_desde' => $v['fecha_desde'] ?? null,
            'fecha_hasta' => $v['fecha_hasta'] ?? null,
            'colaborador_id' => isset($v['colaborador_id']) ? (int) $v['colaborador_id'] : null,
            'puesto_objetivo_id' => isset($v['puesto_objetivo_id']) ? (int) $v['puesto_objetivo_id'] : null,
            'preparacion' => $v['preparacion'] ?? null,
        ];
    }

    public function pagina(): int
    {
        return (int) ($this->validated()['page'] ?? 1);
    }

    public function porPagina(): int
    {
        return (int) ($this->validated()['per_page'] ?? 15);
    }
}
