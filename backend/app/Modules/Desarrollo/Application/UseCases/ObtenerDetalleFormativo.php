<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\DTO\AreaDTO;
use App\Modules\Desarrollo\Application\DTO\DetalleFormativoDTO;
use App\Modules\Desarrollo\Application\DTO\RecursoDTO;
use App\Modules\Desarrollo\Application\Ports\BrechasActivasInterface;
use App\Modules\Desarrollo\Application\Ports\CompetenciasInterface;
use App\Modules\Desarrollo\Application\Ports\NivelesDeColaboradorInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeUsuarioInterface;
use App\Modules\Desarrollo\Domain\Exceptions\CompetenciaNoEncontrada;
use App\Modules\Desarrollo\Domain\Repositories\CatalogoFormativoRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\RecursoFormativo;
use Throwable;

/**
 * Detalle formativo de una competencia. El administrador ve también los recursos archivados (señalados), la
 * versión y quién configuró por última vez; el colaborador solo ve recursos disponibles y su propio nivel.
 */
final class ObtenerDetalleFormativo
{
    public function __construct(
        private readonly CompetenciasInterface $competencias,
        private readonly CatalogoFormativoRepositoryInterface $repositorio,
        private readonly NivelesDeColaboradorInterface $niveles,
        private readonly BrechasActivasInterface $brechas,
        private readonly NombresDeUsuarioInterface $nombres,
    ) {}

    /**
     * @throws CompetenciaNoEncontrada
     */
    public function ejecutar(int $competenciaId, bool $esAdmin, int $usuarioId): DetalleFormativoDTO
    {
        $competencia = $this->competencias->obtener($competenciaId);
        if ($competencia === null || (! $esAdmin && $competencia['estado'] !== 'Activo')) {
            throw new CompetenciaNoEncontrada;
        }

        $config = $this->repositorio->configuracionDe($competenciaId);
        $cursos = $esAdmin ? $config->cursos : $config->cursosDisponibles();
        $micros = $esAdmin ? $config->microcontenidos : $config->microcontenidosDisponibles();
        $aDto = fn (RecursoFormativo $r) => RecursoDTO::desde($r);

        $ultima = null;
        $nivelActual = null;
        $nivelRequerido = null;

        if ($esAdmin) {
            if ($config->actualizadoPor !== null && $config->actualizadoEn !== null) {
                $nombre = $this->nombres->nombresDe([$config->actualizadoPor])[$config->actualizadoPor] ?? 'Usuario eliminado';
                $ultima = ['usuario' => $nombre, 'fecha' => $config->actualizadoEn];
            }
        } else {
            $nivelActual = $this->nivelDe($usuarioId, $competenciaId);
            $nivelRequerido = $this->nivelRequeridoDe($usuarioId, $competenciaId);
        }

        return new DetalleFormativoDTO(
            $competencia,
            $config->areaFormativaId === null ? null : new AreaDTO($config->areaFormativaId, (string) $config->areaNombre),
            array_map($aDto, $cursos),
            array_map($aDto, $micros),
            $esAdmin,
            $config->version,
            $ultima,
            $nivelActual,
            $nivelRequerido,
        );
    }

    private function nivelDe(int $usuarioId, int $competenciaId): ?string
    {
        try {
            return $this->niveles->nivelesDe($usuarioId)[$competenciaId] ?? null;
        } catch (Throwable) {
            return null;
        }
    }

    private function nivelRequeridoDe(int $usuarioId, int $competenciaId): ?string
    {
        try {
            foreach ($this->brechas->brechasDe($usuarioId) as $brecha) {
                if ($brecha['competencia_id'] === $competenciaId) {
                    return $brecha['nivel_requerido'];
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
