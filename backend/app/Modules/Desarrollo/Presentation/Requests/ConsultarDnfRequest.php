<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConsultarDnfRequest extends FormRequest
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
        return ['colaborador_id' => ['nullable', 'integer', 'min:1']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'colaborador_id.integer' => 'El colaborador no es válido.',
            'colaborador_id.min' => 'El colaborador no es válido.',
        ];
    }

    public function colaboradorId(): ?int
    {
        $valor = $this->validated('colaborador_id');

        return $valor === null ? null : (int) $valor;
    }
}
