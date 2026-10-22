<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\Exceptions\QuizInvalido;

/**
 * Reglas puras de las preguntas de un quiz (sin Laravel): de 1 a 20 preguntas con enunciado, de 2 a 5 opciones con
 * texto y exactamente una correcta por pregunta.
 */
final class ReglasDeQuiz
{
    public const MAX_PREGUNTAS = 20;

    public const MIN_OPCIONES = 2;

    public const MAX_OPCIONES = 5;

    /**
     * @param  array<int, array{enunciado: string, opciones: array<int, array{texto: string, es_correcta: bool}>}>  $preguntas
     * @param  bool  $exigirAlMenosUna  true al publicar; un Borrador puede guardarse sin preguntas.
     *
     * @throws QuizInvalido
     */
    public function validar(array $preguntas, bool $exigirAlMenosUna = false): void
    {
        $errores = [];

        if ($exigirAlMenosUna && $preguntas === []) {
            $errores['preguntas'] = 'El quiz necesita al menos una pregunta.';
        }
        if (count($preguntas) > self::MAX_PREGUNTAS) {
            $errores['preguntas'] = 'Un quiz admite como máximo '.self::MAX_PREGUNTAS.' preguntas.';
        }

        foreach (array_values($preguntas) as $i => $pregunta) {
            $n = $i + 1;
            if (trim((string) ($pregunta['enunciado'] ?? '')) === '') {
                $errores["preguntas.$i.enunciado"] = "La pregunta $n necesita un enunciado.";
            }

            $opciones = array_values($pregunta['opciones'] ?? []);
            if (count($opciones) < self::MIN_OPCIONES || count($opciones) > self::MAX_OPCIONES) {
                $errores["preguntas.$i.opciones"] = "La pregunta $n debe tener entre ".self::MIN_OPCIONES.' y '.self::MAX_OPCIONES.' opciones.';
            }

            foreach ($opciones as $j => $opcion) {
                if (trim((string) ($opcion['texto'] ?? '')) === '') {
                    $errores["preguntas.$i.opciones.$j.texto"] = 'Cada opción necesita un texto.';
                }
            }

            $correctas = count(array_filter($opciones, fn (array $o) => (bool) ($o['es_correcta'] ?? false)));
            if ($correctas !== 1) {
                $errores["preguntas.$i.correcta"] = "La pregunta $n debe tener exactamente una opción correcta.";
            }
        }

        if ($errores !== []) {
            throw new QuizInvalido($errores);
        }
    }

    /**
     * Si las preguntas pedidas son exactamente las guardadas (mismo texto, orden y opción correcta). Sirve para que un
     * formulario que reenvía el quiz sin cambios no se rechace cuando ya tiene intentos terminados.
     *
     * @param  array<int, array{enunciado: string, opciones: array<int, array{texto: string, es_correcta: bool}>}>  $guardadas
     * @param  array<int, array{enunciado: string, opciones: array<int, array{texto: string, es_correcta: bool}>}>  $pedidas
     */
    public function iguales(array $guardadas, array $pedidas): bool
    {
        $normalizar = fn (array $preguntas) => array_map(fn (array $p) => [
            trim((string) $p['enunciado']),
            array_map(fn (array $o) => [trim((string) $o['texto']), (bool) $o['es_correcta']], array_values($p['opciones'])),
        ], array_values($preguntas));

        return $normalizar($guardadas) === $normalizar($pedidas);
    }
}
