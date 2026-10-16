<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

use App\Modules\GestionPersonal\Domain\ValueObjects\TipoIdentificacion;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Alta (POST) y corrección (PATCH) de una ficha. Solo admite los campos editables declarados.
 */
class GuardarColaboradorRequest extends FormRequest
{
    private const ETIQUETAS_LIMITE = [
        'numero_identificacion' => 50, 'nombres' => 150, 'apellidos' => 150, 'nacionalidad' => 100,
        'genero' => 80, 'estado_civil' => 80, 'direccion' => 500, 'telefono' => 30, 'correo_contacto' => 254,
        'emergencia_nombre' => 150, 'emergencia_relacion' => 100, 'emergencia_telefono' => 30,
    ];

    public function authorize(): bool
    {
        return true;
    }

    private function esAlta(): bool
    {
        return $this->isMethod('post');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $obligatorio = $this->esAlta() ? ['required'] : ['sometimes', 'required'];
        $telefono = ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()\-]+$/'];

        $reglas = [
            'tipo_identificacion' => [...$obligatorio, 'string', Rule::in(TipoIdentificacion::valores())],
            'numero_identificacion' => [...$obligatorio, 'string', 'max:50'],
            'nombres' => [...$obligatorio, 'string', 'max:150'],
            'apellidos' => [...$obligatorio, 'string', 'max:150'],
            'fecha_nacimiento' => ['nullable', 'date_format:Y-m-d'],
            'nacionalidad' => ['nullable', 'string', 'max:100'],
            'genero' => ['nullable', 'string', 'max:80'],
            'estado_civil' => ['nullable', 'string', 'max:80'],
            'numero_hijos' => ['nullable', 'integer', 'min:0'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'telefono' => $telefono,
            'correo_contacto' => ['nullable', 'string', 'max:254', 'email:rfc'],
            'emergencia_nombre' => ['nullable', 'string', 'max:150'],
            'emergencia_relacion' => ['nullable', 'string', 'max:100'],
            'emergencia_telefono' => $telefono,
        ];

        if (! $this->esAlta()) {
            $reglas['version'] = ['required', 'integer', 'min:1'];
        }

        return $reglas;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $mensajes = [];
        foreach (array_keys($this->rules()) as $campo) {
            $mensajes["$campo.required"] = 'Este campo es obligatorio.';
            $mensajes["$campo.string"] = 'El valor no es válido.';
            $mensajes["$campo.in"] = 'El valor seleccionado no es válido.';
        }
        foreach (self::ETIQUETAS_LIMITE as $campo => $limite) {
            $mensajes["$campo.max"] = "No puede superar los $limite caracteres.";
        }
        $mensajes['fecha_nacimiento.date_format'] = 'La fecha de nacimiento debe tener el formato AAAA-MM-DD.';
        $mensajes['numero_hijos.integer'] = 'El número de hijos debe ser un entero.';
        $mensajes['numero_hijos.min'] = 'El número de hijos debe ser un entero igual o mayor que cero.';
        $mensajes['telefono.regex'] = 'El teléfono solo admite dígitos, espacios, guiones, paréntesis y un + inicial.';
        $mensajes['emergencia_telefono.regex'] = 'El teléfono solo admite dígitos, espacios, guiones, paréntesis y un + inicial.';
        $mensajes['correo_contacto.email'] = 'El correo de contacto no tiene un formato válido.';
        $mensajes['version.required'] = 'Falta la versión de la ficha.';
        $mensajes['version.integer'] = 'La versión no es válida.';

        return $mensajes;
    }

    public function withValidator(Validator $validador): void
    {
        $validador->after(function (Validator $validador): void {
            $permitidos = array_keys($this->rules());
            foreach (array_keys($this->all()) as $campo) {
                if (! in_array($campo, $permitidos, true)) {
                    $validador->errors()->add((string) $campo, 'Este campo no se puede asignar.');
                }
            }
        });
    }

    protected function failedValidation(Validator $validador): void
    {
        $permitidos = array_keys($this->rules());
        $prohibido = collect(array_keys($validador->errors()->toArray()))
            ->contains(fn (string $campo) => ! in_array($campo, $permitidos, true));

        throw new HttpResponseException(response()->json([
            'message' => 'Los datos no son válidos.',
            'code' => $prohibido ? 'CAMPO_NO_PERMITIDO' : 'VALIDACION',
            'errors' => $validador->errors()->toArray(),
        ], 422));
    }

    /**
     * Campos editables enviados (en PATCH, solo los presentes).
     *
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        $validados = $this->validated();
        unset($validados['version']);

        return $validados;
    }
}
