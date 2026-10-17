<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\DTO\CertificadoDTO;
use App\Modules\Desarrollo\Application\Ports\CertificadoPdfInterface;
use App\Modules\Desarrollo\Application\Ports\CompetenciasInterface;
use App\Modules\Desarrollo\Application\Ports\NombreDeColaboradorInterface;
use App\Modules\Desarrollo\Application\Services\AccesoDeColaborador;
use App\Modules\Desarrollo\Domain\Exceptions\MicroNoEncontrado;
use App\Modules\Desarrollo\Domain\Repositories\MicrocontenidoRepositoryInterface;
use App\Modules\Desarrollo\Domain\Repositories\ProgresoMicroRepositoryInterface;

/**
 * Certificado de finalización en PDF de un contenido completado (también si luego se archivó). No se guarda: se genera
 * bajo demanda con las competencias vinculadas al emitirlo.
 */
final class GenerarCertificado
{
    public function __construct(
        private readonly AccesoDeColaborador $acceso,
        private readonly MicrocontenidoRepositoryInterface $contenidos,
        private readonly ProgresoMicroRepositoryInterface $progresos,
        private readonly CompetenciasInterface $competencias,
        private readonly NombreDeColaboradorInterface $colaboradores,
        private readonly CertificadoPdfInterface $pdf,
    ) {}

    /**
     * @return array{contenido: string, nombre: string}
     */
    public function ejecutar(int $microcontenidoId, int $usuarioId): array
    {
        $cuenta = $this->acceso->cuenta($usuarioId);
        $progreso = $this->progresos->progresoDe($microcontenidoId, $cuenta['colaborador_id']);
        if ($progreso === null || ! $progreso->estaCompletado()) {
            throw new MicroNoEncontrado;
        }

        $micro = $this->contenidos->obtener($microcontenidoId) ?? throw new MicroNoEncontrado;
        $porId = [];
        if ($micro->competenciaIds !== []) {
            foreach ($this->competencias->porIds($micro->competenciaIds) as $c) {
                $porId[(int) $c['id']] = $c;
            }
        }
        $nombres = [];
        foreach ($micro->competenciaIds as $id) {
            if (isset($porId[$id])) {
                $nombres[] = (string) $porId[$id]['nombre'];
            }
        }

        $certificado = new CertificadoDTO(
            $micro->codigo,
            $this->colaboradores->nombreDe($cuenta['tenant_id'], $cuenta['colaborador_id']) ?? 'Colaborador',
            $micro->titulo,
            $nombres,
            $micro->duracionMinutos,
            (string) ($progreso->fechaCompletado ?? $progreso->fechaInicio),
        );

        return ['contenido' => $this->pdf->renderizar($certificado), 'nombre' => 'certificado-'.$micro->codigo.'.pdf'];
    }
}
