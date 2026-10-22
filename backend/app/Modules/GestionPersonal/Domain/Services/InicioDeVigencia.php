<?php

namespace App\Modules\GestionPersonal\Domain\Services;

use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\InicioEnElPasado;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Resuelve el inicio de vigencia en la zona horaria de la organización. Un horario inexistente por cambio de hora
 * pasa al primer instante válido posterior; uno repetido toma la primera ocurrencia.
 */
final class InicioDeVigencia
{
    /**
     * @param  array<string, mixed>  $inicio  `{modo: inmediato}` o `{modo: programado, fecha_hora_local: AAAA-MM-DDTHH:mm}`
     * @return array{utc: DateTimeImmutable, local: string}
     *
     * @throws DatosInvalidos
     * @throws InicioEnElPasado
     */
    public function resolver(array $inicio, DateTimeImmutable $ahora, string $zona): array
    {
        $modo = $inicio['modo'] ?? null;
        $tz = new DateTimeZone($zona);

        if ($modo === 'inmediato') {
            $utc = $ahora->setTimezone(new DateTimeZone('UTC'));

            return ['utc' => $utc, 'local' => $utc->setTimezone($tz)->format('Y-m-d\TH:i')];
        }

        if ($modo !== 'programado') {
            throw new DatosInvalidos(['inicio.modo' => ['Elige publicar de inmediato o programar un inicio.']]);
        }

        $texto = $inicio['fecha_hora_local'] ?? null;
        $local = is_string($texto) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $texto) === 1
            ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $texto, $tz)
            : false;
        $errores = DateTimeImmutable::getLastErrors();
        if ($local === false || ($errores !== false && ($errores['warning_count'] > 0 || $errores['error_count'] > 0))) {
            throw new DatosInvalidos(['inicio.fecha_hora_local' => ['La fecha y hora no son válidas.']]);
        }

        $instante = $this->primerInstanteValido($local, (string) $texto, $tz);
        $utc = $instante->setTimezone(new DateTimeZone('UTC'));

        if ($utc <= $ahora) {
            throw new InicioEnElPasado;
        }

        return ['utc' => $utc, 'local' => $instante->setTimezone($tz)->format('Y-m-d\TH:i')];
    }

    private function primerInstanteValido(DateTimeImmutable $local, string $texto, DateTimeZone $tz): DateTimeImmutable
    {
        // PHP desplaza un horario inexistente hacia adelante (02:30 → 03:30): se reconoce porque no se reproduce.
        if ($local->format('Y-m-d\TH:i') === $texto) {
            return $local;
        }

        $ts = $local->getTimestamp();
        foreach ($tz->getTransitions($ts - 172800, $ts + 172800) as $transicion) {
            if ($transicion['ts'] > $ts - 86400 && $transicion['ts'] <= $ts) {
                return (new DateTimeImmutable('@'.$transicion['ts']))->setTimezone($tz);
            }
        }

        return $local;
    }
}
