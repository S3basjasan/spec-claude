<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Acción del Plan de Formación a vincular (nula para desvincular) y, en un Borrador, la versión leída. */
class VinculoDeAccionRequest extends FormRequest
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
            'plan_accion_id' => ['present', 'nullable', 'integer', 'min:1'],
            'version' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['plan_accion_id.present' => 'Indica la acción del Plan de Formación o null para desvincular.'];
    }

    public function planAccionId(): ?int
    {
        $v = $this->validated();

        return isset($v['plan_accion_id']) ? (int) $v['plan_accion_id'] : null;
    }

    public function version(): ?int
    {
        $v = $this->validated();

        return isset($v['version']) ? (int) $v['version'] : null;
    }
}
