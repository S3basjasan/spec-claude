<?php

namespace App\Modules\Compensaciones\Presentation\Requests;

use App\Modules\Compensaciones\Application\DTOs\DatosCategoriaDTO;
use App\Modules\Compensaciones\Domain\ValueObjects\Icono;
use App\Modules\Compensaciones\Domain\ValueObjects\Normalizador;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crear (POST) y editar (PUT) comparten reglas; editar exige además `updated_at`, la versión leída.
 */
class GuardarCategoriaRequest extends FormRequest
{
    private const SIN_HTML = 'not_regex:/<\/?[a-z!][^>]*>/i';

    /** Claves que el cliente nunca puede enviar. */
    private const PROHIBIDAS = ['codigo', 'estado', 'orden', 'created_by', 'updated_by', 'created_at', 'id'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $recortados = [];

        foreach (['nombre', 'descripcion'] as $campo) {
            if (is_string($this->input($campo))) {
                $recortados[$campo] = trim($this->input($campo));
            }
        }

        if (is_array($this->input('indicadores'))) {
            $recortados['indicadores'] = array_map(
                fn ($texto) => is_string($texto) ? trim($texto) : $texto,
                $this->input('indicadores'),
            );
        }

        $this->merge($recortados);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $reglas = [
            'nombre' => ['required', 'string', 'min:3', 'max:100', self::SIN_HTML],
            'descripcion' => ['required', 'string', 'min:10', 'max:500', self::SIN_HTML],
            'competencia_id' => ['required', 'integer', 'min:1'],
            'puntuacion_base' => ['required', 'integer', 'between:1,1000'],
            'icono' => ['required', 'string', Rule::in(Icono::valores())],
            'indicadores' => ['required', 'array', 'min:1', 'max:5'],
            'indicadores.*' => ['required', 'string', 'min:5', 'max:250', self::SIN_HTML],
            'updated_at' => $this->isMethod('PUT') ? ['required', 'date'] : ['missing'],
        ];

        foreach (self::PROHIBIDAS as $clave) {
            $reglas[$clave] = ['missing'];
        }

        return $reglas;
    }

    /**
     * Los indicadores no pueden repetirse entre sí (comparación sin mayúsculas ni tildes).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validador) {
            $indicadores = $this->input('indicadores');

            if (! is_array($indicadores)) {
                return;
            }

            $vistos = [];

            foreach (array_values($indicadores) as $posicion => $texto) {
                if (! is_string($texto)) {
                    continue;
                }

                $clave = Normalizador::normalizar($texto);

                if (isset($vistos[$clave])) {
                    $validador->errors()->add("indicadores.$posicion", 'Los indicadores no pueden repetirse.');
                }

                $vistos[$clave] = true;
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'Este campo es obligatorio.',
            'nombre.min' => 'El nombre debe tener al menos 3 caracteres.',
            'nombre.max' => 'El nombre no puede superar los 100 caracteres.',
            'descripcion.min' => 'La descripción debe tener al menos 10 caracteres.',
            'descripcion.max' => 'La descripción no puede superar los 500 caracteres.',
            'puntuacion_base.integer' => 'La puntuación base debe ser un número entero.',
            'puntuacion_base.between' => 'La puntuación base debe estar entre 1 y 1000.',
            'icono.in' => 'El ícono seleccionado no es válido.',
            'indicadores.min' => 'La categoría debe tener al menos 1 indicador.',
            'indicadores.max' => 'La categoría no puede tener más de 5 indicadores.',
            'indicadores.*.min' => 'Cada indicador debe tener al menos 5 caracteres.',
            'indicadores.*.max' => 'Cada indicador no puede superar los 250 caracteres.',
            'not_regex' => 'El texto no puede contener etiquetas HTML.',
            'missing' => 'Este campo no se puede enviar.',
            'updated_at.date' => 'La versión leída no es válida.',
        ];
    }

    public function datos(): DatosCategoriaDTO
    {
        return new DatosCategoriaDTO(
            (string) $this->validated('nombre'),
            (string) $this->validated('descripcion'),
            (int) $this->validated('competencia_id'),
            (int) $this->validated('puntuacion_base'),
            (string) $this->validated('icono'),
            array_values($this->validated('indicadores')),
        );
    }

    public function versionLeida(): string
    {
        return (string) $this->validated('updated_at');
    }
}
