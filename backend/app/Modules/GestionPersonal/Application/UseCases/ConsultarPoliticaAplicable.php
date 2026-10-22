<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\PoliticaAplicableDTO;
use App\Modules\GestionPersonal\Application\Ports\RelojPort;
use App\Modules\GestionPersonal\Application\Services\ResolvedorDePoliticaAplicable;
use App\Modules\GestionPersonal\Domain\Exceptions\PoliticaNoDisponible;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;

/** Condiciones aplicables ahora a un tipo, para cualquier usuario autenticado del tenant (lectura informativa). */
final class ConsultarPoliticaAplicable
{
    public function __construct(
        private readonly ResolvedorDePoliticaAplicable $resolvedor,
        private readonly RelojPort $reloj,
    ) {}

    /**
     * @return array{situacion: string, politica: PoliticaAplicableDTO|null}
     *
     * @throws PoliticaNoDisponible
     */
    public function ejecutar(ContextoDeTenant $contexto, TipoDePolitica $tipo): array
    {
        return $this->resolvedor->aplicable($contexto->tenantId, $tipo, $this->reloj->ahora());
    }
}
