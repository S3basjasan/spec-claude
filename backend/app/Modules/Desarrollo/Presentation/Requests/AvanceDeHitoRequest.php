<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeHito;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Nuevo estado de un hito y sus observaciones. */
class AvanceDeHitoRequest extends FormRequest
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
            'estado' => ['required', Rule::in(['Pendiente', 'Realizado'])],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado.required' => 'Elige el estado del hito.',
            'estado.in' => 'El estado del hito no es válido.',
            'observaciones.max' => 'Las observaciones no pueden superar los 500 caracteres.',
        ];
    }

    public function estado(): EstadoDeHito
    {
        return EstadoDeHito::from($this->validated()['estado']);
    }

    public function observaciones(): ?string
    {
        return $this->validated()['observaciones'] ?? null;
    }
}
