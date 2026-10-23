<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

/** Devolución a Borrador: motivo obligatorio de 1 a 1000 caracteres y la versión leída. */
class MotivoDePdiRequest extends VersionDePdiRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return parent::rules() + ['motivo' => ['required', 'string', 'max:1000']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return parent::messages() + [
            'motivo.required' => 'Indica el motivo para devolver el plan a Borrador.',
            'motivo.max' => 'El motivo no puede superar los 1000 caracteres.',
        ];
    }

    public function motivo(): string
    {
        return (string) $this->validated()['motivo'];
    }
}
