<?php

namespace App\Modules\GestionPersonal\Domain\Services;

use App\Modules\GestionPersonal\Domain\ValueObjects\TipoIdentificacion;

/**
 * Reglas puras de una ficha, evaluadas sobre el estado resultante completo.
 */
final class ReglasDeFicha
{
    public const OBLIGATORIOS = ['tipo_identificacion', 'numero_identificacion', 'nombres', 'apellidos'];

    public const OPCIONALES = [
        'fecha_nacimiento', 'nacionalidad', 'genero', 'estado_civil', 'numero_hijos',
        'direccion', 'telefono', 'correo_contacto',
        'emergencia_nombre', 'emergencia_relacion', 'emergencia_telefono',
    ];

    /**
     * Recorta textos y convierte vacíos a null; conserva 0 y los números.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function normalizar(array $datos): array
    {
        $salida = [];
        foreach ($datos as $campo => $valor) {
            if (is_string($valor)) {
                $valor = trim($valor);
                $valor = $valor === '' ? null : $valor;
            }
            $salida[$campo] = $valor;
        }

        return $salida;
    }

    /**
     * @param  array<string, mixed>  $estado  Estado completo de la ficha (ya normalizado).
     * @param  string  $hoy  Fecha de hoy en la zona del tenant (Y-m-d).
     * @return array<string, list<string>>
     */
    public function errores(array $estado, string $hoy): array
    {
        $errores = [];

        foreach (self::OBLIGATORIOS as $campo) {
            if (! isset($estado[$campo]) || $estado[$campo] === '') {
                $errores[$campo][] = 'Este campo es obligatorio.';
            }
        }

        if (isset($estado['tipo_identificacion']) && ! in_array($estado['tipo_identificacion'], TipoIdentificacion::valores(), true)) {
            $errores['tipo_identificacion'][] = 'El tipo de identificación no es válido.';
        }

        if (($estado['fecha_nacimiento'] ?? null) !== null && $estado['fecha_nacimiento'] > $hoy) {
            $errores['fecha_nacimiento'][] = 'La fecha de nacimiento no puede ser futura.';
        }

        if (($estado['numero_hijos'] ?? null) !== null && (int) $estado['numero_hijos'] < 0) {
            $errores['numero_hijos'][] = 'El número de hijos debe ser un entero igual o mayor que cero.';
        }

        $hayEmergencia = ($estado['emergencia_nombre'] ?? null) !== null
            || ($estado['emergencia_relacion'] ?? null) !== null
            || ($estado['emergencia_telefono'] ?? null) !== null;

        if ($hayEmergencia) {
            if (($estado['emergencia_nombre'] ?? null) === null) {
                $errores['emergencia_nombre'][] = 'El nombre del contacto de emergencia es obligatorio.';
            }
            if (($estado['emergencia_telefono'] ?? null) === null) {
                $errores['emergencia_telefono'][] = 'El teléfono del contacto de emergencia es obligatorio.';
            }
        }

        return $errores;
    }
}
