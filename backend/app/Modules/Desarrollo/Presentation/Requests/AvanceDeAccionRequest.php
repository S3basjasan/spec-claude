<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeAccionPdi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Nuevo estado de una acción sin vínculo. */
class AvanceDeAccionRequest extends FormRequest
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
        return ['estado' => ['required', Rule::in(EstadoDeAccionPdi::valores())]];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['estado.required' => 'Elige el estado de la acción.', 'estado.in' => 'El estado de la acción no es válido.'];
    }

    public function estado(): EstadoDeAccionPdi
    {
        return EstadoDeAccionPdi::from($this->validated()['estado']);
    }
}
