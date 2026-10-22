<?php

namespace App\Modules\GestionPersonal\Application\Services;

use App\Modules\GestionPersonal\Application\DTO\PoliticaAplicableDTO;
use App\Modules\GestionPersonal\Domain\Entities\VersionDePolitica;
use App\Modules\GestionPersonal\Domain\Exceptions\PoliticaNoAdmiteSolicitudes;
use App\Modules\GestionPersonal\Domain\Exceptions\PoliticaNoConfigurada;
use App\Modules\GestionPersonal\Domain\Exceptions\PoliticaNoDisponible;
use App\Modules\GestionPersonal\Domain\Repositories\PoliticaRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\ClasificadorDeVersiones;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;
use DateTimeImmutable;
use Throwable;

/**
 * Versión de política aplicable a un tipo en un instante. Cualquier fallo de lectura es `PoliticaNoDisponible`:
 * nunca se interpreta como ausencia de requisitos. Una versión Programada no se usa antes de su inicio.
 */
final class ResolvedorDePoliticaAplicable
{
    public function __construct(
        private readonly PoliticaRepositoryInterface $politicas,
        private readonly ClasificadorDeVersiones $clasificador,
    ) {}

    /**
     * Lectura informativa, sin bloqueo ni reserva.
     *
     * @return array{situacion: 'SinConfigurar'|'Vigente'|'NoAdmiteSolicitudes', politica: PoliticaAplicableDTO|null}
     *
     * @throws PoliticaNoDisponible
     */
    public function aplicable(int $tenantId, TipoDePolitica $tipo, DateTimeImmutable $instante): array
    {
        try {
            $cabecera = $this->politicas->cabecera($tenantId, $tipo);
            $versiones = $cabecera === null ? [] : ($this->politicas->versionesDe($tenantId, [$cabecera->id])[$cabecera->id] ?? []);

            return $this->resolver($tipo, $versiones, $instante);
        } catch (PoliticaNoDisponible $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            throw new PoliticaNoDisponible;
        }
    }

    /**
     * Elige la versión al confirmar la presentación de una solicitud: toma el bloqueo de la cabecera del tipo (debe
     * ejecutarse dentro de la transacción del consumidor).
     *
     * @throws PoliticaNoConfigurada
     * @throws PoliticaNoAdmiteSolicitudes
     * @throws PoliticaNoDisponible
     */
    public function seleccionarParaPresentar(int $tenantId, TipoDePolitica $tipo, DateTimeImmutable $instante): PoliticaAplicableDTO
    {
        try {
            $cabecera = $this->politicas->cabecera($tenantId, $tipo);
            if ($cabecera === null) {
                throw new PoliticaNoConfigurada;
            }

            $cabecera = $this->politicas->bloquear($tenantId, $tipo);
            $versiones = $this->politicas->versionesDe($tenantId, [$cabecera->id])[$cabecera->id] ?? [];
            $resultado = $this->resolver($tipo, $versiones, $instante);
        } catch (PoliticaNoConfigurada|PoliticaNoDisponible $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            throw new PoliticaNoDisponible;
        }

        return match ($resultado['situacion']) {
            'SinConfigurar' => throw new PoliticaNoConfigurada,
            'NoAdmiteSolicitudes' => throw new PoliticaNoAdmiteSolicitudes,
            default => $resultado['politica'] ?? throw new PoliticaNoDisponible,
        };
    }

    /**
     * @param  list<VersionDePolitica>  $versiones
     * @return array{situacion: 'SinConfigurar'|'Vigente'|'NoAdmiteSolicitudes', politica: PoliticaAplicableDTO|null}
     */
    private function resolver(TipoDePolitica $tipo, array $versiones, DateTimeImmutable $instante): array
    {
        $vigente = $this->clasificador->vigente($versiones, $instante);
        if ($vigente === null) {
            return ['situacion' => 'SinConfigurar', 'politica' => null];
        }

        if ($vigente->condiciones === null || $vigente->condicionesHash === null || $vigente->numero === null || $vigente->inicioVigencia === null) {
            throw new PoliticaNoDisponible;
        }

        $dto = new PoliticaAplicableDTO($vigente->id, $vigente->numero, $tipo, $vigente->inicioVigencia, (array) ($vigente->condiciones['parametros'] ?? $vigente->parametros), $vigente->condiciones, $vigente->condicionesHash);

        return ['situacion' => $dto->admiteSolicitudes() ? 'Vigente' : 'NoAdmiteSolicitudes', 'politica' => $dto];
    }
}
