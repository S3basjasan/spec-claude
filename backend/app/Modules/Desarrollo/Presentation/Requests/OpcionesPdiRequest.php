<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Texto de búsqueda de colaboradores o colaborador del que se piden las opciones. */
class OpcionesPdiRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:100'],
            'colaborador_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function texto(): ?string
    {
        $v = $this->validated();

        return isset($v['q']) && trim($v['q']) !== '' ? trim($v['q']) : null;
    }

    public function colaboradorId(): ?int
    {
        $v = $this->validated();

        return isset($v['colaborador_id']) ? (int) $v['colaborador_id'] : null;
    }
}
