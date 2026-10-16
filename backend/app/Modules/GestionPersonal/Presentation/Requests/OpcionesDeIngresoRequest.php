<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpcionesDeIngresoRequest extends FormRequest
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
            'fecha' => ['required', 'string', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha.required' => 'La fecha es obligatoria.',
            'fecha.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
            'q.max' => 'La búsqueda no puede superar los 50 caracteres.',
            'per_page.between' => 'El tamaño de página debe estar entre 1 y 50.',
        ];
    }
}
