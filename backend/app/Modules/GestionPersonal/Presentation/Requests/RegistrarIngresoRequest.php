<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Alta de un ingreso: solo fecha, contrato y posición. Cualquier otro campo (fecha_salida, estado, salario,
 * tenant_id…) se rechaza con CAMPO_NO_PERMITIDO. El contrato y la posición se validan en el caso de uso.
 */
class RegistrarIngresoRequest extends FormRequest
{
    private const CAMPOS = ['fecha_ingreso', 'tipo_contrato', 'posicion_id'];

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
            'fecha_ingreso' => ['nullable', 'string'],
            'tipo_contrato' => ['nullable', 'string', 'max:80'],
            'posicion_id' => ['nullable'],
        ];
    }

    public function withValidator(Validator $validador): void
    {
        $validador->after(function (Validator $validador): void {
            foreach (array_keys($this->all()) as $campo) {
                if (! in_array($campo, self::CAMPOS, true)) {
                    $validador->errors()->add((string) $campo, 'Este campo no se puede asignar.');
                }
            }
        });
    }

    protected function failedValidation(Validator $validador): void
    {
        $prohibido = collect(array_keys($validador->errors()->toArray()))
            ->contains(fn (string $campo) => ! in_array($campo, self::CAMPOS, true));

        throw new HttpResponseException(response()->json([
            'message' => 'Los datos no son válidos.',
            'code' => $prohibido ? 'CAMPO_NO_PERMITIDO' : 'VALIDACION',
            'errors' => $validador->errors()->toArray(),
        ], 422));
    }

    /**
     * @return array{fecha_ingreso: mixed, tipo_contrato: mixed, posicion_id: mixed}
     */
    public function datos(): array
    {
        return [
            'fecha_ingreso' => $this->input('fecha_ingreso'),
            'tipo_contrato' => $this->input('tipo_contrato'),
            'posicion_id' => $this->input('posicion_id'),
        ];
    }
}
