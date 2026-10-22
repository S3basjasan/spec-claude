<?php

namespace App\Modules\Desarrollo\Application\Services;

use App\Modules\Desarrollo\Domain\Entities\EventoDeRuta;
use App\Modules\Desarrollo\Domain\Entities\InscripcionARuta;
use App\Modules\Desarrollo\Domain\Entities\Ruta;

/** Arma los eventos del historial de una inscripción con los nombres de nivel legibles (no solo los ids). */
final class EventosDeInscripcion
{
    /** @return array<string, mixed> */
    public function instantanea(InscripcionARuta $i, Ruta $ruta): array
    {
        return [
            'colaborador_id' => $i->colaboradorId,
            'estado' => $i->estado->value,
            'nivel_actual' => $this->nombreDeNivel($i->nivelActualId, $ruta),
            'nivel_objetivo' => $this->nombreDeNivel($i->nivelObjetivoId, $ruta),
        ];
    }

    public function inscrita(InscripcionARuta $nueva, Ruta $ruta, ?int $usuarioId): EventoDeRuta
    {
        return new EventoDeRuta(EventoDeRuta::TIPO_INSCRIPCION, 'inscrita', null, $this->instantanea($nueva, $ruta), $usuarioId);
    }

    public function cambio(string $accion, InscripcionARuta $antes, InscripcionARuta $despues, Ruta $ruta, ?int $usuarioId, ?string $motivo = null): EventoDeRuta
    {
        return new EventoDeRuta(EventoDeRuta::TIPO_INSCRIPCION, $accion, $this->instantanea($antes, $ruta), $this->instantanea($despues, $ruta), $usuarioId, $motivo);
    }

    private function nombreDeNivel(?int $nivelId, Ruta $ruta): ?string
    {
        if ($nivelId === null) {
            return null;
        }
        $nivel = $ruta->nivelPorId($nivelId);

        return $nivel === null ? null : "{$nivel->orden}. {$nivel->nombre}";
    }
}
