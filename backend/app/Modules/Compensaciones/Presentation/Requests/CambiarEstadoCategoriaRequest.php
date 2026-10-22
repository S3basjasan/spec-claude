<?php

namespace App\Modules\Compensaciones\Presentation\Requests;

use App\Modules\Compensaciones\Domain\ValueObjects\EstadoCategoria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoCategoriaRequest extends FormRequest
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
            'estado' => ['required', 'string', Rule::in(array_map(fn (EstadoCategoria $e) => $e->value, EstadoCategoria::cases()))],
            'updated_at' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado.required' => 'El estado es obligatorio.',
            'estado.in' => 'El estado debe ser Activo o Inactivo.',
            'updated_at.required' => 'La versión leída es obligatoria.',
            'updated_at.date' => 'La versión leída no es válida.',
        ];
    }

    public function estado(): EstadoCategoria
    {
        return EstadoCategoria::from((string) $this->validated('estado'));
    }

    public function versionLeida(): string
    {
        return (string) $this->validated('updated_at');
    }
}
