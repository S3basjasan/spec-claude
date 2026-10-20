<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\Exceptions\LugarInvalido;
use App\Modules\Desarrollo\Domain\ValueObjects\AccionDeActividad;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoAccion;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoPlan;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoAccion;
use DateTimeImmutable;

/** Reglas puras de una actividad formativa: estado calculado, lugar o enlace y bloqueos por estado. */
final class ReglasDeActividad
{
    public const LUGAR_MAXIMO = 500;

    public function __construct(private readonly CalculadorDeEstadoDeAccion $estados = new CalculadorDeEstadoDeAccion) {}

    /** Estado de la actividad: el de su acción respecto a hoy (no se guarda). */
    public function estado(AccionDeActividad $accion, DateTimeImmutable $hoy): EstadoAccion
    {
        return $this->estados->calcular($accion->cancelada, $accion->fechaInicio, $accion->fechaFin, $hoy);
    }

    /**
     * Virtual y E-learning exigen una URL http(s); los demás tipos aceptan texto libre.
     *
     * @throws LugarInvalido
     */
    public function validarLugar(?TipoAccion $tipo, string $lugar): void
    {
        $lugar = trim($lugar);
        if ($lugar === '') {
            throw new LugarInvalido('El lugar o enlace es obligatorio.');
        }
        if (mb_strlen($lugar) > self::LUGAR_MAXIMO) {
            throw new LugarInvalido('El lugar o enlace no puede superar los '.self::LUGAR_MAXIMO.' caracteres.');
        }
        if (in_array($tipo, [TipoAccion::Virtual, TipoAccion::ELearning], true) && ! $this->esUrl($lugar)) {
            throw new LugarInvalido('Para una acción virtual o e-learning el enlace debe ser una URL válida (http o https).');
        }
    }

    public function puedeEditar(EstadoPlan $plan): bool
    {
        return $plan !== EstadoPlan::Cerrado;
    }

    public function puedeRegistrar(EstadoAccion $estado, EstadoPlan $plan): bool
    {
        return $this->motivoBloqueo($estado, $plan) === null;
    }

    /** @return 'plan_cerrado'|'actividad_cancelada'|'actividad_programada'|null */
    public function motivoBloqueo(EstadoAccion $estado, EstadoPlan $plan): ?string
    {
        if ($plan === EstadoPlan::Cerrado) {
            return 'plan_cerrado';
        }

        return match ($estado) {
            EstadoAccion::Cancelada => 'actividad_cancelada',
            EstadoAccion::Programada => 'actividad_programada',
            default => null,
        };
    }

    /** Finalizada con al menos un inscrito sin registro. */
    public function asistenciaPendiente(EstadoAccion $estado, int $inscritos, int $registradosDeInscritos): bool
    {
        return $estado === EstadoAccion::Finalizada && $inscritos - $registradosDeInscritos > 0;
    }

    private function esUrl(string $valor): bool
    {
        if (preg_match('/\s/', $valor) === 1) {
            return false;
        }
        if (filter_var($valor, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return in_array(strtolower((string) parse_url($valor, PHP_URL_SCHEME)), ['http', 'https'], true)
            && (string) parse_url($valor, PHP_URL_HOST) !== '';
    }
}
