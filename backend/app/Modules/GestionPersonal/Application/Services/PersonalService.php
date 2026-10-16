<?php

namespace App\Modules\GestionPersonal\Application\Services;

use App\Modules\GestionPersonal\Application\Ports\AccesosDeCuentaPort;
use App\Modules\GestionPersonal\Application\Ports\ContextoDeTenantPort;
use App\Modules\GestionPersonal\Application\UseCases\RegistrarFicha;
use App\Modules\GestionPersonal\Domain\Entities\Colaborador;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\NormalizadorDeTexto;

/**
 * Servicio inter-módulos detrás de PersonalFacade. Devuelve arreglos; los modelos no salen del módulo.
 */
class PersonalService
{
    public function __construct(
        private readonly ColaboradorRepositoryInterface $colaboradores,
        private readonly NormalizadorDeTexto $normalizador,
        private readonly ContextoDeTenantPort $contextos,
        private readonly RegistrarFicha $registrar,
        private readonly AccesosDeCuentaPort $accesos,
    ) {}

    /** @return array<string, mixed>|null */
    public function getColaborador(int $tenantId, int $id): ?array
    {
        $colaborador = $this->colaboradores->porId($tenantId, $id);

        return $colaborador === null ? null : $this->aArreglo($colaborador);
    }

    /**
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    public function getColaboradoresPorIds(int $tenantId, array $ids): array
    {
        $filas = [];
        foreach (array_unique($ids) as $id) {
            $colaborador = $this->colaboradores->porId($tenantId, (int) $id);
            if ($colaborador !== null) {
                $filas[] = $this->aArreglo($colaborador);
            }
        }

        return $filas;
    }

    /**
     * Ficha asociada a la cuenta de un usuario, o null si no tiene (solo lectura).
     *
     * @return array{tenant_id: int, colaborador_id: int}|null
     */
    public function getCuentaDeUsuario(int $usuarioId): ?array
    {
        $acceso = $this->accesos->deCuenta($usuarioId);

        return $acceso === null ? null : ['tenant_id' => $acceso->tenantId, 'colaborador_id' => $acceso->colaboradorId];
    }

    /** @return array<string, mixed>|null */
    public function buscarPorIdentificacion(int $tenantId, string $tipo, string $numero): ?array
    {
        $colaborador = $this->colaboradores->porIdentificacion(
            $tenantId,
            $tipo,
            $this->normalizador->claveDeIdentificacion($numero),
        );

        return $colaborador === null ? null : $this->aArreglo($colaborador);
    }

    /**
     * Alta reutilizable por GP-07: mismas validaciones, idempotencia y errores de dominio que el alta manual.
     * El llamador debe haber verificado al actor.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     *
     * @throws ColaboradorNoEncontrado Si el actor no pertenece al tenant indicado.
     */
    public function registrarFicha(int $tenantId, int $actorId, string $claveDeOperacion, array $datos): array
    {
        $contexto = $this->contextos->deCuenta($actorId);
        if ($contexto === null || $contexto->tenantId !== $tenantId) {
            throw new ColaboradorNoEncontrado;
        }

        $resultado = $this->registrar->ejecutar($contexto, $actorId, $claveDeOperacion, $datos);

        return ['colaborador_id' => $resultado->datos['id'], 'codigo' => $resultado->datos['codigo'], 'creado' => $resultado->creado];
    }

    /** @return array<string, mixed> */
    private function aArreglo(Colaborador $colaborador): array
    {
        return [
            'id' => $colaborador->id,
            'codigo' => $colaborador->codigo,
            'tipo_identificacion' => $colaborador->datos['tipo_identificacion'],
            'numero_identificacion' => $colaborador->datos['numero_identificacion'],
            'nombres' => $colaborador->datos['nombres'],
            'apellidos' => $colaborador->datos['apellidos'],
            'version' => $colaborador->version,
        ];
    }
}
