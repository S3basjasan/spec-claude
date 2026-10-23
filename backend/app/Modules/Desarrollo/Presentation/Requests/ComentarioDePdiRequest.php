<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

/** Devolución del colaborador: comentario obligatorio de 1 a 1000 caracteres y la versión leída. */
class ComentarioDePdiRequest extends VersionDePdiRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return parent::rules() + ['comentario' => ['required', 'string', 'max:1000']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return parent::messages() + [
            'comentario.required' => 'Escribe un comentario para devolver el plan.',
            'comentario.max' => 'El comentario no puede superar los 1000 caracteres.',
        ];
    }

    public function comentario(): string
    {
        return (string) $this->validated()['comentario'];
    }
}
