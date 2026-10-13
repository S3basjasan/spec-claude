<?php

namespace App\Modules\Estructura\Presentation\Requests;

use App\Modules\Estructura\Domain\ValueObjects\AsignacionDeCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\DatosPuesto;
use App\Modules\Estructura\Domain\ValueObjects\FuncionPuesto;
use App\Modules\Estructura\Domain\ValueObjects\RequisitoPuesto;
use App\Modules\Estructura\Domain\ValueObjects\TipoFuncion;
use App\Modules\Estructura\Domain\ValueObjects\TipoRequisito;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Crear (POST) y actualizar (PUT) un puesto. Valida la forma, las longitudes, los mínimos, los máximos y las
 * repeticiones; que las competencias existan, estén activas y tengan el grado lo comprueba el caso de uso.
 */
class GuardarPuestoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Máximo de elementos por lista (configurable; 20 por defecto). */
    public function maximo(): int
    {
        return (int) config('estructura.puestos.max_elementos_por_lista', 20);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $max = $this->maximo();

        return [
            'nombre' => ['required', 'string', 'max:50'],
            'descripcion' => ['required', 'string', 'max:200'],
            'mision' => ['required', 'string', 'max:200'],
            'funciones' => ['required', 'array', 'min:1', "max:$max"],
            'funciones.*' => ['array'],
            'funciones.*.descripcion' => ['required', 'string', 'max:500'],
            'funciones.*.tipo' => ['required', Rule::in(array_map(fn (TipoFuncion $t) => $t->value, TipoFuncion::cases()))],
            'requisitos' => ['required', 'array', 'min:1', "max:$max"],
            'requisitos.*' => ['array'],
            'requisitos.*.tipo' => ['required', Rule::in(array_map(fn (TipoRequisito $t) => $t->value, TipoRequisito::cases()))],
            'requisitos.*.descripcion' => ['required', 'string', 'max:500'],
            'competencias' => ['required', 'array', 'min:1', "max:$max"],
            'competencias.*' => ['array'],
            'competencias.*.competencia_id' => ['required', 'integer', 'min:1'],
            'competencias.*.grado_requerido' => ['required', Rule::in(['A', 'B', 'C', 'D'])],
            'version' => $this->isMethod('PUT') ? ['required', 'integer', 'min:1'] : ['nullable'],
            'codigo' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $max = $this->maximo();

        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.string' => 'El nombre no es válido.',
            'nombre.max' => 'El nombre no puede superar los 50 caracteres.',
            'descripcion.required' => 'La descripción es obligatoria.',
            'descripcion.string' => 'La descripción no es válida.',
            'descripcion.max' => 'La descripción no puede superar los 200 caracteres.',
            'mision.required' => 'La misión es obligatoria.',
            'mision.string' => 'La misión no es válida.',
            'mision.max' => 'La misión no puede superar los 200 caracteres.',
            'funciones.required' => 'Agrega al menos una función.',
            'funciones.array' => 'Las funciones deben ser una lista.',
            'funciones.min' => 'Agrega al menos una función.',
            'funciones.max' => "Un puesto admite como máximo $max funciones.",
            'funciones.*.array' => 'La función no es válida.',
            'funciones.*.descripcion.required' => 'La descripción de la función es obligatoria.',
            'funciones.*.descripcion.string' => 'La descripción de la función no es válida.',
            'funciones.*.descripcion.max' => 'La descripción de la función no puede superar los 500 caracteres.',
            'funciones.*.tipo.required' => 'Elige el tipo de la función.',
            'funciones.*.tipo.in' => 'El tipo de la función debe ser Esencial o Complementaria.',
            'requisitos.required' => 'Agrega al menos un requisito.',
            'requisitos.array' => 'Los requisitos deben ser una lista.',
            'requisitos.min' => 'Agrega al menos un requisito.',
            'requisitos.max' => "Un puesto admite como máximo $max requisitos.",
            'requisitos.*.array' => 'El requisito no es válido.',
            'requisitos.*.tipo.required' => 'Elige el tipo del requisito.',
            'requisitos.*.tipo.in' => 'El tipo del requisito debe ser Formación, Experiencia o Capacitación.',
            'requisitos.*.descripcion.required' => 'La descripción del requisito es obligatoria.',
            'requisitos.*.descripcion.string' => 'La descripción del requisito no es válida.',
            'requisitos.*.descripcion.max' => 'La descripción del requisito no puede superar los 500 caracteres.',
            'competencias.required' => 'Agrega al menos una competencia.',
            'competencias.array' => 'Las competencias deben ser una lista.',
            'competencias.min' => 'Agrega al menos una competencia.',
            'competencias.max' => "Un puesto admite como máximo $max competencias.",
            'competencias.*.array' => 'La competencia no es válida.',
            'competencias.*.competencia_id.required' => 'Elige una competencia.',
            'competencias.*.competencia_id.integer' => 'Elige una competencia válida.',
            'competencias.*.competencia_id.min' => 'Elige una competencia válida.',
            'competencias.*.grado_requerido.required' => 'Elige el grado requerido.',
            'competencias.*.grado_requerido.in' => 'El grado requerido debe ser A, B, C o D.',
            'version.required' => 'Falta la versión del puesto. Recarga e inténtalo de nuevo.',
            'version.integer' => 'La versión del puesto no es válida.',
            'version.min' => 'La versión del puesto no es válida.',
            'codigo.string' => 'El código no es válido.',
            'codigo.max' => 'El código no es válido.',
        ];
    }

    /**
     * Reglas que dependen del conjunto: al menos una función esencial, un requisito de formación y uno de
     * experiencia, y sin repeticiones (sin distinguir mayúsculas ni espacios al inicio o al final).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validarFunciones($validator);
            $this->validarRequisitos($validator);
            $this->validarCompetencias($validator);
        });
    }

    private function validarFunciones(Validator $validator): void
    {
        $funciones = $this->input('funciones');
        if (! is_array($funciones) || $funciones === []) {
            return;
        }

        $hayEsencial = false;
        $vistas = [];
        foreach (array_values($funciones) as $indice => $funcion) {
            if (! is_array($funcion)) {
                continue;
            }

            $hayEsencial = $hayEsencial || ($funcion['tipo'] ?? null) === TipoFuncion::Esencial->value;

            $descripcion = $funcion['descripcion'] ?? null;
            if (! is_string($descripcion) || trim($descripcion) === '') {
                continue;
            }

            $clave = mb_strtolower(trim($descripcion));
            if (isset($vistas[$clave])) {
                $validator->errors()->add("funciones.$indice.descripcion", 'Esta función está repetida.');
            }
            $vistas[$clave] = true;
        }

        if (! $hayEsencial && ! $validator->errors()->has('funciones')) {
            $validator->errors()->add('funciones', 'Incluye al menos una función esencial.');
        }
    }

    private function validarRequisitos(Validator $validator): void
    {
        $requisitos = $this->input('requisitos');
        if (! is_array($requisitos) || $requisitos === []) {
            return;
        }

        $tipos = [];
        $vistos = [];
        foreach (array_values($requisitos) as $indice => $requisito) {
            if (! is_array($requisito)) {
                continue;
            }

            $tipo = $requisito['tipo'] ?? null;
            if (is_string($tipo)) {
                $tipos[$tipo] = true;
            }

            $descripcion = $requisito['descripcion'] ?? null;
            if (! is_string($tipo) || ! is_string($descripcion) || trim($descripcion) === '') {
                continue;
            }

            $clave = $tipo.'|'.mb_strtolower(trim($descripcion));
            if (isset($vistos[$clave])) {
                $validator->errors()->add("requisitos.$indice.descripcion", 'Este requisito está repetido en su tipo.');
            }
            $vistos[$clave] = true;
        }

        if ($validator->errors()->has('requisitos')) {
            return;
        }

        if (! isset($tipos[TipoRequisito::Formacion->value])) {
            $validator->errors()->add('requisitos', 'Incluye al menos un requisito de formación.');
        } elseif (! isset($tipos[TipoRequisito::Experiencia->value])) {
            $validator->errors()->add('requisitos', 'Incluye al menos un requisito de experiencia.');
        }
    }

    private function validarCompetencias(Validator $validator): void
    {
        $competencias = $this->input('competencias');
        if (! is_array($competencias)) {
            return;
        }

        $vistas = [];
        foreach (array_values($competencias) as $indice => $competencia) {
            $id = is_array($competencia) ? ($competencia['competencia_id'] ?? null) : null;
            if (! is_numeric($id)) {
                continue;
            }

            if (isset($vistas[(int) $id])) {
                $validator->errors()->add("competencias.$indice.competencia_id", 'Esta competencia ya está en el puesto.');
            }
            $vistas[(int) $id] = true;
        }
    }

    /**
     * El contenido del puesto con los textos recortados y las listas en el orden recibido.
     */
    public function datos(): DatosPuesto
    {
        $funciones = [];
        foreach (array_values((array) $this->validated('funciones')) as $indice => $funcion) {
            $funciones[] = new FuncionPuesto($indice + 1, trim((string) $funcion['descripcion']), TipoFuncion::from($funcion['tipo']));
        }

        $requisitos = array_map(
            fn (array $requisito) => new RequisitoPuesto(TipoRequisito::from($requisito['tipo']), trim((string) $requisito['descripcion'])),
            array_values((array) $this->validated('requisitos')),
        );

        $competencias = array_map(
            fn (array $competencia) => new AsignacionDeCompetencia((int) $competencia['competencia_id'], (string) $competencia['grado_requerido']),
            array_values((array) $this->validated('competencias')),
        );

        return new DatosPuesto(
            trim((string) $this->validated('nombre')),
            trim((string) $this->validated('descripcion')),
            trim((string) $this->validated('mision')),
            $funciones,
            $requisitos,
            $competencias,
        );
    }
}
