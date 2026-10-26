<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base de las peticiones de movimientos y novedades: solo se aceptan los campos declarados (cualquier otro, como
 * tenant_id, estado o politica_version_id, se rechaza con CAMPO_NO_PERMITIDO) y los fallos se responden en JSON.
 * El contenido de negocio se valida en los casos de uso.
 */
abstract class ActuacionRequest extends FormRequest
{
    /** @return list<string> */
    abstract protected function campos(): array;

    public function authorize(): bool
    {
        return true;
    }

    public function withValidator(Validator $validador): void
    {
        $validador->after(function (Validator $validador): void {
            foreach (array_keys($this->all()) as $campo) {
                if (! in_array($campo, $this->campos(), true)) {
                    $validador->errors()->add((string) $campo, 'Este campo no se puede asignar.');
                }
            }
        });
    }

    protected function failedValidation(Validator $validador): void
    {
        $prohibido = collect(array_keys($validador->errors()->toArray()))
            ->contains(fn (string $campo) => ! in_array($campo, $this->campos(), true));

        throw new HttpResponseException(response()->json([
            'message' => 'Los datos no son válidos.',
            'code' => $prohibido ? 'CAMPO_NO_PERMITIDO' : 'VALIDACION',
            'errors' => $validador->errors()->toArray(),
        ], 422));
    }

    public function clave(): ?string
    {
        $clave = $this->header('Idempotency-Key');

        return is_string($clave) ? $clave : null;
    }
}
