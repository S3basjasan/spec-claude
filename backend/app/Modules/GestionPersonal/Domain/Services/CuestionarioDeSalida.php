<?php

namespace App\Modules\GestionPersonal\Domain\Services;

use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;

/**
 * Cuestionario de salida predefinido y versionado (versión 1): sin diseñador por tenant. Todo es opcional. PHP puro.
 */
final class CuestionarioDeSalida
{
    public const VERSION = 1;

    public const ESTRUCTURA_VERSION = 1;

    public const TEXTO_MAXIMO = 2000;

    /** @var array<int, string> */
    public const ESCALA = [
        1 => 'Muy insatisfecho',
        2 => 'Insatisfecho',
        3 => 'Ni satisfecho ni insatisfecho',
        4 => 'Satisfecho',
        5 => 'Muy satisfecho',
    ];

    /** @return array<string, array{nombre: string, pregunta: string}> */
    public function dimensiones(): array
    {
        return [
            'DIM-DESARROLLO' => ['nombre' => 'Desarrollo profesional', 'pregunta' => '¿Qué tan satisfecho estuvo con las oportunidades de crecimiento y desarrollo profesional?'],
            'DIM-SUPERVISION' => ['nombre' => 'Supervisión', 'pregunta' => '¿Qué tan satisfecho estuvo con el apoyo recibido de su supervisor?'],
            'DIM-CARGA' => ['nombre' => 'Carga laboral', 'pregunta' => '¿Qué tan satisfecho estuvo con la distribución y organización de su carga de trabajo?'],
            'DIM-RECONOCIMIENTO' => ['nombre' => 'Reconocimiento', 'pregunta' => '¿Qué tan satisfecho estuvo con el reconocimiento recibido por su trabajo?'],
            'DIM-COMUNICACION' => ['nombre' => 'Comunicación interna', 'pregunta' => '¿Qué tan satisfecho estuvo con la comunicación dentro de la organización?'],
            'DIM-GENERAL' => ['nombre' => 'Experiencia general', 'pregunta' => '¿Qué tan satisfecho estuvo, en términos generales, con su experiencia laboral?'],
        ];
    }

    /** @return array<string, array{nombre: string, descripcion: string}> */
    public function categorias(): array
    {
        return [
            'CAT-DESARROLLO' => ['nombre' => 'Desarrollo profesional', 'descripcion' => 'Oportunidades de crecimiento, capacitación o proyección laboral.'],
            'CAT-SUPERVISION' => ['nombre' => 'Supervisión', 'descripcion' => 'Experiencia de acompañamiento y relación profesional con la supervisión.'],
            'CAT-CARGA' => ['nombre' => 'Carga laboral', 'descripcion' => 'Distribución de responsabilidades, volumen de trabajo y organización de tareas.'],
            'CAT-RECONOCIMIENTO' => ['nombre' => 'Reconocimiento', 'descripcion' => 'Percepción de valoración y reconocimiento de las contribuciones realizadas.'],
            'CAT-COMPENSACION' => ['nombre' => 'Compensación', 'descripcion' => 'Percepción sobre remuneración y beneficios laborales.'],
            'CAT-AMBIENTE' => ['nombre' => 'Ambiente laboral', 'descripcion' => 'Relaciones de trabajo y condiciones generales del entorno organizacional.'],
            'CAT-PERSONAL' => ['nombre' => 'Motivos personales', 'descripcion' => 'Circunstancias que el colaborador decide declarar como ajenas o no necesariamente atribuibles a la organización.'],
            'CAT-OTROS' => ['nombre' => 'Otros', 'descripcion' => 'Factores que no correspondan a las categorías anteriores.'],
        ];
    }

    /** @return array<string, string> */
    public function abiertas(): array
    {
        return [
            'ABI-01' => '¿Cuáles fueron los principales factores que influyeron en su decisión de desvincularse?',
            'ABI-02' => '¿Qué aspectos positivos destacaría de su experiencia en la organización?',
            'ABI-03' => '¿Qué situaciones o dificultades considera que afectaron su experiencia laboral?',
            'ABI-04' => '¿Qué cambios recomendaría para mejorar las condiciones de trabajo?',
            'ABI-05' => '¿Qué oportunidades de desarrollo o crecimiento considera que le hicieron falta?',
            'ABI-06' => '¿Desea compartir alguna observación adicional?',
        ];
    }

    /** @return array<string, mixed> */
    public function definicion(): array
    {
        return [
            'version' => self::VERSION,
            'escala' => self::ESCALA,
            'dimensiones' => $this->dimensiones(),
            'categorias' => $this->categorias(),
            'abiertas' => $this->abiertas(),
        ];
    }

    /** @return list<string> Identificadores de todas las preguntas, en orden. */
    public function preguntas(): array
    {
        return [...array_keys($this->dimensiones()), 'CATEGORIAS', ...array_keys($this->abiertas())];
    }

    /**
     * Normaliza y valida lo enviado: solo identificadores del cuestionario, valores 1 a 5 o ausentes, categorías sin
     * repetir y texto sin recortar su contenido (un texto vacío es «no proporcionado»).
     *
     * @param  array<mixed>  $valoraciones
     * @param  array<mixed>  $categorias
     * @param  array<mixed>  $abiertas
     * @return array{valoraciones: array<string, int>, categorias: list<string>, abiertas: array<string, string>}
     *
     * @throws DatosInvalidos
     */
    public function normalizar(array $valoraciones, array $categorias, array $abiertas): array
    {
        $errores = [];
        $dimensiones = $this->dimensiones();
        $salidaValoraciones = [];
        foreach ($valoraciones as $id => $valor) {
            if (! isset($dimensiones[$id])) {
                $errores["valoraciones.$id"][] = 'La pregunta no pertenece al cuestionario.';

                continue;
            }
            if ($valor === null || $valor === '') {
                continue;
            }
            if (! is_int($valor) || $valor < 1 || $valor > 5) {
                $errores["valoraciones.$id"][] = 'La valoración debe ser un número entero de 1 a 5.';

                continue;
            }
            $salidaValoraciones[$id] = $valor;
        }

        $validas = $this->categorias();
        $salidaCategorias = [];
        foreach ($categorias as $id) {
            if (! is_string($id) || ! isset($validas[$id])) {
                $errores['categorias'][] = 'Una categoría no pertenece al catálogo.';

                continue;
            }
            $salidaCategorias[$id] = $id;
        }

        $preguntas = $this->abiertas();
        $salidaAbiertas = [];
        foreach ($abiertas as $id => $texto) {
            if (! isset($preguntas[$id])) {
                $errores["abiertas.$id"][] = 'La pregunta no pertenece al cuestionario.';

                continue;
            }
            if ($texto === null) {
                continue;
            }
            if (! is_string($texto)) {
                $errores["abiertas.$id"][] = 'La respuesta debe ser texto.';

                continue;
            }
            if (mb_strlen($texto) > self::TEXTO_MAXIMO) {
                $errores["abiertas.$id"][] = 'La respuesta no puede superar '.self::TEXTO_MAXIMO.' caracteres.';

                continue;
            }
            if (trim($texto) !== '') {
                $salidaAbiertas[$id] = $texto;
            }
        }

        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        return ['valoraciones' => $salidaValoraciones, 'categorias' => array_values($salidaCategorias), 'abiertas' => $salidaAbiertas];
    }
}
