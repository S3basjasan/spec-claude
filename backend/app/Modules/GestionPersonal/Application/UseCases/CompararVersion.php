<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\VersionDTO;
use App\Modules\GestionPersonal\Application\Ports\RelojPort;
use App\Modules\GestionPersonal\Application\Services\ArmadorDeVersion;
use App\Modules\GestionPersonal\Domain\Exceptions\PoliticaNoEncontrada;
use App\Modules\GestionPersonal\Domain\Repositories\PoliticaRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\ClasificadorDeVersiones;
use App\Modules\GestionPersonal\Domain\Services\DiferenciasDePolitica;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeVersion;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;

/** Compara una versión con la vigente real del tipo (no con la versión de la que nació). */
final class CompararVersion
{
    public function __construct(
        private readonly PoliticaRepositoryInterface $politicas,
        private readonly ClasificadorDeVersiones $clasificador,
        private readonly DiferenciasDePolitica $diferencias,
        private readonly ArmadorDeVersion $armador,
        private readonly RelojPort $reloj,
    ) {}

    /**
     * @return array{contra: VersionDTO|null, diferencias: list<array{clave: string, etiqueta: string, anterior: mixed, nuevo: mixed}>}
     *
     * @throws PoliticaNoEncontrada
     */
    public function ejecutar(ContextoDeTenant $contexto, TipoDePolitica $tipo, int $versionId): array
    {
        $ahora = $this->reloj->ahora();
        $cabecera = $this->politicas->cabecera($contexto->tenantId, $tipo) ?? throw new PoliticaNoEncontrada;
        $version = $this->politicas->version($contexto->tenantId, $cabecera->id, $versionId);
        if ($version === null || $version->descartadaEn !== null) {
            throw new PoliticaNoEncontrada;
        }

        $versiones = $this->politicas->versionesDe($contexto->tenantId, [$cabecera->id])[$cabecera->id] ?? [];
        $vigente = $this->clasificador->vigente($versiones, $ahora);

        return [
            'contra' => $vigente === null ? null : $this->armador->dto($vigente, EstadoDeVersion::Vigente, $contexto->zonaHoraria),
            'diferencias' => $this->diferencias->entre(
                $tipo,
                ['descripcion' => $version->descripcion, 'parametros' => $version->parametros],
                $vigente === null ? null : ['descripcion' => $vigente->descripcion, 'parametros' => $vigente->parametros],
            ),
        ];
    }
}
