<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Colaborador del que se piden las acciones vinculables y texto de búsqueda opcional. */
class AccionesVinculablesRequest extends FormRequest
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
            'colaborador_id' => ['required', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['colaborador_id.required' => 'Elige un colaborador.'];
    }

    public function colaboradorId(): int
    {
        return (int) $this->validated()['colaborador_id'];
    }

    public function texto(): ?string
    {
        $v = $this->validated();

        return isset($v['q']) && trim($v['q']) !== '' ? trim($v['q']) : null;
    }
}
