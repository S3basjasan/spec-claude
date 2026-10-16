<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use App\Modules\Desarrollo\Domain\ValueObjects\FiltroDnf;
use App\Modules\Desarrollo\Domain\ValueObjects\PrioridadBrecha;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarDnfRequest extends FormRequest
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
            'fecha_hasta' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('fecha_desde'), ['after_or_equal:fecha_desde'])],
            'colaborador_id' => ['nullable', 'integer', 'min:1'],
            'departamento_id' => ['nullable', 'integer', 'min:1'],
            'prioridad' => ['nullable', Rule::enum(PrioridadBrecha::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_desde.date_format' => 'La fecha inicial debe tener el formato AAAA-MM-DD.',
            'fecha_hasta.date_format' => 'La fecha final debe tener el formato AAAA-MM-DD.',
            'fecha_hasta.after_or_equal' => 'La fecha inicial no puede ser posterior a la final.',
            'colaborador_id.integer' => 'El colaborador no es válido.',
            'colaborador_id.min' => 'El colaborador no es válido.',
            'departamento_id.integer' => 'El departamento no es válido.',
            'departamento_id.min' => 'El departamento no es válido.',
            'prioridad.Illuminate\Validation\Rules\Enum' => 'La prioridad debe ser Critica, Alta, Media o Baja.',
            'page.integer' => 'La página debe ser un número entero.',
            'page.min' => 'La página debe ser 1 o mayor.',
            'per_page.integer' => 'El tamaño de página debe ser un número entero.',
            'per_page.between' => 'El tamaño de página debe estar entre 1 y 100.',
        ];
    }

    public function filtro(): FiltroDnf
    {
        $datos = $this->validated();

        return new FiltroDnf(
            $datos['fecha_desde'] ?? null,
            $datos['fecha_hasta'] ?? null,
            isset($datos['colaborador_id']) ? (int) $datos['colaborador_id'] : null,
            isset($datos['departamento_id']) ? (int) $datos['departamento_id'] : null,
            isset($datos['prioridad']) ? PrioridadBrecha::from($datos['prioridad']) : null,
            (int) ($datos['page'] ?? 1),
            (int) ($datos['per_page'] ?? 15),
        );
    }
}
