<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Lista de acciones Finalizadas con su evaluación de impacto: filtros por plan y por «con»/«sin» evaluación. */
class ListarImpactosRequest extends FormRequest
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
            'plan_id' => ['nullable', 'integer', 'min:1'],
            'evaluado' => ['nullable', 'string', Rule::in(['con', 'sin'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['evaluado.in' => 'El filtro de evaluación debe ser «con» o «sin».', 'plan_id.integer' => 'El plan no es válido.'];
    }
}
