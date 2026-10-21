<?php

namespace App\Modules\Estructura\Application\Services;

use App\Modules\Estructura\Application\Ports\NombresDeUsuariosInterface;
use App\Modules\Estructura\Domain\Entities\Competencia;

/**
 * Datos de auditoría de una competencia (quién y cuándo la creó y la modificó). Solo los ve el administrador;
 * los nombres salen del puerto de usuarios, sin consultar tablas de otro módulo.
 */
final class AuditoriaDeCompetencia
{
    public function __construct(private readonly NombresDeUsuariosInterface $usuarios) {}

    /**
     * @return array{created_by: ?string, created_at: ?string, updated_by: ?string, updated_at: ?string}
     */
    public function para(Competencia $competencia): array
    {
        $ids = array_values(array_filter([$competencia->creadoPor, $competencia->modificadoPor], fn ($id) => $id !== null));
        $nombres = $this->usuarios->nombres($ids);

        return [
            'created_by' => $competencia->creadoPor === null ? null : ($nombres[$competencia->creadoPor] ?? null),
            'created_at' => $competencia->creadaEn,
            'updated_by' => $competencia->modificadoPor === null ? null : ($nombres[$competencia->modificadoPor] ?? null),
            'updated_at' => $competencia->modificadaEn,
        ];
    }
}
