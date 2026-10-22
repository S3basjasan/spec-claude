<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use App\Modules\Desarrollo\Domain\ValueObjects\DatosMicro;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoMicro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cuerpo para crear (POST) o reemplazar (PUT) un micro-contenido. */
class GuardarMicrocontenidoRequest extends FormRequest
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
            'titulo' => ['required', 'string', 'max:120'],
            'tipo' => ['required', 'string', Rule::in(array_map(fn (TipoMicro $t) => $t->value, TipoMicro::cases()))],
            'duracion_minutos' => ['required', 'integer', 'min:1', 'max:100000'],
            'url' => ['nullable', 'string', 'max:500', 'url:http,https', 'prohibited_if:tipo,Quiz'],
            'miniatura_url' => ['nullable', 'string', 'max:500', 'url:http,https'],
            'competencia_ids' => ['nullable', 'array'],
            'competencia_ids.*' => ['integer', 'min:1', 'distinct'],
            'preguntas' => ['nullable', 'array', 'prohibited_unless:tipo,Quiz'],
            'preguntas.*.enunciado' => ['required', 'string', 'max:500'],
            'preguntas.*.opciones' => ['required', 'array'],
            'preguntas.*.opciones.*.texto' => ['required', 'string', 'max:300'],
            'preguntas.*.opciones.*.es_correcta' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'titulo.required' => 'El título es obligatorio.',
            'titulo.max' => 'El título no puede superar los 120 caracteres.',
            'tipo.required' => 'Elige el tipo de contenido.',
            'tipo.in' => 'El tipo debe ser Video, PDF, Artículo o Quiz.',
            'duracion_minutos.required' => 'La duración es obligatoria.',
            'duracion_minutos.integer' => 'La duración debe ser un número entero de minutos.',
            'duracion_minutos.min' => 'La duración debe ser mayor que cero.',
            'url.url' => 'La URL debe comenzar con http:// o https://.',
            'url.max' => 'La URL no puede superar los 500 caracteres.',
            'url.prohibited_if' => 'Un quiz no lleva URL.',
            'miniatura_url.url' => 'La miniatura debe ser una URL que comience con http:// o https://.',
            'competencia_ids.array' => 'La lista de competencias no es válida.',
            'competencia_ids.*.integer' => 'La competencia no es válida.',
            'competencia_ids.*.distinct' => 'Hay competencias repetidas.',
            'preguntas.prohibited_unless' => 'Solo un quiz lleva preguntas.',
            'preguntas.*.enunciado.required' => 'Cada pregunta necesita un enunciado.',
            'preguntas.*.opciones.required' => 'Cada pregunta necesita sus opciones.',
            'preguntas.*.opciones.*.texto.required' => 'Cada opción necesita un texto.',
            'preguntas.*.opciones.*.es_correcta.required' => 'Indica cuál opción es la correcta.',
        ];
    }

    public function datos(): DatosMicro
    {
        $preguntas = $this->validated('preguntas');

        return new DatosMicro(
            (string) $this->validated('titulo'),
            TipoMicro::from((string) $this->validated('tipo')),
            (int) $this->validated('duracion_minutos'),
            $this->filled('url') ? (string) $this->validated('url') : null,
            $this->filled('miniatura_url') ? (string) $this->validated('miniatura_url') : null,
            array_map('intval', array_values($this->validated('competencia_ids') ?? [])),
            $preguntas === null ? null : array_map(fn (array $p) => [
                'enunciado' => (string) $p['enunciado'],
                'opciones' => array_map(fn (array $o) => ['texto' => (string) $o['texto'], 'es_correcta' => (bool) $o['es_correcta']], array_values($p['opciones'])),
            ], array_values($preguntas)),
        );
    }
}
