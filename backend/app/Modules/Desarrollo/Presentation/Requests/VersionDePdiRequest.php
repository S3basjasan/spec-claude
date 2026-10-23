<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Comandos sobre un plan que solo necesitan la versión leída (control optimista). */
class VersionDePdiRequest extends FormRequest
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
        return ['version' => ['required', 'integer', 'min:1']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'version.required' => 'Falta la versión del plan.',
            'version.integer' => 'La versión del plan no es válida.',
        ];
    }

    public function version(): int
    {
        return (int) $this->validated()['version'];
    }
}
