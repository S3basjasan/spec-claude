<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Ports\PosicionesAsignablesPort;
use App\Modules\GestionPersonal\Application\Services\PoliticasService;
use App\Modules\GestionPersonal\Application\Services\ResolvedorDeSolicitante;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\IngresoNoVerificable;
use App\Modules\GestionPersonal\Domain\Exceptions\PoliticaNoDisponible;
use App\Modules\GestionPersonal\Domain\Repositories\ActuacionRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\VinculoLaboralRepositoryInterface;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;

/**
 * Datos para preparar un ascenso o traslado: situación actual del colaborador, destinos que admiten asignaciones
 * (de Estructura) y la situación de las políticas de Ascensos y Traslados. No reserva ninguna versión.
 */
final class ObtenerContextoDeMovimiento
{
    public function __construct(
        private readonly ResolvedorDeSolicitante $solicitantes,
        private readonly ActuacionRepositoryInterface $actuaciones,
        private readonly VinculoLaboralRepositoryInterface $vinculos,
        private readonly PosicionesAsignablesPort $posiciones,
        private readonly PoliticasService $politicas,
    ) {}

    /**
     * @return array{colaborador: array<string, mixed>, vinculo_vigente: bool, posicion_actual: array<string, mixed>|null, destinos: array{items: list<array<string, mixed>>, total: int}, politicas: array<string, array<string, mixed>>}
     *
     * @throws ColaboradorNoEncontrado
     * @throws IngresoNoVerificable
     */
    public function ejecutar(ContextoDeTenant $contexto, int $userId, int $colaboradorId, ?string $texto, int $pagina, int $porPagina): array
    {
        $this->solicitantes->administrador($contexto, $userId, $colaboradorId);
        $ficha = $this->actuaciones->fichaResumen($contexto->tenantId, $colaboradorId);

        $hoy = $contexto->hoy();
        $vigente = null;
        foreach ($this->vinculos->vinculosDeFicha($contexto->tenantId, $colaboradorId) as $vinculo) {
            if ($vinculo->periodo()->contiene($hoy)) {
                $vigente = $vinculo;
            }
        }
        $asignacion = $vigente?->asignacionEn($hoy);
        $actual = $asignacion === null ? null : $this->posiciones->describir($asignacion->posicionId);

        $destinos = $this->posiciones->listar($contexto->tenantId, $texto, $pagina, $porPagina);
        $items = array_values(array_filter($destinos['items'], fn (array $p) => $asignacion === null || (int) $p['id'] !== $asignacion->posicionId));

        $ahora = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $politicas = [];
        foreach ([TipoDePolitica::Ascensos, TipoDePolitica::Traslados] as $tipo) {
            try {
                $resultado = $this->politicas->aplicablePara($contexto->tenantId, $tipo->value, $ahora);
                $politicas[$tipo->identificador()] = [
                    'situacion' => $resultado['situacion'],
                    'numero' => $resultado['politica']['numero'] ?? null,
                    'parametros' => $resultado['politica']['parametros'] ?? null,
                ];
            } catch (PoliticaNoDisponible) {
                $politicas[$tipo->identificador()] = ['situacion' => 'NoDisponible', 'numero' => null, 'parametros' => null];
            }
        }

        return [
            'colaborador' => ['id' => $colaboradorId, 'codigo' => $ficha['codigo'] ?? null, 'nombre' => $ficha['nombre'] ?? null],
            'vinculo_vigente' => $vigente !== null,
            'posicion_actual' => $actual === null ? null : ['id' => $actual['id'], 'puesto' => $actual['puesto'], 'unidad' => $actual['unidad']],
            'destinos' => ['items' => $items, 'total' => $destinos['total']],
            'politicas' => $politicas,
        ];
    }
}
