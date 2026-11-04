<?php

namespace App\Modules\Compensaciones\Presentation\Requests;

use App\Modules\Compensaciones\Application\DTOs\CambioSolicitadoDTO;
use App\Modules\Compensaciones\Domain\ValueObjects\ClaveDeParametro;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cuerpo: `cambios` = lista de `{clave, valor, version}`. El valor debe ser un entero dentro del rango de su clave.
 */
class GuardarParametrosRequest extends FormRequest
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
            'cambios' => ['required', 'array', 'min:1', 'max:'.count(ClaveDeParametro::cases())],
            'cambios.*.clave' => ['required', 'string', Rule::in(ClaveDeParametro::valores()), 'distinct'],
            'cambios.*.valor' => ['required', 'integer:strict'],
            'cambios.*.version' => ['required', 'integer:strict', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cambios.*.valor.required' => 'El valor es obligatorio.',
            'cambios.*.valor.integer' => 'Debe ser un número entero.',
            'cambios.*.clave.in' => 'El parámetro no existe.',
            'cambios.*.clave.distinct' => 'El parámetro está repetido.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validador): void {
                foreach ((array) $this->input('cambios', []) as $indice => $cambio) {
                    $clave = is_array($cambio) && is_string($cambio['clave'] ?? null) ? ClaveDeParametro::tryFrom($cambio['clave']) : null;
                    $valor = is_array($cambio) ? ($cambio['valor'] ?? null) : null;

                    if ($clave !== null && is_int($valor) && ! $clave->admite($valor)) {
                        $validador->errors()->add(
                            "cambios.$indice.valor",
                            "Debe ser un número entero entre {$clave->minimo()} y {$clave->maximo()}.",
                        );
                    }
                }
            },
        ];
    }

    /** @return array<int, CambioSolicitadoDTO> */
    public function cambios(): array
    {
        return array_map(
            fn (array $c) => new CambioSolicitadoDTO(ClaveDeParametro::from($c['clave']), (int) $c['valor'], (int) $c['version']),
            array_values($this->validated('cambios')),
        );
    }
}
