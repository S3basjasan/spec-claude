<?php

namespace App\Modules\Desarrollo\Domain\Entities;

use App\Modules\Desarrollo\Domain\Exceptions\InscripcionNoPermitida;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeInscripcion;
use DateTimeImmutable;

/**
 * Inscripción de un colaborador a una ruta, con su nivel actual (opcional) y su nivel objetivo. Es informativa:
 * el cambio real de puesto lo ejecuta Gestión de Personal. Finalizada y Retirada son estados terminales.
 */
final class InscripcionARuta
{
    public const MAXIMO_MOTIVO = 500;

    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly int $rutaId,
        public readonly int $colaboradorId,
        public readonly ?int $nivelActualId,
        public readonly ?int $nivelObjetivoId,
        public readonly EstadoDeInscripcion $estado,
        public readonly ?string $motivo,
        public readonly ?int $resueltaPor,
        public readonly ?DateTimeImmutable $resueltaEn,
        public readonly ?int $creadoPor,
        public readonly ?DateTimeImmutable $creadoEn = null,
    ) {}

    public function estaActiva(): bool
    {
        return $this->estado === EstadoDeInscripcion::Activa;
    }

    /** @throws InscripcionNoPermitida si ya no está Activa */
    public function exigirActiva(): void
    {
        if (! $this->estaActiva()) {
            throw new InscripcionNoPermitida('La inscripción ya no está activa y no admite cambios.', 'inscripcion_no_activa');
        }
    }

    /**
     * @param  array<int, int>  $ordenes  nivel_id => orden de los niveles de la ruta
     *
     * @throws InscripcionNoPermitida
     */
    public static function validarNiveles(array $ordenes, ?int $actualId, ?int $objetivoId): void
    {
        if ($actualId !== null && ! isset($ordenes[$actualId])) {
            throw new InscripcionNoPermitida('El nivel actual no pertenece a la ruta.', 'nivel_ajeno', 'nivel_actual_id');
        }
        if ($objetivoId !== null && ! isset($ordenes[$objetivoId])) {
            throw new InscripcionNoPermitida('El nivel objetivo no pertenece a la ruta.', 'nivel_ajeno', 'nivel_objetivo_id');
        }
        if ($actualId !== null && $objetivoId !== null && $ordenes[$objetivoId] <= $ordenes[$actualId]) {
            throw new InscripcionNoPermitida('El nivel objetivo debe ser posterior al nivel actual.', 'objetivo_no_posterior', 'nivel_objetivo_id');
        }
    }

    /**
     * @param  array<int, int>  $ordenes
     *
     * @throws InscripcionNoPermitida
     */
    public function conNiveles(?int $actualId, ?int $objetivoId, array $ordenes): self
    {
        $this->exigirActiva();
        self::validarNiveles($ordenes, $actualId, $objetivoId);

        return $this->copiar($actualId, $objetivoId, $this->estado, $this->motivo, $this->resueltaPor, $this->resueltaEn);
    }

    /**
     * Mueve el nivel actual al objetivo. Si el objetivo era el último nivel de la ruta, la inscripción pasa a Finalizada;
     * si no, el objetivo queda sin definir hasta que se fije otro.
     *
     * @param  array<int, int>  $ordenes
     *
     * @throws InscripcionNoPermitida
     */
    public function alcanzada(array $ordenes, int $ultimoOrden, ?int $usuarioId, DateTimeImmutable $ahora): self
    {
        $this->exigirActiva();
        if ($this->nivelObjetivoId === null) {
            throw new InscripcionNoPermitida('La inscripción no tiene un nivel objetivo que registrar como alcanzado.', 'sin_objetivo', 'nivel_objetivo_id');
        }

        $esUltimo = ($ordenes[$this->nivelObjetivoId] ?? 0) >= $ultimoOrden;

        return $this->copiar(
            $this->nivelObjetivoId,
            $esUltimo ? $this->nivelObjetivoId : null,
            $esUltimo ? EstadoDeInscripcion::Finalizada : EstadoDeInscripcion::Activa,
            $this->motivo,
            $esUltimo ? $usuarioId : null,
            $esUltimo ? $ahora : null,
        );
    }

    /** @throws InscripcionNoPermitida si no está Activa o falta el motivo */
    public function retirada(string $motivo, ?int $usuarioId, DateTimeImmutable $ahora): self
    {
        $this->exigirActiva();
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new InscripcionNoPermitida('El motivo del retiro es obligatorio.', 'motivo_requerido', 'motivo');
        }
        if (mb_strlen($motivo) > self::MAXIMO_MOTIVO) {
            throw new InscripcionNoPermitida('El motivo no puede superar los 500 caracteres.', 'motivo_largo', 'motivo');
        }

        return $this->copiar($this->nivelActualId, $this->nivelObjetivoId, EstadoDeInscripcion::Retirada, $motivo, $usuarioId, $ahora);
    }

    private function copiar(?int $actualId, ?int $objetivoId, EstadoDeInscripcion $estado, ?string $motivo, ?int $resueltaPor, ?DateTimeImmutable $resueltaEn): self
    {
        return new self(
            $this->id,
            $this->tenantId,
            $this->rutaId,
            $this->colaboradorId,
            $actualId,
            $objetivoId,
            $estado,
            $motivo,
            $resueltaPor,
            $resueltaEn,
            $this->creadoPor,
            $this->creadoEn,
        );
    }
}
