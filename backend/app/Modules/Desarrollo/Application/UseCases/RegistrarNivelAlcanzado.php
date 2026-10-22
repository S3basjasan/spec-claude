<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\ArmadorDeInscripciones;
use App\Modules\Desarrollo\Application\Services\EventosDeInscripcion;
use App\Modules\Desarrollo\Domain\Exceptions\InscripcionNoEncontrada;
use App\Modules\Desarrollo\Domain\Exceptions\InscripcionNoPermitida;
use App\Modules\Desarrollo\Domain\Exceptions\RutaNoEncontrada;
use App\Modules\Desarrollo\Domain\Repositories\RutasRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeRuta;
use DateTimeImmutable;

/**
 * Registra que el colaborador alcanzó su nivel objetivo: el nivel actual pasa a ser el objetivo y, si era el último nivel
 * de la ruta, la inscripción queda Finalizada. Es informativo; el cambio real de puesto lo ejecuta Gestión de Personal.
 */
final class RegistrarNivelAlcanzado
{
    public function __construct(
        private readonly RutasRepositoryInterface $rutas,
        private readonly EventosDeInscripcion $eventos,
        private readonly ArmadorDeInscripciones $armador,
    ) {}

    /**
     * @return array{inscripcion: array<string, mixed>, nota: string}
     *
     * @throws InscripcionNoEncontrada
     * @throws RutaNoEncontrada
     * @throws InscripcionNoPermitida
     */
    public function ejecutar(int $tenantId, int $usuarioId, int $inscripcionId): array
    {
        $antes = $this->rutas->obtenerInscripcion($tenantId, $inscripcionId) ?? throw new InscripcionNoEncontrada;
        $ruta = $this->rutas->obtener($tenantId, $antes->rutaId) ?? throw new RutaNoEncontrada;

        $antes->exigirActiva();
        if ($ruta->estado === EstadoDeRuta::Archivada) {
            throw new InscripcionNoPermitida('La ruta está archivada: sus inscripciones se conservan, pero no admiten cambios de nivel.', 'ruta_archivada');
        }

        $despues = $antes->alcanzada($ruta->ordenesPorNivel(), $ruta->ultimoOrden(), $usuarioId, new DateTimeImmutable);
        $guardada = $this->rutas->guardarInscripcion($despues, $this->eventos->cambio('nivel_alcanzado', $antes, $despues, $ruta, $usuarioId));

        return ['inscripcion' => $this->armador->detalle($tenantId, $usuarioId, $guardada), 'nota' => ArmadorDeInscripciones::NOTA_ALCANZADO];
    }
}
