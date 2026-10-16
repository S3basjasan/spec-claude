<?php

namespace App\Modules\GestionPersonal\Application\Services;

use App\Modules\GestionPersonal\Application\Ports\AccesosDeCuentaPort;
use App\Modules\GestionPersonal\Application\Ports\ContextoDeTenantPort;
use App\Modules\GestionPersonal\Application\Ports\LectorDeDesarrolloPort;
use App\Modules\GestionPersonal\Application\UseCases\RegistrarFicha;
use App\Modules\GestionPersonal\Domain\Entities\Colaborador;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\InformacionNoDisponible;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\VinculoLaboralRepositoryInterface;
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
        private readonly VinculoLaboralRepositoryInterface $vinculos,
        private readonly LectorDeDesarrolloPort $desarrollo,
    ) {}

    /**
     * Posiciones, entre las dadas, con una asignación laboral vigente o futura en la fecha (fin nulo o posterior).
     * Lo consumen Estructura (desactivar puestos y unidades) y futuras funcionalidades de movimientos.
     *
     * @param  array<int, int>  $posicionIds
     * @return array<int, int>
     */
    public function posicionesConAsignacionVigenteOFutura(array $posicionIds, string $fecha): array
    {
        return $this->vinculos->posicionesConAsignacionVigenteOFutura(array_values(array_map('intval', $posicionIds)), $fecha);
    }

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

    /**
     * Colaboradores con vínculo laboral vigente en la fecha, buscados por nombres, apellidos o código (solo lectura).
     * Lo consume el Plan de formación para elegir participantes.
     *
     * @return array{items: list<array{id: int, codigo: string, nombre: string}>, total: int}
     */
    public function buscarColaboradoresVigentes(int $tenantId, ?string $texto, int $pagina, int $porPagina, string $fecha): array
    {
        $vigentes = $this->vinculos->fichasConVinculoVigente($tenantId, null, $fecha);
        if ($vigentes === []) {
            return ['items' => [], 'total' => 0];
        }

        $busqueda = $this->normalizador->busquedaDeTexto((string) $texto);
        $tokens = $busqueda === '' ? [] : array_values(array_filter(preg_split('/\s+/', $busqueda) ?: []));
        $pagina = $this->colaboradores->listar($tenantId, $tokens, null, null, $vigentes, null, max(1, $pagina), max(1, $porPagina));

        return [
            'items' => array_map(fn (Colaborador $c) => [
                'id' => $c->id,
                'codigo' => $c->codigo,
                'nombre' => trim($c->datos['nombres'].' '.$c->datos['apellidos']),
            ], $pagina['items']),
            'total' => $pagina['total'],
        ];
    }

    /**
     * Ids, entre los dados, de colaboradores con vínculo laboral vigente en la fecha (solo lectura).
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    public function vigentesPorIds(int $tenantId, array $ids, string $fecha): array
    {
        return $this->vinculos->fichasConVinculoVigente($tenantId, array_values(array_map('intval', $ids)), $fecha);
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

    /**
     * Formación, experiencia y movilidad por colaborador (GP-06). Sin N+1; ids de otro tenant se ignoran.
     *
     * @param  list<int>  $colaboradorIds
     * @return array<int, array{formaciones: list<array<string, mixed>>, experiencias: list<array<string, mixed>>, movilidad: array<string, mixed>}>
     *
     * @throws InformacionNoDisponible Si la lectura falla; el llamador decide su degradación.
     */
    public function getDesarrollo(int $tenantId, array $colaboradorIds): array
    {
        $lectura = $this->desarrollo->deColaboradores($tenantId, $colaboradorIds);

        if (! $lectura->estaDisponible()) {
            throw new InformacionNoDisponible;
        }

        return $lectura->datos;
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
