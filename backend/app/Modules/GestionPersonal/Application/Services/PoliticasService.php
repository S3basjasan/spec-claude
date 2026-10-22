<?php

namespace App\Modules\GestionPersonal\Application\Services;

use App\Modules\GestionPersonal\Application\DTO\PoliticaAplicableDTO;
use App\Modules\GestionPersonal\Application\Ports\TransaccionPort;
use App\Modules\GestionPersonal\Domain\Exceptions\PoliticaNoAdmiteSolicitudes;
use App\Modules\GestionPersonal\Domain\Exceptions\PoliticaNoConfigurada;
use App\Modules\GestionPersonal\Domain\Exceptions\PoliticaNoDisponible;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;

/**
 * Servicio inter-módulos detrás de PoliticasFacade (contracts/consumo-de-politicas.md). Devuelve arreglos; las
 * entidades y los modelos no salen del módulo, y los consumidores nunca tocan las tablas de configuración.
 */
class PoliticasService
{
    public function __construct(
        private readonly ResolvedorDePoliticaAplicable $resolvedor,
        private readonly TransaccionPort $transaccion,
    ) {}

    /**
     * Condiciones aplicables en el instante, sin bloquear ni reservar (por ejemplo, para mostrarlas antes de presentar).
     *
     * @return array{situacion: string, politica: array<string, mixed>|null}
     *
     * @throws PoliticaNoDisponible
     */
    public function aplicablePara(int $tenantId, string $tipo, DateTimeImmutable $instante): array
    {
        $resultado = $this->resolvedor->aplicable($tenantId, $this->tipo($tipo), $instante);

        return ['situacion' => $resultado['situacion'], 'politica' => $resultado['politica'] === null ? null : $this->aArreglo($resultado['politica'])];
    }

    /**
     * Elige la versión al confirmar la presentación, dentro de la transacción del consumidor.
     *
     * @return array<string, mixed>
     *
     * @throws LogicException Si no hay una transacción abierta.
     * @throws PoliticaNoConfigurada
     * @throws PoliticaNoAdmiteSolicitudes
     * @throws PoliticaNoDisponible
     */
    public function seleccionarParaPresentar(int $tenantId, string $tipo, DateTimeImmutable $instante): array
    {
        if (! $this->transaccion->enCurso()) {
            throw new LogicException('La política debe seleccionarse dentro de la transacción que registra la solicitud.');
        }

        return $this->aArreglo($this->resolvedor->seleccionarParaPresentar($tenantId, $this->tipo($tipo), $instante));
    }

    private function tipo(string $tipo): TipoDePolitica
    {
        return TipoDePolitica::tryFrom($tipo) ?? throw new InvalidArgumentException("Tipo de política desconocido: {$tipo}");
    }

    /** @return array<string, mixed> */
    private function aArreglo(PoliticaAplicableDTO $dto): array
    {
        return [
            'version_id' => $dto->versionId,
            'numero' => $dto->numero,
            'tipo' => $dto->tipo->value,
            'inicio_vigencia' => $dto->inicioVigencia->format('Y-m-d\TH:i:s\Z'),
            'parametros' => $dto->parametros,
            'condiciones' => $dto->condiciones,
            'hash' => $dto->hash,
        ];
    }
}
