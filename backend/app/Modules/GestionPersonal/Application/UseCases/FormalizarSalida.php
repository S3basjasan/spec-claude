<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\ResultadoDeSalida;
use App\Modules\GestionPersonal\Application\Ports\RelojPort;
use App\Modules\GestionPersonal\Application\Services\ControlDeOffboarding;
use App\Modules\GestionPersonal\Application\Services\PoliticasService;
use App\Modules\GestionPersonal\Application\Services\ResolvedorDeSolicitante;
use App\Modules\GestionPersonal\Application\Services\ValidadorDeRespaldos;
use App\Modules\GestionPersonal\Domain\Entities\VinculoLaboral;
use App\Modules\GestionPersonal\Domain\Exceptions\ConflictoDeUnicidad;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\FechaEfectivaInvalida;
use App\Modules\GestionPersonal\Domain\Exceptions\SalidaYaFormalizada;
use App\Modules\GestionPersonal\Domain\Exceptions\SinVinculoVigente;
use App\Modules\GestionPersonal\Domain\Repositories\ExpedienteRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\VinculoLaboralRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\ReglasDeSalida;
use Illuminate\Support\Carbon;

/**
 * El Administrador formaliza la salida de un colaborador con vínculo vigente. La política de Offboarding se fija con el
 * mecanismo de coordinación de Configuración de políticas dentro de la misma transacción, bajo el bloqueo de la ficha, y
 * su plantilla de tareas se copia al expediente. Formalizar nunca modifica el vínculo ni la asignación.
 */
final class FormalizarSalida
{
    public function __construct(
        private readonly ResolvedorDeSolicitante $solicitantes,
        private readonly ExpedienteRepositoryInterface $expedientes,
        private readonly VinculoLaboralRepositoryInterface $vinculos,
        private readonly PoliticasService $politicas,
        private readonly ValidadorDeRespaldos $respaldos,
        private readonly ControlDeOffboarding $control,
        private readonly ReglasDeSalida $reglas,
        private readonly RelojPort $reloj,
    ) {}

    /**
     * @param  list<mixed>  $documentoIds
     *
     * @throws DatosInvalidos
     * @throws FechaEfectivaInvalida
     * @throws SinVinculoVigente
     * @throws SalidaYaFormalizada
     */
    public function ejecutar(ContextoDeTenant $contexto, int $userId, int $colaboradorId, mixed $motivo, mixed $fechaEfectiva, array $documentoIds, ?string $claveDeOperacion): ResultadoDeSalida
    {
        $clave = $this->control->normalizar($claveDeOperacion);
        $solicitante = $this->solicitantes->administrador($contexto, $userId, $colaboradorId);
        $ahora = $this->reloj->ahora();
        $hoy = Carbon::instance($ahora)->setTimezone($contexto->zonaHoraria)->toDateString();

        $respaldos = $this->respaldos->validar($contexto->tenantId, $solicitante, $documentoIds);
        $ordenados = $respaldos;
        sort($ordenados);
        $huella = $this->control->huella('formalizar', $colaboradorId, $motivo, $fechaEfectiva, $ordenados);

        $previa = $this->control->repeticion($contexto->tenantId, $clave, $huella, $userId);
        if ($previa !== null) {
            return new ResultadoDeSalida($previa, false);
        }

        try {
            return $this->vinculos->ejecutarBajoBloqueo(
                $contexto->tenantId,
                $colaboradorId,
                fn () => $this->confirmar($contexto, $userId, $colaboradorId, $motivo, $fechaEfectiva, $respaldos, $clave, $huella, $ahora, $hoy),
            );
        } catch (ConflictoDeUnicidad $conflicto) {
            $previa = $this->control->repeticion($contexto->tenantId, $clave, $huella, $userId);
            if ($previa !== null) {
                return new ResultadoDeSalida($previa, false);
            }

            throw $conflicto;
        }
    }

    /** @param list<int> $respaldos */
    private function confirmar(ContextoDeTenant $contexto, int $userId, int $colaboradorId, mixed $motivo, mixed $fechaEfectiva, array $respaldos, string $clave, string $huella, \DateTimeImmutable $ahora, string $hoy): ResultadoDeSalida
    {
        $previa = $this->control->repeticion($contexto->tenantId, $clave, $huella, $userId);
        if ($previa !== null) {
            return new ResultadoDeSalida($previa, false);
        }

        $vinculo = $this->vinculoVigente($contexto->tenantId, $colaboradorId, $hoy);
        $motivoValido = $this->reglas->verificar($motivo, $fechaEfectiva, $hoy, $vinculo->fechaIngreso);

        // Una salida viva o un fin ya fijado en el vínculo impiden formalizar otra.
        if ($vinculo->fechaSalida !== null) {
            throw new SalidaYaFormalizada;
        }

        $politica = $this->politicas->seleccionarParaPresentar($contexto->tenantId, 'Offboarding', $ahora);
        $tareas = $this->tareasDe((array) $politica['parametros']);

        $expediente = $this->expedientes->insertar([
            'tenant_id' => $contexto->tenantId,
            'colaborador_id' => $colaboradorId,
            'vinculo_id' => $vinculo->id,
            'motivo' => $motivoValido->value,
            'fecha_efectiva' => (string) $fechaEfectiva,
            'estado' => 'Programada',
            'politica_version_id' => $politica['version_id'],
            'politica_numero' => $politica['numero'],
            'politica_condiciones' => (string) json_encode($politica['condiciones'], JSON_UNESCAPED_UNICODE),
            'politica_hash' => $politica['hash'],
            'formalizado_por' => $userId,
            'formalizado_en' => Carbon::instance($ahora)->utc()->format('Y-m-d H:i:s'),
            'version' => 1,
            'operacion_id' => $clave,
            'operacion_hash' => $huella,
            'created_by' => $userId,
            'updated_by' => $userId,
        ], $tareas, $respaldos);

        return new ResultadoDeSalida($expediente, true);
    }

    /**
     * @param  array<string, mixed>  $parametros
     * @return list<array{nombre: string, descripcion: string|null, exige_evidencia: bool}>
     */
    private function tareasDe(array $parametros): array
    {
        $tareas = [];
        foreach ((array) ($parametros['tareas'] ?? []) as $tarea) {
            $tareas[] = [
                'orden' => (int) ($tarea['orden'] ?? count($tareas) + 1),
                'nombre' => (string) $tarea['nombre'],
                'descripcion' => isset($tarea['descripcion']) && $tarea['descripcion'] !== '' ? (string) $tarea['descripcion'] : null,
                'exige_evidencia' => (bool) ($tarea['exige_evidencia'] ?? false),
            ];
        }
        usort($tareas, fn (array $a, array $b) => $a['orden'] <=> $b['orden']);

        return array_map(fn (array $t) => ['nombre' => $t['nombre'], 'descripcion' => $t['descripcion'], 'exige_evidencia' => $t['exige_evidencia']], $tareas);
    }

    private function vinculoVigente(int $tenantId, int $colaboradorId, string $hoy): VinculoLaboral
    {
        foreach ($this->vinculos->vinculosDeFicha($tenantId, $colaboradorId) as $vinculo) {
            if ($vinculo->periodo()->contiene($hoy)) {
                return $vinculo;
            }
        }

        throw new SinVinculoVigente;
    }
}
