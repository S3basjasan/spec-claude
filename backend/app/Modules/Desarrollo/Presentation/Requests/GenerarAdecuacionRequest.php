<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

/** Datos para guardar una adecuación: el objetivo, los tiempos de cierre de las brechas y las observaciones. */
class GenerarAdecuacionRequest extends PrevisualizarAdecuacionRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'puesto_objetivo_esperado_id' => ['nullable', 'integer', 'min:1'],
            'tiempos_cierre' => ['nullable', 'array', 'max:200'],
            'tiempos_cierre.*.dimension' => ['required', 'string', 'in:Competencia,Conocimiento,Experiencia'],
            'tiempos_cierre.*.elemento_id' => ['nullable', 'integer', 'min:1'],
            'tiempos_cierre.*.meses' => ['nullable', 'integer', 'between:1,60'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return parent::messages() + [
            'tiempos_cierre.*.meses.integer' => 'El tiempo de cierre debe ser un número entero de meses.',
            'tiempos_cierre.*.meses.between' => 'El tiempo de cierre debe estar entre 1 y 60 meses.',
            'tiempos_cierre.*.dimension.in' => 'La dimensión no es válida.',
            'observaciones.max' => 'Las observaciones no pueden superar los 500 caracteres.',
        ];
    }

    /**
     * @return array{colaborador_id: int, inscripcion_id: int|null, puesto_objetivo_id: int|null, experiencia_requerida_anios: float|null, puesto_objetivo_esperado_id: int|null, tiempos_cierre: list<array{dimension: string, elemento_id: int|null, meses: int|null}>, observaciones: string|null}
     */
    public function datos(): array
    {
        $v = $this->validated();

        return [
            'colaborador_id' => $this->colaboradorId(),
            'inscripcion_id' => $this->inscripcionId(),
            'puesto_objetivo_id' => $this->puestoObjetivoId(),
            'experiencia_requerida_anios' => $this->experienciaRequerida(),
            'puesto_objetivo_esperado_id' => isset($v['puesto_objetivo_esperado_id']) ? (int) $v['puesto_objetivo_esperado_id'] : null,
            'tiempos_cierre' => array_values(array_map(fn (array $t) => [
                'dimension' => (string) $t['dimension'],
                'elemento_id' => isset($t['elemento_id']) ? (int) $t['elemento_id'] : null,
                'meses' => isset($t['meses']) ? (int) $t['meses'] : null,
            ], $v['tiempos_cierre'] ?? [])),
            'observaciones' => $v['observaciones'] ?? null,
        ];
    }
}
