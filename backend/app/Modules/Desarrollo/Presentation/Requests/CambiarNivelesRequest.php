<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Cambia el nivel actual y el nivel objetivo de una inscripción (ambos se envían; nulo deja el nivel sin definir). */
class CambiarNivelesRequest extends FormRequest
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
            'nivel_actual_id' => ['present', 'nullable', 'integer', 'min:1'],
            'nivel_objetivo_id' => ['present', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nivel_actual_id.present' => 'Falta el nivel actual (puede ir vacío).',
            'nivel_actual_id.integer' => 'El nivel actual no es válido.',
            'nivel_objetivo_id.present' => 'Falta el nivel objetivo (puede ir vacío).',
            'nivel_objetivo_id.integer' => 'El nivel objetivo no es válido.',
        ];
    }

    public function nivelActualId(): ?int
    {
        $v = $this->validated()['nivel_actual_id'];

        return $v === null ? null : (int) $v;
    }

    public function nivelObjetivoId(): ?int
    {
        $v = $this->validated()['nivel_objetivo_id'];

        return $v === null ? null : (int) $v;
    }
}
