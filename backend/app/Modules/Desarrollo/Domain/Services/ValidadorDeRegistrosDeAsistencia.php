<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\Exceptions\RegistroDeAsistenciaInvalido;
use App\Modules\Desarrollo\Domain\ValueObjects\AsistenciaParticipante;

/** Valida el lote de registros de asistencia contra los inscritos actuales y las reglas de calificación. */
final class ValidadorDeRegistrosDeAsistencia
{
    public const OBSERVACIONES_MAXIMO = 1000;

    /**
     * @param  list<array<string, mixed>>  $registros  colaborador_id, asistencia, calificacion?, observaciones?
     * @param  list<int>  $inscritos
     *
     * @throws RegistroDeAsistenciaInvalido
     */
    public function validar(array $registros, array $inscritos): void
    {
        $errores = [];
        if ($registros === []) {
            throw new RegistroDeAsistenciaInvalido(['registros' => ['Debes enviar al menos un registro.']]);
        }

        $vistos = [];
        foreach (array_values($registros) as $i => $r) {
            $campo = fn (string $c) => "registros.{$i}.{$c}";
            $colaborador = $r['colaborador_id'] ?? null;
            if (! is_int($colaborador)) {
                $errores[$campo('colaborador_id')][] = 'El colaborador no es válido.';

                continue;
            }
            if (isset($vistos[$colaborador])) {
                $errores[$campo('colaborador_id')][] = 'El colaborador está repetido.';
            }
            $vistos[$colaborador] = true;
            if (! in_array($colaborador, $inscritos, true)) {
                $errores[$campo('colaborador_id')][] = 'El colaborador no está inscrito en la acción.';
            }

            $asistencia = is_string($r['asistencia'] ?? null) ? AsistenciaParticipante::tryFrom($r['asistencia']) : null;
            if ($asistencia === null) {
                $errores[$campo('asistencia')][] = 'La asistencia debe ser Pendiente, Presente, Ausente o Justificado.';

                continue;
            }

            $calificacion = $r['calificacion'] ?? null;
            if ($calificacion !== null) {
                if (! is_int($calificacion) || $calificacion < 1 || $calificacion > 5) {
                    $errores[$campo('calificacion')][] = 'La calificación debe ser un entero de 1 a 5.';
                } elseif ($asistencia !== AsistenciaParticipante::Presente) {
                    $errores[$campo('calificacion')][] = 'Solo se puede calificar a un participante con asistencia Presente.';
                }
            }

            $observaciones = $r['observaciones'] ?? null;
            if ($observaciones !== null && $observaciones !== '') {
                if (! is_string($observaciones) || mb_strlen($observaciones) > self::OBSERVACIONES_MAXIMO) {
                    $errores[$campo('observaciones')][] = 'Las observaciones no pueden superar los '.self::OBSERVACIONES_MAXIMO.' caracteres.';
                } elseif ($asistencia === AsistenciaParticipante::Pendiente) {
                    $errores[$campo('observaciones')][] = 'Registra la asistencia antes de añadir observaciones.';
                }
            }
        }

        if ($errores !== []) {
            throw new RegistroDeAsistenciaInvalido($errores);
        }
    }
}
