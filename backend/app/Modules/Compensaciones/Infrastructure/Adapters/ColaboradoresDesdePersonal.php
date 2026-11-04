<?php

namespace App\Modules\Compensaciones\Infrastructure\Adapters;

use App\Modules\Compensaciones\Application\Ports\ColaboradoresPort;
use App\Modules\Compensaciones\Application\Ports\EmpresaActualPort;
use App\Modules\GestionPersonal\Presentation\Facades\PersonalFacade;

/** Lee colaboradores solo a través de la Facade pública de Gestión de Personal. */
class ColaboradoresDesdePersonal implements ColaboradoresPort
{
    public function __construct(private readonly EmpresaActualPort $empresa) {}

    public function cuentaDeUsuario(int $usuarioId): ?int
    {
        $cuenta = PersonalFacade::getCuentaDeUsuario($usuarioId);

        return $cuenta === null ? null : (int) $cuenta['colaborador_id'];
    }

    public function nombres(array $ids): array
    {
        $resultado = [];

        foreach (PersonalFacade::getColaboradoresPorIds($this->empresa->tenantId(), array_values(array_unique($ids))) as $ficha) {
            $resultado[(int) $ficha['id']] = [
                'id' => (int) $ficha['id'],
                'codigo' => (string) ($ficha['codigo'] ?? ''),
                'nombre' => trim(($ficha['nombres'] ?? '').' '.($ficha['apellidos'] ?? '')),
            ];
        }

        return $resultado;
    }

    public function vigentes(array $ids, string $fecha): array
    {
        return array_values(array_map(
            'intval',
            PersonalFacade::vigentesPorIds($this->empresa->tenantId(), array_values(array_unique($ids)), $fecha),
        ));
    }

    public function unidadActual(array $ids, string $fecha): array
    {
        $resultado = [];

        foreach (PersonalFacade::getDepartamentoActual($this->empresa->tenantId(), array_values(array_unique($ids)), $fecha) as $colaboradorId => $unidadId) {
            $resultado[(int) $colaboradorId] = $unidadId === null ? null : (int) $unidadId;
        }

        return $resultado;
    }
}
