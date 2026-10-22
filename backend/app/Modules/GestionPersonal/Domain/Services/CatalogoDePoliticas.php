<?php

namespace App\Modules\GestionPersonal\Domain\Services;

use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;

/**
 * Catálogo de parámetros admitidos y reglas fijas de cada tipo (contracts/catalogo-de-parametros.md). Nada fuera de él
 * se admite. PHP puro.
 */
final class CatalogoDePoliticas
{
    public const DESCRIPCION = 1000;

    public const MOTIVO = 500;

    public const JUSTIFICACION_RETIRO = 500;

    public const REVISOR = 150;

    public const OBSERVACIONES_REVISION = 1000;

    public const TAREA_NOMBRE = 150;

    public const TAREA_DESCRIPCION = 500;

    public const MAX_TAREAS = 30;

    /** @return array<string, int> */
    public function limites(): array
    {
        return [
            'descripcion' => self::DESCRIPCION,
            'motivo' => self::MOTIVO,
            'justificacion_retiro' => self::JUSTIFICACION_RETIRO,
            'revisor' => self::REVISOR,
            'observaciones_revision' => self::OBSERVACIONES_REVISION,
            'tarea_nombre' => self::TAREA_NOMBRE,
            'tarea_descripcion' => self::TAREA_DESCRIPCION,
            'max_tareas' => self::MAX_TAREAS,
        ];
    }

    /**
     * @return list<array{clave: string, tipo: string, etiqueta: string, ayuda: string, obligatorio: bool, minimo?: int}>
     */
    public function parametros(TipoDePolitica $tipo): array
    {
        if ($tipo === TipoDePolitica::Offboarding) {
            return [[
                'clave' => 'tareas', 'tipo' => 'tareas', 'etiqueta' => 'Tareas del cierre administrativo', 'obligatorio' => true,
                'ayuda' => 'Plantilla que se copia a cada proceso de salida al iniciarlo. Hasta '.self::MAX_TAREAS.' tareas con nombres distintos.',
            ]];
        }

        $disponible = ['clave' => 'disponible', 'tipo' => 'booleano', 'obligatorio' => true,
            'etiqueta' => $tipo->categoria() === 'Movimiento' ? 'Permite iniciar nuevos movimientos' : 'Permite nuevas solicitudes',
            'ayuda' => 'Si se desactiva, no se aceptan nuevas solicitudes; las existentes no se cancelan.'];
        $anticipacion = ['clave' => 'anticipacion_dias', 'tipo' => 'entero', 'minimo' => 0, 'obligatorio' => true,
            'etiqueta' => 'Anticipación mínima (días calendario)',
            'ayuda' => $tipo->categoria() === 'Movimiento'
                ? 'Días calendario enteros, iguales o mayores que 0, entre la presentación y la fecha efectiva. Se mide en la zona horaria de la organización.'
                : 'Días calendario enteros, iguales o mayores que 0, entre la presentación y la fecha inicial solicitada. Se mide en la zona horaria de la organización; 0 permite iniciar el mismo día pero no fechas pasadas.'];

        if ($tipo->categoria() === 'Movimiento') {
            return [
                $disponible,
                $anticipacion,
                ['clave' => 'documento_adicional_obligatorio', 'tipo' => 'booleano', 'obligatorio' => true,
                    'etiqueta' => 'Exige documento adicional de respaldo',
                    'ayuda' => 'Debe existir un respaldo válido y accesible para el responsable autorizado.'],
            ];
        }

        $parametros = [
            $disponible,
            ['clave' => 'justificacion_obligatoria', 'tipo' => 'booleano', 'obligatorio' => true,
                'etiqueta' => 'Exige justificación', 'ayuda' => 'La solicitud debe incluir una justificación.'],
            ['clave' => 'documento_obligatorio', 'tipo' => 'booleano', 'obligatorio' => true,
                'etiqueta' => 'Exige documento de respaldo',
                'ayuda' => 'Debe existir un respaldo válido y accesible para el responsable autorizado; no concede acceso documental a otras personas.'],
        ];
        if ($tipo->aceptaAnticipacion()) {
            $parametros[] = $anticipacion;
        }

        return $parametros;
    }

    /** @return list<string> */
    public function claves(TipoDePolitica $tipo): array
    {
        return array_map(fn (array $p) => $p['clave'], $this->parametros($tipo));
    }

    /**
     * Textos y claves de las reglas fijas aplicables, en orden.
     *
     * @return list<array{clave: string, texto: string}>
     */
    public function reglasFijas(TipoDePolitica $tipo): array
    {
        $todas = [
            'NOV-RESUELVE-ADMIN' => 'El administrador de Talento Humano resuelve las solicitudes que requieren aprobación.',
            'NOV-REGLAS-TEMPORALES' => 'Las reglas temporales de cada novedad y los saldos de vacaciones se definen en el proceso de novedades.',
            'NOV-DOCUMENTO-SIN-ACCESO' => 'Exigir un documento no concede acceso documental a otras personas.',
            'MOV-JUSTIFICACION' => 'La justificación del movimiento es siempre obligatoria.',
            'MOV-EVALUACION' => 'Antes de aprobar debe existir una evaluación del colaborador contra los requisitos del puesto destino.',
            'MOV-DESTINO' => 'La posición destino debe existir y cumplir las condiciones de asignación al aplicar el movimiento.',
            'MOV-UNA-POSICION' => 'Un colaborador no puede tener más de una posición vigente.',
            'OFF-MOTIVO-FECHA' => 'El motivo y la fecha efectiva de salida son siempre obligatorios.',
            'OFF-SIN-RETROACTIVAS' => 'No se autorizan salidas retroactivas.',
            'OFF-SIN-RETENCION' => 'Las tareas pendientes no prolongan el vínculo, no mantienen ocupada la posición ni impiden registrar la salida.',
            'OFF-RESPONSABLES' => 'Los responsables se asignan dentro del proceso de salida; la plantilla no otorga permisos.',
            'COM-AISLAMIENTO' => 'Ninguna política concede acceso entre organizaciones ni permisos que la persona no tenga.',
        ];

        $claves = match ($tipo->categoria()) {
            'Novedad' => ['NOV-RESUELVE-ADMIN', 'NOV-REGLAS-TEMPORALES', 'NOV-DOCUMENTO-SIN-ACCESO'],
            'Movimiento' => ['MOV-JUSTIFICACION', 'MOV-EVALUACION', 'MOV-DESTINO', 'MOV-UNA-POSICION'],
            default => ['OFF-MOTIVO-FECHA', 'OFF-SIN-RETROACTIVAS', 'OFF-SIN-RETENCION', 'OFF-RESPONSABLES'],
        };
        $claves[] = 'COM-AISLAMIENTO';

        return array_map(fn (string $c) => ['clave' => $c, 'texto' => $todas[$c]], $claves);
    }

    /**
     * Claves que intentan fijar una regla fija: se rechazan como contradicción, no como parámetro desconocido.
     *
     * @return array<string, string> clave del cuerpo => clave de la regla fija
     */
    public function clavesDeReglasFijas(TipoDePolitica $tipo): array
    {
        return match ($tipo->categoria()) {
            'Novedad' => [
                'aprobadores' => 'NOV-RESUELVE-ADMIN', 'sustituto_administrador' => 'NOV-RESUELVE-ADMIN',
                'saldo_vacaciones' => 'NOV-REGLAS-TEMPORALES', 'fechas_pasadas' => 'NOV-REGLAS-TEMPORALES',
            ],
            'Movimiento' => [
                'evaluacion_obligatoria' => 'MOV-EVALUACION', 'justificacion_movimiento' => 'MOV-JUSTIFICACION',
                'justificacion_obligatoria' => 'MOV-JUSTIFICACION', 'segunda_posicion' => 'MOV-UNA-POSICION',
            ],
            default => [
                'motivo_obligatorio' => 'OFF-MOTIVO-FECHA', 'fecha_efectiva_obligatoria' => 'OFF-MOTIVO-FECHA',
                'salidas_retroactivas' => 'OFF-SIN-RETROACTIVAS', 'prolonga_vinculo' => 'OFF-SIN-RETENCION',
                'tareas_retienen_salida' => 'OFF-SIN-RETENCION', 'responsables' => 'OFF-RESPONSABLES',
            ],
        };
    }

    /**
     * Definición completa para la interfaz.
     *
     * @return array<string, mixed>
     */
    public function definicion(TipoDePolitica $tipo): array
    {
        return [
            'categoria' => $tipo->categoria(),
            'parametros' => $this->parametros($tipo),
            'reglas_fijas' => $this->reglasFijas($tipo),
            'limites' => $this->limites(),
        ];
    }
}
