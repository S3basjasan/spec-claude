<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoRequest extends FormRequest
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
            'estado' => ['required', Rule::enum(EstadoPlan::class)],
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado.required' => 'El estado es obligatorio.',
            'estado.Illuminate\Validation\Rules\Enum' => 'El estado debe ser Borrador, Revision, Aprobado, Ejecutando o Cerrado.',
            'motivo.max' => 'El motivo no puede superar 500 caracteres.',
        ];
    }

    public function destino(): EstadoPlan
    {
        return EstadoPlan::from($this->validated()['estado']);
    }

    public function motivo(): ?string
    {
        return $this->validated()['motivo'] ?? null;
    }
}
