<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** «Mi adecuación»: solo admite elegir el puesto objetivo; la identidad sale siempre de la sesión. */
class MiAdecuacionRequest extends FormRequest
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
        return ['puesto_objetivo_id' => ['nullable', 'integer', 'min:1']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['puesto_objetivo_id.integer' => 'El puesto objetivo no es válido.'];
    }

    public function puestoObjetivoId(): ?int
    {
        $v = $this->validated();

        return isset($v['puesto_objetivo_id']) ? (int) $v['puesto_objetivo_id'] : null;
    }
}
