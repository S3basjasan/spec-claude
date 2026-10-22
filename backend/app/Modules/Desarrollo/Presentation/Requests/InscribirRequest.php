<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Inscribe a un colaborador en una ruta, con nivel actual (opcional) y nivel objetivo (opcional). */
class InscribirRequest extends FormRequest
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
            'ruta_id' => ['required', 'integer', 'min:1'],
            'colaborador_id' => ['required', 'integer', 'min:1'],
            'nivel_actual_id' => ['nullable', 'integer', 'min:1'],
            'nivel_objetivo_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ruta_id.required' => 'Falta la ruta.',
            'ruta_id.integer' => 'La ruta no es válida.',
            'colaborador_id.required' => 'Elige un colaborador.',
            'colaborador_id.integer' => 'El colaborador no es válido.',
            'nivel_actual_id.integer' => 'El nivel actual no es válido.',
            'nivel_objetivo_id.integer' => 'El nivel objetivo no es válido.',
        ];
    }

    public function rutaId(): int
    {
        return (int) $this->validated()['ruta_id'];
    }

    public function colaboradorId(): int
    {
        return (int) $this->validated()['colaborador_id'];
    }

    public function nivelActualId(): ?int
    {
        $v = $this->validated()['nivel_actual_id'] ?? null;

        return $v === null ? null : (int) $v;
    }

    public function nivelObjetivoId(): ?int
    {
        $v = $this->validated()['nivel_objetivo_id'] ?? null;

        return $v === null ? null : (int) $v;
    }
}
