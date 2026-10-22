<?php

namespace App\Modules\Desarrollo\Domain\Repositories;

use App\Modules\Desarrollo\Domain\Entities\IntentoQuiz;
use App\Modules\Desarrollo\Domain\Entities\ProgresoMicro;

/** Persistencia del aprendizaje del colaborador: progreso, intentos y respuestas de quiz. */
interface ProgresoMicroRepositoryInterface
{
    public function progresoDe(int $microcontenidoId, int $colaboradorId): ?ProgresoMicro;

    /**
     * Crea el progreso En progreso si no existe (con fecha de inicio) y, si se indica, guarda la posición del Video.
     */
    public function iniciarOGuardarAvance(int $microcontenidoId, int $colaboradorId, int $tenantId, ?int $posicionSegundos): ProgresoMicro;

    /** Pasa el progreso a Completado con la fecha de finalización (no hace nada si ya lo estaba). */
    public function completar(int $progresoId): ProgresoMicro;

    public function intentoEnCurso(int $progresoId): ?IntentoQuiz;

    public function obtenerIntento(int $intentoId): ?IntentoQuiz;

    /** Crea un intento En curso con el total de preguntas del quiz. */
    public function crearIntento(int $progresoId, int $totalPreguntas): IntentoQuiz;

    /** Borra explícitamente las respuestas y el intento En curso del progreso. */
    public function descartarIntentosEnCurso(int $progresoId): void;

    public function registrarRespuesta(int $intentoId, int $preguntaId, int $opcionId, bool $esCorrecta): void;

    /** Pasa el intento a Finalizado con su puntaje y la fecha. */
    public function finalizarIntento(int $intentoId, int $puntaje): void;

    /**
     * Último intento finalizado del progreso.
     *
     * @return array{correctas: int, total: int}|null
     */
    public function ultimoPuntaje(int $progresoId): ?array;

    /**
     * Progresos del colaborador con el último puntaje de cada uno.
     *
     * @return array<int, array{progreso: ProgresoMicro, ultimo_puntaje: array{correctas: int, total: int}|null}>
     */
    public function progresosDe(int $colaboradorId): array;

    /**
     * Resumen del colaborador: completados, en progreso y suma de minutos de los completados.
     *
     * @return array{completados: int, en_progreso: int, minutos: int}
     */
    public function resumenDe(int $tenantId, int $colaboradorId): array;

    /**
     * Completaciones del tenant (progresos Completado) con los datos del contenido.
     *
     * @param  array{colaborador_id?: int, microcontenido_id?: int, desde?: string, hasta?: string}  $filtros
     * @return array<int, array{microcontenido_id: int, codigo: string, titulo: string, tipo: string, duracion_minutos: int, colaborador_id: int, fecha_inicio: string, fecha_completado: string}>
     */
    public function completaciones(int $tenantId, array $filtros): array;
}
