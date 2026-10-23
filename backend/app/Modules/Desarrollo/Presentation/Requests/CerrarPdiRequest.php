<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use Illuminate\Validation\Rule;

/** Cierre del plan: resultado de cada compromiso, conclusión opcional y la versión leída. */
class CerrarPdiRequest extends VersionDePdiRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'resultados' => ['required', 'array', 'min:1', 'max:100'],
            'resultados.*.compromiso_id' => ['required', 'integer', 'min:1'],
            'resultados.*.resultado' => ['required', Rule::in(['Cerrada', 'Parcial', 'NoCerrada'])],
            'conclusion' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return parent::messages() + [
            'resultados.required' => 'Indica el resultado de cada compromiso.',
            'resultados.*.resultado.required' => 'Indica el resultado del compromiso.',
            'resultados.*.resultado.in' => 'El resultado del compromiso no es válido.',
            'conclusion.max' => 'La conclusión no puede superar los 1000 caracteres.',
        ];
    }

    /** @return list<array{compromiso_id: int, resultado: string}> */
    public function resultados(): array
    {
        return array_values(array_map(
            fn (array $r) => ['compromiso_id' => (int) $r['compromiso_id'], 'resultado' => (string) $r['resultado']],
            $this->validated()['resultados'],
        ));
    }

    public function conclusion(): ?string
    {
        return $this->validated()['conclusion'] ?? null;
    }
}
