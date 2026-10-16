<?php

namespace App\Modules\GestionPersonal\Domain\Services;

/**
 * Periodo laboral `[inicio, fin)` con fechas de calendario (AAAA-MM-DD). Un fin nulo es un intervalo abierto
 * (infinito, no «hoy»). Un intervalo vacío (inicio = fin) no ocupa nada.
 */
final class PeriodoLaboral
{
    /** Fecha tope usada solo para comparar intervalos abiertos. */
    public const INFINITO = '9999-12-31';

    public function __construct(
        public readonly string $inicio,
        public readonly ?string $fin = null,
    ) {}

    public function finEfectivo(): string
    {
        return $this->fin ?? self::INFINITO;
    }

    public function estaVacio(): bool
    {
        return $this->inicio >= $this->finEfectivo();
    }

    /** Dos periodos no vacíos se superponen si comparten al menos un día; los contiguos no se superponen. */
    public function superpone(self $otro): bool
    {
        if ($this->estaVacio() || $otro->estaVacio()) {
            return false;
        }

        return $this->inicio < $otro->finEfectivo() && $otro->inicio < $this->finEfectivo();
    }

    /** El inicio se incluye y el fin se excluye. */
    public function contiene(string $fecha): bool
    {
        return ! $this->estaVacio() && $this->inicio <= $fecha && $fecha < $this->finEfectivo();
    }

    /** Terminó efectivamente en o antes de la fecha (tiene fin y este no es posterior a ella). */
    public function terminoEnOAntesDe(string $fecha): bool
    {
        return $this->fin !== null && $this->fin <= $fecha;
    }
}
