<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\NodoDeOrganigramaDTO;
use App\Modules\Estructura\Application\DTO\OrganigramaDTO;
use App\Modules\Estructura\Application\Ports\NombresDeUsuariosInterface;
use App\Modules\Estructura\Application\Ports\TenantDelUsuarioInterface;
use App\Modules\Estructura\Domain\Entities\Unidad;
use App\Modules\Estructura\Domain\Exceptions\OrganigramaInconsistente;
use App\Modules\Estructura\Domain\Repositories\PosicionRepositoryInterface;
use App\Modules\Estructura\Domain\Repositories\UnidadRepositoryInterface;
use App\Modules\Estructura\Domain\Services\ComprobadorDeArbol;
use App\Modules\Estructura\Domain\ValueObjects\NodoDePosicion;

final class ObtenerOrganigrama
{
    public function __construct(
        private readonly PosicionRepositoryInterface $posiciones,
        private readonly UnidadRepositoryInterface $unidades,
        private readonly NombresDeUsuariosInterface $usuarios,
        private readonly TenantDelUsuarioInterface $tenants,
        private readonly ComprobadorDeArbol $comprobador,
    ) {}

    /**
     * Posiciones vigentes (nunca las Suprimidas) del tenant de quien consulta, más las unidades activas para el filtro.
     * Antes de responder comprueba que formen un único árbol; no intenta corregir los datos. Es igual para todos los roles.
     *
     * @throws OrganigramaInconsistente
     */
    public function ejecutar(int $userId): OrganigramaDTO
    {
        $tenantId = $this->tenants->tenantDe($userId);
        $vigentes = $tenantId === null ? [] : $this->posiciones->vigentes($tenantId);

        $this->comprobador->comprobar(array_map(
            fn ($posicion) => new NodoDePosicion(
                $posicion->id,
                $posicion->codigo,
                $posicion->supervisor['id'] ?? null,
                $posicion->estado,
            ),
            $vigentes,
        ));

        $ocupantes = array_values(array_unique(array_filter(
            array_map(fn ($posicion) => $posicion->ocupanteId, $vigentes),
            fn ($id) => $id !== null,
        )));
        $nombres = $ocupantes === [] ? [] : $this->usuarios->nombres($ocupantes);

        $activas = array_values(array_filter($this->unidades->todas(), fn (Unidad $unidad) => $unidad->estaActiva()));

        return new OrganigramaDTO(
            array_map(fn ($posicion) => NodoDeOrganigramaDTO::desde($posicion, $nombres), $vigentes),
            array_map(fn (Unidad $unidad) => [
                'id' => $unidad->id,
                'codigo' => $unidad->codigo,
                'nombre' => $unidad->nombre,
                'nivel_arbol' => $unidad->nivel,
                'unidad_padre_id' => $unidad->padre?->id,
            ], $activas),
        );
    }
}
