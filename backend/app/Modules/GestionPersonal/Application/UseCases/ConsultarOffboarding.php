<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Services\ArmadorDeExpediente;
use App\Modules\GestionPersonal\Application\Services\ResolvedorDeSolicitante;
use App\Modules\GestionPersonal\Domain\Exceptions\ExpedienteNoEncontrado;
use App\Modules\GestionPersonal\Domain\Repositories\ExpedienteRepositoryInterface;

/** Lecturas de expedientes: listado, resumen y detalle del Administrador y la vista comunicable del Colaborador. */
final class ConsultarOffboarding
{
    public function __construct(
        private readonly ExpedienteRepositoryInterface $expedientes,
        private readonly ArmadorDeExpediente $armador,
        private readonly ResolvedorDeSolicitante $solicitantes,
    ) {}

    /**
     * @param  array{estado?: string|null, cierre?: string|null, motivo?: string|null, entrevista?: string|null, colaborador_id?: int|null, desde?: string|null, hasta?: string|null}  $filtros
     * @return array{items: list<array<string, mixed>>, total: int, pagina: int, por_pagina: int}
     */
    public function listar(ContextoDeTenant $contexto, array $filtros, int $pagina, int $porPagina): array
    {
        $resultado = $this->expedientes->listar($contexto->tenantId, $filtros, $pagina, $porPagina);

        return ['items' => $resultado['items'], 'total' => $resultado['total'], 'pagina' => $pagina, 'por_pagina' => $porPagina];
    }

    /** @return array<string, int> */
    public function resumen(ContextoDeTenant $contexto): array
    {
        return $this->expedientes->resumen($contexto->tenantId);
    }

    /** @return array<string, mixed> */
    public function detalle(ContextoDeTenant $contexto, int $id): array
    {
        $expediente = $this->expedientes->porId($contexto->tenantId, $id) ?? throw new ExpedienteNoEncontrado;

        return $this->armador->paraAdministrador($contexto, $expediente);
    }

    /** @return array<string, mixed> */
    public function miDesvinculacion(ContextoDeTenant $contexto, int $userId): array
    {
        $solicitante = $this->solicitantes->colaborador($contexto, $userId);
        $expediente = $this->expedientes->vigenteDeColaborador($contexto->tenantId, $solicitante->colaboradorId) ?? throw new ExpedienteNoEncontrado;

        return $this->armador->paraColaborador($expediente);
    }

    /** @return array<string, mixed> */
    public function miEntrevista(ContextoDeTenant $contexto, int $userId): array
    {
        $solicitante = $this->solicitantes->colaborador($contexto, $userId);
        $expediente = $this->expedientes->vigenteDeColaborador($contexto->tenantId, $solicitante->colaboradorId) ?? throw new ExpedienteNoEncontrado;

        return $this->armador->entrevistaDelColaborador($expediente);
    }
}
