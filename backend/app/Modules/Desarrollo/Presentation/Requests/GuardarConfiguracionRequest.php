<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use App\Modules\Desarrollo\Domain\ValueObjects\DatosConfiguracion;
use Illuminate\Foundation\Http\FormRequest;

class GuardarConfiguracionRequest extends FormRequest
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
            'area_formativa_id' => ['present', 'nullable', 'integer', 'min:1'],
            'curso_ids' => ['present', 'array'],
            'curso_ids.*' => ['integer', 'min:1'],
            'microcontenido_ids' => ['present', 'array'],
            'microcontenido_ids.*' => ['integer', 'min:1'],
            'version' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'area_formativa_id.present' => 'Indica el área formativa o déjala vacía.',
            'area_formativa_id.integer' => 'El área formativa no es válida.',
            'curso_ids.present' => 'Envía la lista de cursos (puede estar vacía).',
            'curso_ids.array' => 'La lista de cursos no es válida.',
            'curso_ids.*.integer' => 'El curso no es válido.',
            'microcontenido_ids.present' => 'Envía la lista de micro-contenidos (puede estar vacía).',
            'microcontenido_ids.array' => 'La lista de micro-contenidos no es válida.',
            'microcontenido_ids.*.integer' => 'El micro-contenido no es válido.',
            'version.required' => 'Falta la versión de la configuración.',
            'version.integer' => 'La versión no es válida.',
        ];
    }

    public function datos(): DatosConfiguracion
    {
        $area = $this->validated('area_formativa_id');

        return new DatosConfiguracion(
            $area === null ? null : (int) $area,
            array_map('intval', array_values($this->validated('curso_ids'))),
            array_map('intval', array_values($this->validated('microcontenido_ids'))),
        );
    }
}
