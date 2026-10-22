<?php

namespace App\Modules\Estructura\Presentation\Requests;

use App\Modules\Estructura\Application\DTO\DatosCompetenciaDTO;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cuerpo de la creación (POST, con comportamientos y preguntas) y de la edición de la competencia y sus grados
 * (PUT, con `version`). `codigo` y `estado` no se aceptan en ningún caso.
 */
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
            'tipo_grado_id' => ['required', 'integer'],
            'categorias' => ['nullable', 'array', 'size:4'],
            'categorias.*' => ['required', 'string', 'max:20'],
            'grados' => ['required', 'array', 'size:4'],
            'grados.*' => ['required', 'string', 'max:500'],
            'version' => $this->isMethod('PUT') ? ['required', 'integer', 'min:1'] : ['nullable'],
            'codigo' => ['prohibited'],
            'estado' => ['prohibited'],
        ];

        if ($this->isMethod('POST')) {
            $reglas['comportamientos'] = ['required', 'array', 'size:4'];
            $reglas['comportamientos.*'] = ['required', 'array', 'size:4'];
            $reglas['comportamientos.*.*'] = ['required', 'string', 'max:300'];
            $reglas['preguntas'] = ['required', 'array', 'size:4'];
            $reglas['preguntas.*'] = ['required', 'string', 'max:500'];
        }

        return $reglas;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Ingresa el nombre.',
            'nombre.string' => 'Ingresa el nombre.',
            'nombre.max' => 'El nombre no puede superar los 50 caracteres.',
            'descripcion.required' => 'Ingresa la descripción.',
            'descripcion.string' => 'Ingresa la descripción.',
            'descripcion.max' => 'La descripción no puede superar los 500 caracteres.',
            'tipo.required' => 'Selecciona el tipo.',
            'tipo.string' => 'Selecciona el tipo.',
            'tipo.in' => 'Selecciona el tipo.',
            'tipo_grado_id.required' => 'Selecciona la escala de los grados.',
            'tipo_grado_id.integer' => 'Selecciona la escala de los grados.',
            'categorias.array' => 'Escribe las cuatro categorías, sin repetir nombres.',
            'categorias.size' => 'Escribe las cuatro categorías, sin repetir nombres.',
            'categorias.*.required' => 'Escribe las cuatro categorías, sin repetir nombres.',
            'categorias.*.string' => 'Escribe las cuatro categorías, sin repetir nombres.',
            'categorias.*.max' => 'Un nombre de categoría no puede superar los 20 caracteres.',
            'grados.required' => 'Completa los cuatro grados.',
            'grados.array' => 'Completa los cuatro grados.',
            'grados.size' => 'Completa los cuatro grados.',
            'grados.*.required' => 'Completa los cuatro grados.',
            'grados.*.string' => 'Completa los cuatro grados.',
            'grados.*.max' => 'La descripción de un grado no puede superar los 500 caracteres.',
            'comportamientos.required' => 'Completa los cuatro comportamientos de cada grado.',
            'comportamientos.array' => 'Completa los cuatro comportamientos de cada grado.',
            'comportamientos.size' => 'Completa los cuatro comportamientos de cada grado.',
            'comportamientos.*.required' => 'Completa los cuatro comportamientos de cada grado.',
            'comportamientos.*.array' => 'Completa los cuatro comportamientos de cada grado.',
            'comportamientos.*.size' => 'Completa los cuatro comportamientos de cada grado.',
            'comportamientos.*.*.required' => 'Completa los cuatro comportamientos de cada grado.',
            'comportamientos.*.*.string' => 'Completa los cuatro comportamientos de cada grado.',
            'comportamientos.*.*.max' => 'Un comportamiento no puede superar los 300 caracteres.',
            'preguntas.required' => 'Completa las cuatro preguntas.',
            'preguntas.array' => 'Completa las cuatro preguntas.',
            'preguntas.size' => 'Completa las cuatro preguntas.',
            'preguntas.*.required' => 'Completa las cuatro preguntas.',
            'preguntas.*.string' => 'Completa las cuatro preguntas.',
            'preguntas.*.max' => 'Una pregunta no puede superar los 500 caracteres.',
            'version.required' => 'Falta la versión de la competencia. Recarga e inténtalo de nuevo.',
            'version.integer' => 'La versión de la competencia no es válida.',
            'version.min' => 'La versión de la competencia no es válida.',
            'codigo.prohibited' => 'El código no se puede modificar.',
            'estado.prohibited' => 'El estado se cambia con la acción Activar o Desactivar.',
        ];
    }

    public function datos(): DatosCompetenciaDTO
    {
        $validados = $this->validated();

        return new DatosCompetenciaDTO(
            (string) $validados['nombre'],
            (string) $validados['descripcion'],
            (string) $validados['tipo'],
            (int) $validados['tipo_grado_id'],
            isset($validados['categorias']) ? array_values($validados['categorias']) : null,
            array_values($validados['grados']),
            isset($validados['comportamientos'])
                ? array_map(fn (array $textos) => array_values($textos), array_values($validados['comportamientos']))
                : null,
            isset($validados['preguntas']) ? array_values($validados['preguntas']) : null,
        );
    }
}
