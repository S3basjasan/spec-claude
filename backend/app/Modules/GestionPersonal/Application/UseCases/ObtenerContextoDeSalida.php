<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Ports\RelojPort;
use App\Modules\GestionPersonal\Application\Services\PoliticasService;
use App\Modules\GestionPersonal\Application\Services\ResolvedorDeSolicitante;
use App\Modules\GestionPersonal\Domain\Exceptions\PoliticaNoDisponible;
use App\Modules\GestionPersonal\Domain\Repositories\ExpedienteRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\VinculoLaboralRepositoryInterface;
use App\Modules\GestionPersonal\Domain\ValueObjects\MotivoDeSalida;

/**
 * Datos para preparar una salida: vínculo vigente, motivos del catálogo y situación y plantilla de la política de
 * Offboarding. Es informativa: no reserva ninguna versión de política.
 */
final class ObtenerContextoDeSalida
{
    public function __construct(
        private readonly ResolvedorDeSolicitante $solicitantes,
        private readonly ExpedienteRepositoryInterface $expedientes,
        private readonly VinculoLaboralRepositoryInterface $vinculos,
        private readonly PoliticasService $politicas,
        private readonly RelojPort $reloj,
    ) {}

    /** @return array<string, mixed> */
    public function ejecutar(ContextoDeTenant $contexto, int $userId, int $colaboradorId): array
    {
        $this->solicitantes->administrador($contexto, $userId, $colaboradorId);
        $ficha = $this->expedientes->fichaResumen($contexto->tenantId, $colaboradorId);
        $hoy = $contexto->hoy();

        $vigente = null;
        foreach ($this->vinculos->vinculosDeFicha($contexto->tenantId, $colaboradorId) as $vinculo) {
            if ($vinculo->periodo()->contiene($hoy)) {
                $vigente = $vinculo;
            }
        }

        $existente = $this->expedientes->vigenteDeColaborador($contexto->tenantId, $colaboradorId);

        try {
            $resultado = $this->politicas->aplicablePara($contexto->tenantId, 'Offboarding', $this->reloj->ahora());
            $politica = [
                'situacion' => $resultado['situacion'],
                'numero' => $resultado['politica']['numero'] ?? null,
                'tareas' => array_values((array) ($resultado['politica']['parametros']['tareas'] ?? [])),
            ];
        } catch (PoliticaNoDisponible) {
            $politica = ['situacion' => 'NoDisponible', 'numero' => null, 'tareas' => []];
        }

        return [
            'colaborador' => ['id' => $colaboradorId, 'codigo' => $ficha['codigo'] ?? null, 'nombre' => $ficha['nombre'] ?? null],
            'hoy' => $hoy,
            'vinculo_vigente' => $vigente === null ? null : ['id' => $vigente->id, 'fecha_ingreso' => $vigente->fechaIngreso, 'fecha_salida' => $vigente->fechaSalida],
            'salida_existente' => $existente === null ? null : ['id' => $existente->id, 'estado' => $existente->estado->value, 'fecha_efectiva' => $existente->fechaEfectiva],
            'motivos' => array_map(fn (MotivoDeSalida $m) => ['clave' => $m->value, 'etiqueta' => $m->etiqueta()], MotivoDeSalida::cases()),
            'politica' => $politica,
        ];
    }
}
