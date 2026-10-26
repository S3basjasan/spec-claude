<?php

namespace App\Modules\GestionPersonal\Domain\Services;

use App\Modules\GestionPersonal\Domain\Exceptions\AnticipacionInsuficiente;
use App\Modules\GestionPersonal\Domain\Exceptions\AusenciaFutura;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\DocumentoRequerido;
use App\Modules\GestionPersonal\Domain\Exceptions\FechaPasada;
use App\Modules\GestionPersonal\Domain\Exceptions\JustificacionRequerida;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDeActuacion;
use DateTimeImmutable;

/**
 * Reglas puras de presentación de una actuación: forma de los datos, condiciones de la política conservada y reglas
 * temporales por tipo. Las fechas son de calendario (AAAA-MM-DD) y `$hoy` es el día en la zona horaria del tenant.
 */
final class ReglasDeActuacion
{
    public const FECHA_MINIMA = '1900-01-01';

    public const MOTIVO_MAXIMO = 1000;

    public const MAX_RESPALDOS = 10;

    /**
     * @param  array<string, mixed>  $datos  `fecha_inicio`, `fecha_fin`, `fecha_efectiva` y `motivo` según el tipo.
     * @param  array<string, mixed>  $parametros  Parámetros de la política conservada.
     * @param  int  $respaldosValidos  Respaldos referenciados que existen y son accesibles para quien presenta.
     *
     * @throws DatosInvalidos
     * @throws JustificacionRequerida
     * @throws DocumentoRequerido
     * @throws FechaPasada
     * @throws AnticipacionInsuficiente
     * @throws AusenciaFutura
     */
    public function verificar(TipoDeActuacion $tipo, array $datos, array $parametros, string $hoy, int $respaldosValidos): void
    {
        $errores = $this->errores($tipo, $datos, $hoy);
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        $motivo = trim((string) ($datos['motivo'] ?? ''));
        if ($tipo->esSolicitudDeNovedad() || $tipo->esAusencia()) {
            if (($parametros['justificacion_obligatoria'] ?? false) === true && $motivo === '') {
                throw new JustificacionRequerida;
            }
            if (($parametros['documento_obligatorio'] ?? false) === true && $respaldosValidos < 1) {
                throw new DocumentoRequerido;
            }
        } elseif (($parametros['documento_adicional_obligatorio'] ?? false) === true && $respaldosValidos < 1) {
            throw new DocumentoRequerido;
        }

        $this->verificarTiempo($tipo, $datos, $parametros, $hoy);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, list<string>>
     */
    public function errores(TipoDeActuacion $tipo, array $datos, string $hoy): array
    {
        $errores = [];
        $maximo = $this->sumarAnios($hoy, 10);

        $revisar = function (string $campo, string $etiqueta) use ($datos, &$errores, $maximo): void {
            $valor = $datos[$campo] ?? null;
            if (! is_string($valor) || ! $this->esFechaValida($valor)) {
                $errores[$campo][] = "$etiqueta es obligatoria y debe tener el formato AAAA-MM-DD.";
            } elseif ($valor < self::FECHA_MINIMA || $valor > $maximo) {
                $errores[$campo][] = "$etiqueta está fuera del rango permitido.";
            }
        };

        if ($tipo->esMovimiento()) {
            $revisar('fecha_efectiva', 'La fecha efectiva');
        } else {
            $revisar('fecha_inicio', 'La fecha de inicio');
            $revisar('fecha_fin', 'La fecha de fin');
            if (! isset($errores['fecha_inicio'], $errores['fecha_fin']) && $datos['fecha_fin'] < $datos['fecha_inicio']) {
                $errores['fecha_fin'][] = 'La fecha de fin no puede ser anterior a la de inicio.';
            }
        }

        $motivo = $datos['motivo'] ?? null;
        if ($motivo !== null && ! is_string($motivo)) {
            $errores['motivo'][] = 'El motivo debe ser texto.';
        } elseif (is_string($motivo) && mb_strlen($motivo) > self::MOTIVO_MAXIMO) {
            $errores['motivo'][] = 'El motivo no puede superar '.self::MOTIVO_MAXIMO.' caracteres.';
        }
        if ($tipo->esMovimiento() && trim((string) $motivo) === '') {
            $errores['motivo'] = ['La justificación del movimiento es obligatoria.'];
        }

        return $errores;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, mixed>  $parametros
     */
    private function verificarTiempo(TipoDeActuacion $tipo, array $datos, array $parametros, string $hoy): void
    {
        if ($tipo->esAusencia()) {
            if ($datos['fecha_fin'] > $hoy) {
                throw new AusenciaFutura;
            }

            return;
        }

        $inicio = (string) ($tipo->esMovimiento() ? $datos['fecha_efectiva'] : $datos['fecha_inicio']);
        if ($inicio < $hoy) {
            throw new FechaPasada($tipo->esMovimiento()
                ? 'La fecha efectiva no puede ser anterior a hoy.'
                : 'La fecha de inicio no puede ser anterior a hoy.');
        }

        $dias = $parametros['anticipacion_dias'] ?? null;
        if (is_int($dias) && $dias > 0) {
            $primera = $this->sumarDias($hoy, $dias);
            if ($inicio < $primera) {
                $campo = $tipo->esMovimiento() ? 'La fecha efectiva' : 'El inicio';

                throw new AnticipacionInsuficiente("$campo exige $dias días calendario de anticipación; la primera fecha posible es el $primera.");
            }
        }
    }

    public function sumarDias(string $fecha, int $dias): string
    {
        return (new DateTimeImmutable($fecha))->modify(($dias >= 0 ? '+' : '').$dias.' days')->format('Y-m-d');
    }

    private function esFechaValida(string $fecha): bool
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $m)) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    private function sumarAnios(string $fecha, int $anios): string
    {
        return sprintf('%04d', (int) substr($fecha, 0, 4) + $anios).substr($fecha, 4);
    }
}
