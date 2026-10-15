<?php

namespace App\Modules\Estructura\Presentation\Requests;

use App\Modules\Estructura\Domain\ValueObjects\EstadoUnidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoUnidadRequest extends FormRequest
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
        return ['estado' => ['required', 'string', Rule::in(array_map(fn (EstadoUnidad $e) => $e->value, EstadoUnidad::cases()))]];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado.required' => 'Indica el estado.',
            'estado.in' => 'El estado debe ser Activa o Inactiva.',
        ];
    }

    public function estado(): EstadoUnidad
    {
        return EstadoUnidad::from((string) $this->validated('estado'));
    }
}
