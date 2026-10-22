<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Datos del paso de Selección: búsqueda de colaboradores o, con `colaborador_id`, sus opciones de puesto objetivo. */
class OpcionesAdecuacionRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:80'],
            'colaborador_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.max' => 'La búsqueda no puede superar los 80 caracteres.',
            'colaborador_id.integer' => 'El colaborador no es válido.',
        ];
    }

    public function texto(): ?string
    {
        return $this->validated()['q'] ?? null;
    }

    public function colaboradorId(): ?int
    {
        $v = $this->validated();

        return isset($v['colaborador_id']) ? (int) $v['colaborador_id'] : null;
    }
}
