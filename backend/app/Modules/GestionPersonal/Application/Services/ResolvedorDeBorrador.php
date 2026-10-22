<?php

namespace App\Modules\GestionPersonal\Application\Services;

use App\Modules\GestionPersonal\Domain\Entities\VersionDePolitica;
use App\Modules\GestionPersonal\Domain\Exceptions\BorradorModificado;
use App\Modules\GestionPersonal\Domain\Exceptions\PoliticaNoEncontrada;
use App\Modules\GestionPersonal\Domain\Repositories\PoliticaRepositoryInterface;

/** Localiza el borrador activo de una política: inexistente ⇒ 404; descartado, publicado u obsoleto ⇒ conflicto. */
final class ResolvedorDeBorrador
{
    public function __construct(private readonly PoliticaRepositoryInterface $politicas) {}

    /**
     * @throws PoliticaNoEncontrada
     * @throws BorradorModificado
     */
    public function obtener(int $tenantId, int $politicaId, int $versionId, ?int $versionEsperada = null): VersionDePolitica
    {
        $version = $this->politicas->version($tenantId, $politicaId, $versionId) ?? throw new PoliticaNoEncontrada;

        if (! $version->esBorradorActivo() || ($versionEsperada !== null && $version->version !== $versionEsperada)) {
            throw new BorradorModificado;
        }

        return $version;
    }
}
