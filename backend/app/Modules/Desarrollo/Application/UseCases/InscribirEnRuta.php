<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\ColaboradoresVigentesInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDeInscripciones;
use App\Modules\Desarrollo\Application\Services\EventosDeInscripcion;
use App\Modules\Desarrollo\Domain\Entities\InscripcionARuta;
use App\Modules\Desarrollo\Domain\Exceptions\ColaboradorNoEncontradoParaRuta;
use App\Modules\Desarrollo\Domain\Exceptions\FuenteNoDisponibleParaRuta;
use App\Modules\Desarrollo\Domain\Exceptions\InscripcionNoPermitida;
use App\Modules\Desarrollo\Domain\Exceptions\RutaNoEncontrada;
use App\Modules\Desarrollo\Domain\Repositories\RutasRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeInscripcion;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeRuta;
use Throwable;

/** Inscribe a un colaborador activo en una ruta Vigente, con su nivel actual (opcional) y su nivel objetivo. */
final class InscribirEnRuta
{
    public function __construct(
        private readonly RutasRepositoryInterface $rutas,
        private readonly ColaboradoresVigentesInterface $colaboradores,
        private readonly NombresDeReferenciaInterface $referencias,
        private readonly RelojInterface $reloj,
        private readonly EventosDeInscripcion $eventos,
        private readonly ArmadorDeInscripciones $armador,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws RutaNoEncontrada
     * @throws ColaboradorNoEncontradoParaRuta
     * @throws InscripcionNoPermitida
     * @throws FuenteNoDisponibleParaRuta
     */
    public function ejecutar(int $tenantId, int $usuarioId, int $rutaId, int $colaboradorId, ?int $nivelActualId, ?int $nivelObjetivoId): array
    {
        $ruta = $this->rutas->obtener($tenantId, $rutaId) ?? throw new RutaNoEncontrada;
        $this->exigirColaboradorActivo($tenantId, $usuarioId, $colaboradorId);

        if ($ruta->estado !== EstadoDeRuta::Vigente) {
            throw new InscripcionNoPermitida('Solo las rutas Vigentes admiten nuevas inscripciones.', 'ruta_no_vigente', 'ruta_id');
        }
        InscripcionARuta::validarNiveles($ruta->ordenesPorNivel(), $nivelActualId, $nivelObjetivoId);

        $nueva = new InscripcionARuta(null, $tenantId, $rutaId, $colaboradorId, $nivelActualId, $nivelObjetivoId, EstadoDeInscripcion::Activa, null, null, null, $usuarioId);
        $creada = $this->rutas->crearInscripcion($nueva, $this->eventos->inscrita($nueva, $ruta, $usuarioId));

        return $this->armador->detalle($tenantId, $usuarioId, $creada);
    }

    /**
     * @throws ColaboradorNoEncontradoParaRuta
     * @throws InscripcionNoPermitida
     * @throws FuenteNoDisponibleParaRuta
     */
    private function exigirColaboradorActivo(int $tenantId, int $usuarioId, int $colaboradorId): void
    {
        try {
            $vigentes = $this->colaboradores->vigentesPorIds($tenantId, [$colaboradorId], $this->reloj->hoy($usuarioId)->format('Y-m-d'));
            if (in_array($colaboradorId, $vigentes, true)) {
                return;
            }
            $existe = isset($this->referencias->colaboradores($tenantId, [$colaboradorId])[$colaboradorId]);
        } catch (Throwable) {
            throw new FuenteNoDisponibleParaRuta('Gestión de Personal');
        }

        throw $existe
            ? new InscripcionNoPermitida('El colaborador no está activo en la nómina (sin vínculo laboral vigente).', 'colaborador_no_activo', 'colaborador_id')
            : new ColaboradorNoEncontradoParaRuta;
    }
}
