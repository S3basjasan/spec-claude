<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Services\ResolvedorDeSolicitante;
use App\Modules\GestionPersonal\Domain\Repositories\DocumentoRepositoryInterface;

/**
 * Documentos que pueden referenciarse como respaldo de una solicitud. El Administrador ve los disponibles de la
 * ficha; el Colaborador solo los suyos visibles y únicamente con permiso documental. Nunca expone claves de objeto.
 */
final class ListarDocumentosReferenciables
{
    private const MAXIMO = 100;

    public function __construct(
        private readonly ResolvedorDeSolicitante $solicitantes,
        private readonly DocumentoRepositoryInterface $documentos,
    ) {}

    /** @return list<array{id: int, titulo: string}> */
    public function comoAdministrador(ContextoDeTenant $contexto, int $userId, int $colaboradorId): array
    {
        $solicitante = $this->solicitantes->administrador($contexto, $userId, $colaboradorId);

        return $this->listar($contexto->tenantId, $solicitante->colaboradorId, false);
    }

    /** @return list<array{id: int, titulo: string}> */
    public function comoColaborador(ContextoDeTenant $contexto, int $userId): array
    {
        $solicitante = $this->solicitantes->colaborador($contexto, $userId);
        if (! $solicitante->puedeConsultarDocumentos) {
            return [];
        }

        return $this->listar($contexto->tenantId, $solicitante->colaboradorId, true);
    }

    /** @return list<array{id: int, titulo: string}> */
    private function listar(int $tenantId, int $colaboradorId, bool $soloVisibles): array
    {
        $resultado = $this->documentos->listar($tenantId, $colaboradorId, null, null, 'Disponible', $soloVisibles, 1, self::MAXIMO);

        return array_map(fn ($d) => ['id' => $d->id, 'titulo' => $d->titulo], $resultado['items']);
    }
}
