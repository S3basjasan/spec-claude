<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CambiarParticipantesRequest extends FormRequest
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
            'colaborador_ids' => ['present', 'array'],
            'colaborador_ids.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'colaborador_ids.present' => 'Indica la lista de participantes.',
            'colaborador_ids.array' => 'Los participantes deben ser una lista.',
            'colaborador_ids.*.integer' => 'Algún participante no es válido.',
            'colaborador_ids.*.min' => 'Algún participante no es válido.',
        ];
    }

    /** @return list<int> */
    public function colaboradorIds(): array
    {
        return array_map('intval', $this->validated()['colaborador_ids']);
    }
}
