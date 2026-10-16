<?php

namespace App\Modules\GestionPersonal\Domain\Services;

use DateTimeImmutable;

/**
 * Etiquetas funcionales de la ficha exportada. «No registrado» y «No informada» se aplican solo cuando la lectura
 * fue exitosa y el dato es nulo: un fallo de lectura nunca llega aquí.
 */
final class EtiquetasDeExportacion
{
    public const NO_REGISTRADO = 'No registrado';

    public const NO_INFORMADA = 'No informada';

    public const SIN_REGISTROS = 'Sin registros';

    /** Texto de un dato: nulo o vacío ⇒ «No registrado»; el número 0 se muestra como 0. */
    public function valor(mixed $valor): string
    {
        if ($valor === null || (is_string($valor) && trim($valor) === '')) {
            return self::NO_REGISTRADO;
        }

        return (string) $valor;
    }

    /** `AAAA-MM-DD` ⇒ `DD/MM/AAAA`; nulo ⇒ «No registrado». */
    public function fecha(?string $fecha): string
    {
        if ($fecha === null || $fecha === '') {
            return self::NO_REGISTRADO;
        }

        $parseada = DateTimeImmutable::createFromFormat('!Y-m-d', substr($fecha, 0, 10));

        return $parseada === false ? $fecha : $parseada->format('d/m/Y');
    }

    public function tipoDeIdentificacion(string $tipo): string
    {
        return ['Cedula' => 'Cédula', 'Pasaporte' => 'Pasaporte', 'Otro' => 'Otro'][$tipo] ?? $tipo;
    }

    public function estadoLaboral(string $estado): string
    {
        return ['SinVinculoVigente' => 'Sin vínculo vigente', 'Activo' => 'Activo', 'Desvinculado' => 'Desvinculado'][$estado] ?? $estado;
    }

    public function estadoDeFormacion(string $estado): string
    {
        return ['EnCurso' => 'En curso', 'Completada' => 'Completada', 'Incompleta' => 'Incompleta'][$estado] ?? $estado;
    }

    public function estadoDeMovilidad(string $estado): string
    {
        return ['Disponible' => 'Disponible', 'NoDisponible' => 'No disponible', 'NoInformada' => self::NO_INFORMADA][$estado] ?? $estado;
    }

    /** Nombre de descarga: `Ficha_{codigo}.pdf` con el código saneado a `[A-Za-z0-9_-]`. */
    public function nombreDeArchivo(string $codigo): string
    {
        $seguro = preg_replace('/[^A-Za-z0-9_-]/', '', $codigo);

        return 'Ficha_'.($seguro === '' || $seguro === null ? 'SIN-CODIGO' : $seguro).'.pdf';
    }
}
