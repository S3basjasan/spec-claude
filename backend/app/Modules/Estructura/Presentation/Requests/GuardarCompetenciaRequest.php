<?php

namespace App\Modules\Estructura\Presentation\Requests;

use App\Modules\Estructura\Application\DTO\DatosCompetenciaDTO;
use App\Modules\Estructura\Domain\ValueObjects\GradoLetra;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarCompetenciaRequest extends FormRequest
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
        $reglas = [
            'nombre' => ['required', 'string', 'max:50'],
            'descripcion' => ['required', 'string', 'max:500'],
            'tipo' => ['required', 'string', Rule::in(array_map(fn (TipoCompetencia $t) => $t->value, TipoCompetencia::cases()))],
            // Exactamente A, B, C y D: se rechazan claves extra y cada letra va una sola vez.
            'grados' => ['required', 'array:'.implode(',', GradoLetra::letras())],
            'version' => $this->isMethod('PUT') ? ['required', 'integer', 'min:1'] : ['nullable'],
        ];

        foreach (GradoLetra::letras() as $letra) {
            $reglas["grados.$letra"] = ['required', 'string', 'max:500'];
        }

        return $reglas;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $mensajes = [
            'nombre.required' => 'Ingresa el nombre de la competencia.',
            'nombre.string' => 'Ingresa el nombre de la competencia.',
            'nombre.max' => 'El nombre no puede superar los 50 caracteres.',
            'descripcion.required' => 'Ingresa la descripción de la competencia.',
            'descripcion.string' => 'Ingresa la descripción de la competencia.',
            'descripcion.max' => 'La descripción no puede superar los 500 caracteres.',
            'tipo.required' => 'Selecciona el tipo de competencia.',
            'tipo.in' => 'Selecciona un tipo válido: Cardinal o Específica.',
            'grados.required' => 'Ingresa la descripción de los cuatro grados.',
            'grados.array' => 'Los grados deben ser exactamente A, B, C y D, sin repetir.',
            'version.required' => 'Falta la versión de la competencia. Recarga e inténtalo de nuevo.',
            'version.integer' => 'La versión de la competencia no es válida.',
            'version.min' => 'La versión de la competencia no es válida.',
        ];

        foreach (GradoLetra::letras() as $letra) {
            $mensajes["grados.$letra.required"] = "Ingresa la descripción del grado $letra.";
            $mensajes["grados.$letra.string"] = "Ingresa la descripción del grado $letra.";
            $mensajes["grados.$letra.max"] = "La descripción del grado $letra no puede superar los 500 caracteres.";
        }

        return $mensajes;
    }

    public function datos(): DatosCompetenciaDTO
    {
        $grados = [];
        foreach (GradoLetra::letras() as $letra) {
            $grados[$letra] = (string) $this->validated("grados.$letra");
        }

        return new DatosCompetenciaDTO(
            (string) $this->validated('nombre'),
            (string) $this->validated('descripcion'),
            (string) $this->validated('tipo'),
            $grados,
        );
    }
}
