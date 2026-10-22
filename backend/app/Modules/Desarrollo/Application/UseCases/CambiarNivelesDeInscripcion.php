<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\ArmadorDeInscripciones;
use App\Modules\Desarrollo\Application\Services\EventosDeInscripcion;
use App\Modules\Desarrollo\Domain\Exceptions\InscripcionNoEncontrada;
use App\Modules\Desarrollo\Domain\Exceptions\InscripcionNoPermitida;
use App\Modules\Desarrollo\Domain\Exceptions\RutaNoEncontrada;
use App\Modules\Desarrollo\Domain\Repositories\RutasRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeRuta;

/** Cambia el nivel actual y el nivel objetivo de una inscripción Activa de una ruta no Archivada. */
final class CambiarNivelesDeInscripcion
{
    public function __construct(
        private readonly RutasRepositoryInterface $rutas,
        private readonly EventosDeInscripcion $eventos,
        private readonly ArmadorDeInscripciones $armador,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws InscripcionNoEncontrada
     * @throws RutaNoEncontrada
     * @throws InscripcionNoPermitida
     */
    public function ejecutar(int $tenantId, int $usuarioId, int $inscripcionId, ?int $nivelActualId, ?int $nivelObjetivoId): array
    {
        $antes = $this->rutas->obtenerInscripcion($tenantId, $inscripcionId) ?? throw new InscripcionNoEncontrada;
        $ruta = $this->rutas->obtener($tenantId, $antes->rutaId) ?? throw new RutaNoEncontrada;

        $antes->exigirActiva();
        if ($ruta->estado === EstadoDeRuta::Archivada) {
            throw new InscripcionNoPermitida('La ruta está archivada: sus inscripciones se conservan, pero no admiten cambios de nivel.', 'ruta_archivada');
        }

        $despues = $antes->conNiveles($nivelActualId, $nivelObjetivoId, $ruta->ordenesPorNivel());
        $guardada = $this->rutas->guardarInscripcion($despues, $this->eventos->cambio('niveles_cambiados', $antes, $despues, $ruta, $usuarioId));

        return $this->armador->detalle($tenantId, $usuarioId, $guardada);
    }
}
