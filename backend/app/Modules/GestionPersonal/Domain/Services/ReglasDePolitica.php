<?php

namespace App\Modules\GestionPersonal\Domain\Services;

use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;

/**
 * Validación y normalización de borradores y publicaciones. Los errores se devuelven por campo
 * (`parametros.<clave>`, `parametros.tareas.<n>.nombre`…) para que la interfaz los muestre junto al campo.
 */
final class ReglasDePolitica
{
    public function __construct(
        private readonly CatalogoDePoliticas $catalogo,
        private readonly NormalizadorDeTexto $normalizador,
    ) {}

    /** Texto recortado; cualquier valor no textual se trata como vacío. */
    public function texto(mixed $valor): string
    {
        return is_string($valor) ? trim($valor) : '';
    }

    /**
     * Errores de un borrador (guardar): solo lo que se envió; la completitud se exige al publicar.
     *
     * @param  array<string, mixed>  $parametros
     * @return array<string, list<string>>
     */
    public function erroresDeEdicion(TipoDePolitica $tipo, string $descripcion, array $parametros): array
    {
        $errores = [];

        if (mb_strlen($descripcion) > CatalogoDePoliticas::DESCRIPCION) {
            $errores['descripcion'][] = 'Máximo '.CatalogoDePoliticas::DESCRIPCION.' caracteres.';
        }

        $definidos = [];
        foreach ($this->catalogo->parametros($tipo) as $definicion) {
            $definidos[$definicion['clave']] = $definicion;
        }
        $fijas = $this->catalogo->clavesDeReglasFijas($tipo);

        foreach ($parametros as $clave => $valor) {
            $campo = 'parametros.'.$clave;

            if (! is_string($clave) || ! isset($definidos[$clave])) {
                $errores[$campo][] = $this->mensajeDeClaveNoAdmitida($tipo, (string) $clave, $fijas);

                continue;
            }

            foreach ($this->erroresDeValor($definidos[$clave], $valor, $campo) as $cp => $mensajes) {
                $errores[$cp] = array_merge($errores[$cp] ?? [], $mensajes);
            }
        }

        return $errores;
    }

    /**
     * Errores de completitud (descripción y parámetros obligatorios), además de los de edición.
     *
     * @param  array<string, mixed>  $parametros
     * @return array<string, list<string>>
     */
    public function erroresDeCompletitud(TipoDePolitica $tipo, string $descripcion, array $parametros): array
    {
        $errores = $this->erroresDeEdicion($tipo, $descripcion, $parametros);

        if ($descripcion === '') {
            $errores['descripcion'][] = 'Este campo es obligatorio.';
        }

        foreach ($this->catalogo->parametros($tipo) as $definicion) {
            if ($definicion['obligatorio'] && ! array_key_exists($definicion['clave'], $parametros)) {
                $errores['parametros.'.$definicion['clave']][] = 'Este campo es obligatorio.';
            }
        }

        return $errores;
    }

    /**
     * Errores para publicar: completitud, motivo y constancia de revisión.
     *
     * @param  array<string, mixed>  $parametros
     * @return array<string, list<string>>
     */
    public function erroresDePublicacion(TipoDePolitica $tipo, string $descripcion, array $parametros, string $motivo, string $revisor, string $observaciones): array
    {
        $errores = $this->erroresDeCompletitud($tipo, $descripcion, $parametros);

        if ($motivo === '') {
            $errores['motivo'][] = 'Este campo es obligatorio.';
        } elseif (mb_strlen($motivo) > CatalogoDePoliticas::MOTIVO) {
            $errores['motivo'][] = 'Máximo '.CatalogoDePoliticas::MOTIVO.' caracteres.';
        }

        if ($revisor === '') {
            $errores['revision.revisor'][] = 'Este campo es obligatorio.';
        } elseif (mb_strlen($revisor) > CatalogoDePoliticas::REVISOR) {
            $errores['revision.revisor'][] = 'Máximo '.CatalogoDePoliticas::REVISOR.' caracteres.';
        }

        if (mb_strlen($observaciones) > CatalogoDePoliticas::OBSERVACIONES_REVISION) {
            $errores['revision.observaciones'][] = 'Máximo '.CatalogoDePoliticas::OBSERVACIONES_REVISION.' caracteres.';
        }

        return $errores;
    }

    /** @return array<string, list<string>> */
    public function erroresDeRetiro(string $justificacion): array
    {
        if ($justificacion === '') {
            return ['justificacion' => ['Este campo es obligatorio.']];
        }
        if (mb_strlen($justificacion) > CatalogoDePoliticas::JUSTIFICACION_RETIRO) {
            return ['justificacion' => ['Máximo '.CatalogoDePoliticas::JUSTIFICACION_RETIRO.' caracteres.']];
        }

        return [];
    }

    /**
     * Parámetros en su forma canónica (solo claves del tipo; textos recortados; tareas con su orden). Se asume que
     * `erroresDeEdicion` ya no devuelve errores.
     *
     * @param  array<string, mixed>  $parametros
     * @return array<string, mixed>
     */
    public function canonicos(TipoDePolitica $tipo, array $parametros): array
    {
        $salida = [];
        foreach ($this->catalogo->claves($tipo) as $clave) {
            if (! array_key_exists($clave, $parametros)) {
                continue;
            }
            $valor = $parametros[$clave];

            if ($clave === 'tareas') {
                $tareas = [];
                foreach (array_values((array) $valor) as $i => $tarea) {
                    $descripcion = $this->texto($tarea['descripcion'] ?? null);
                    $tareas[] = [
                        'orden' => $i + 1,
                        'nombre' => $this->texto($tarea['nombre'] ?? null),
                        'descripcion' => $descripcion === '' ? null : $descripcion,
                        'exige_evidencia' => (bool) ($tarea['exige_evidencia'] ?? false),
                    ];
                }
                $valor = $tareas;
            }

            $salida[$clave] = $valor;
        }

        return $salida;
    }

    /**
     * Representación sellada al publicar (claves ordenadas) y su `sha256`.
     *
     * @param  array<string, mixed>  $parametros  Ya canónicos.
     * @return array{condiciones: array<string, mixed>, json: string, hash: string}
     */
    public function sellar(TipoDePolitica $tipo, int $numero, string $inicioVigenciaIso, array $parametros): array
    {
        $condiciones = [
            'esquema' => 1,
            'tipo' => $tipo->value,
            'numero' => $numero,
            'inicio_vigencia' => $inicioVigenciaIso,
            'parametros' => $parametros,
            'reglas_fijas' => $this->catalogo->reglasFijas($tipo),
        ];
        $json = self::json($condiciones);

        return ['condiciones' => $condiciones, 'json' => $json, 'hash' => hash('sha256', $json)];
    }

    /** JSON canónico: claves de objetos ordenadas, listas intactas, UTF-8 sin escapar. */
    public static function json(mixed $dato): string
    {
        return (string) json_encode(self::ordenar($dato), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function ordenar(mixed $dato): mixed
    {
        if (! is_array($dato)) {
            return $dato;
        }
        $esLista = array_is_list($dato);
        $dato = array_map(fn ($v) => self::ordenar($v), $dato);
        if (! $esLista) {
            ksort($dato);
        }

        return $dato;
    }

    /** @param array<string, string> $fijas */
    private function mensajeDeClaveNoAdmitida(TipoDePolitica $tipo, string $clave, array $fijas): string
    {
        if (isset($fijas[$clave])) {
            foreach ($this->catalogo->reglasFijas($tipo) as $regla) {
                if ($regla['clave'] === $fijas[$clave]) {
                    return 'Regla fija, no configurable: '.$regla['texto'];
                }
            }
        }
        if ($clave === 'anticipacion_dias' && ! $tipo->aceptaAnticipacion()) {
            return 'Este tipo de política no usa anticipación mínima.';
        }

        return 'Parámetro no admitido para este tipo de política.';
    }

    /**
     * @param  array<string, mixed>  $definicion
     * @return array<string, list<string>>
     */
    private function erroresDeValor(array $definicion, mixed $valor, string $campo): array
    {
        switch ($definicion['tipo']) {
            case 'booleano':
                return is_bool($valor) ? [] : [$campo => ['Debe ser verdadero o falso.']];
            case 'entero':
                if (! is_int($valor)) {
                    return [$campo => ['Debe ser un número entero de días calendario.']];
                }
                if ($valor < ($definicion['minimo'] ?? 0)) {
                    return [$campo => ['Debe ser igual o mayor que '.($definicion['minimo'] ?? 0).'.']];
                }

                return [];
            case 'tareas':
                return $this->erroresDeTareas($valor, $campo);
        }

        return [];
    }

    /** @return array<string, list<string>> */
    private function erroresDeTareas(mixed $valor, string $campo): array
    {
        if (! is_array($valor) || ! array_is_list($valor)) {
            return [$campo => ['Debe ser una lista de tareas.']];
        }

        $errores = [];
        if (count($valor) > CatalogoDePoliticas::MAX_TAREAS) {
            $errores[$campo][] = 'Máximo '.CatalogoDePoliticas::MAX_TAREAS.' tareas.';
        }

        $vistos = [];
        foreach ($valor as $i => $tarea) {
            $cp = $campo.'.'.$i;
            if (! is_array($tarea)) {
                $errores[$cp][] = 'La tarea no es válida.';

                continue;
            }

            foreach (array_keys($tarea) as $clave) {
                if (! in_array($clave, ['nombre', 'descripcion', 'exige_evidencia', 'orden'], true)) {
                    $errores[$cp.'.'.$clave][] = 'Campo no admitido en una tarea.';
                }
            }

            $nombre = $this->texto($tarea['nombre'] ?? null);
            if ($nombre === '') {
                $errores[$cp.'.nombre'][] = 'El nombre de la tarea es obligatorio.';
            } elseif (mb_strlen($nombre) > CatalogoDePoliticas::TAREA_NOMBRE) {
                $errores[$cp.'.nombre'][] = 'Máximo '.CatalogoDePoliticas::TAREA_NOMBRE.' caracteres.';
            } else {
                $clave = $this->normalizador->busquedaDeTexto($nombre);
                if (isset($vistos[$clave])) {
                    $errores[$cp.'.nombre'][] = 'Ya existe una tarea con este nombre.';
                }
                $vistos[$clave] = true;
            }

            if (array_key_exists('descripcion', $tarea) && $tarea['descripcion'] !== null) {
                if (! is_string($tarea['descripcion'])) {
                    $errores[$cp.'.descripcion'][] = 'El valor no es válido.';
                } elseif (mb_strlen(trim($tarea['descripcion'])) > CatalogoDePoliticas::TAREA_DESCRIPCION) {
                    $errores[$cp.'.descripcion'][] = 'Máximo '.CatalogoDePoliticas::TAREA_DESCRIPCION.' caracteres.';
                }
            }

            if (! array_key_exists('exige_evidencia', $tarea) || ! is_bool($tarea['exige_evidencia'])) {
                $errores[$cp.'.exige_evidencia'][] = 'Debe ser verdadero o falso.';
            }
        }

        return $errores;
    }
}
