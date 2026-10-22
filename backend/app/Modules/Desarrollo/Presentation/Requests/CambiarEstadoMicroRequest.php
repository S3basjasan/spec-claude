<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoMicro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoMicroRequest extends FormRequest
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
        return ['estado' => ['required', 'string', Rule::in(['Publicado', 'Archivado'])]];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado.required' => 'Indica el estado nuevo.',
            'estado.in' => 'El estado debe ser Publicado o Archivado.',
        ];
    }

    public function estado(): EstadoMicro
    {
        return EstadoMicro::from((string) $this->validated('estado'));
    }
}
