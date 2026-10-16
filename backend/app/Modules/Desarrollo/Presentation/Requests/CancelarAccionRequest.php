<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelarAccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El motivo es obligatorio, pero su ausencia la rechaza el caso de uso con un código de negocio estable.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return ['motivo' => ['nullable', 'string', 'max:500']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['motivo.max' => 'El motivo no puede superar 500 caracteres.'];
    }

    public function motivo(): ?string
    {
        return $this->validated()['motivo'] ?? null;
    }
}
