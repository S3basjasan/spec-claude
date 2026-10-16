<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\IngresoDTO;
use App\Modules\GestionPersonal\Application\Ports\PosicionesAsignablesPort;
use App\Modules\GestionPersonal\Application\Services\CatalogoDeContratos;
use App\Modules\GestionPersonal\Domain\Entities\VinculoLaboral;
use App\Modules\GestionPersonal\Domain\Exceptions\ClaveDeOperacionRequerida;
use App\Modules\GestionPersonal\Domain\Exceptions\ClaveDeOperacionReutilizada;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\ConflictoDeUnicidad;
use App\Modules\GestionPersonal\Domain\Exceptions\ContratoNoHabilitado;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\IngresoNoVerificable;
use App\Modules\GestionPersonal\Domain\Exceptions\IngresosDeshabilitados;
use App\Modules\GestionPersonal\Domain\Exceptions\PosicionNoDisponible;
use App\Modules\GestionPersonal\Domain\Exceptions\VinculoSuperpuesto;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\VinculoLaboralRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\ReglasDeIngreso;
use Illuminate\Support\Str;

/**
 * Registra el vínculo y la asignación inicial de una ficha como una sola operación, idempotente por clave.
 */
final class RegistrarIngreso
{
    private const MOTIVOS = [
        'inexistente' => 'La posición no está disponible.',
        'otro_tenant' => 'La posición no está disponible.',
        'suprimida' => 'La posición está suprimida y no admite nuevas asignaciones.',
        'puesto_inactivo' => 'El puesto de la posición está inactivo y no admite nuevas asignaciones.',
        'unidad_inactiva' => 'La unidad de la posición está inactiva y no admite nuevas asignaciones.',
    ];

    public function __construct(
        private readonly ColaboradorRepositoryInterface $colaboradores,
        private readonly VinculoLaboralRepositoryInterface $vinculos,
        private readonly PosicionesAsignablesPort $posiciones,
        private readonly CatalogoDeContratos $contratos,
        private readonly ReglasDeIngreso $reglas,
        private readonly ConsultarSituacionLaboral $situacion,
    ) {}

    /**
     * @param  array<string, mixed>  $datos  `fecha_ingreso`, `tipo_contrato` y `posicion_id`.
     *
     * @throws IngresosDeshabilitados
     * @throws ClaveDeOperacionRequerida
     * @throws ColaboradorNoEncontrado
     * @throws DatosInvalidos
     * @throws ContratoNoHabilitado
     * @throws ClaveDeOperacionReutilizada
     * @throws PosicionNoDisponible
     * @throws VinculoSuperpuesto
     * @throws IngresoNoVerificable
     */
    public function ejecutar(ContextoDeTenant $contexto, int $actorId, int $colaboradorId, ?string $claveDeOperacion, array $datos): IngresoDTO
    {
        if (! config('hrms.gestion_personal.ingresos_habilitados', true)) {
            throw new IngresosDeshabilitados;
        }

        if ($claveDeOperacion === null || ! Str::isUuid($claveDeOperacion)) {
            throw new ClaveDeOperacionRequerida;
        }
        $claveDeOperacion = strtolower($claveDeOperacion);

        $this->colaboradores->porId($contexto->tenantId, $colaboradorId) ?? throw new ColaboradorNoEncontrado;

        $fecha = $datos['fecha_ingreso'] ?? null;
        $contrato = $datos['tipo_contrato'] ?? null;
        $posicionId = $datos['posicion_id'] ?? null;

        $errores = $this->reglas->errores($fecha, $contrato, $posicionId, $contexto->hoy(), $this->contratos->claves());
        $contratoNoHabilitado = is_string($contrato) && $contrato !== '' && ! in_array($contrato, $this->contratos->claves(), true);
        if ($contratoNoHabilitado) {
            unset($errores['tipo_contrato']);
        }
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }
        if ($contratoNoHabilitado) {
            throw new ContratoNoHabilitado;
        }

        $fecha = (string) $fecha;
        $contrato = (string) $contrato;
        $posicionId = (int) $posicionId;
        $hash = hash('sha256', (string) json_encode([$colaboradorId, $fecha, $contrato, $posicionId]));

        // Un reintento válido se resuelve antes de repetir efectos y fuera de cualquier transacción.
        $existente = $this->vinculos->porOperacion($contexto->tenantId, $claveDeOperacion);
        if ($existente !== null) {
            return $this->repetir($existente, $hash, $actorId, $contexto);
        }

        try {
            $vinculo = $this->vinculos->ejecutarBajoBloqueo(
                $contexto->tenantId,
                $colaboradorId,
                fn () => $this->confirmar($contexto, $actorId, $colaboradorId, $claveDeOperacion, $hash, $fecha, $contrato, $posicionId),
            );
        } catch (ConflictoDeUnicidad $conflicto) {
            // La transacción ya se cerró: la carrera idempotente se resuelve con una lectura nueva.
            $existente = $this->vinculos->porOperacion($contexto->tenantId, $claveDeOperacion);
            if ($existente !== null) {
                return $this->repetir($existente, $hash, $actorId, $contexto);
            }

            throw $conflicto;
        }

        if ($vinculo instanceof IngresoDTO) {
            return $vinculo;
        }

        return $this->resultado($contexto, $colaboradorId, $vinculo, true);
    }

    /**
     * Cuerpo de la transacción: ya se tiene el bloqueo de escritura, así que todas las lecturas ven el estado vigente.
     */
    private function confirmar(ContextoDeTenant $contexto, int $actorId, int $colaboradorId, string $clave, string $hash, string $fecha, string $contrato, int $posicionId): VinculoLaboral|IngresoDTO
    {
        // Carrera idempotente: otro proceso pudo confirmar la misma operación mientras se esperaba el bloqueo.
        $existente = $this->vinculos->porOperacion($contexto->tenantId, $clave);
        if ($existente !== null) {
            return $this->repetir($existente, $hash, $actorId, $contexto);
        }

        $veredicto = $this->posiciones->verificar($posicionId, $contexto->tenantId);
        if (! $veredicto['asignable']) {
            throw new PosicionNoDisponible(self::MOTIVOS[$veredicto['motivo'] ?? 'inexistente'] ?? 'La posición no está disponible.');
        }

        $vinculos = $this->vinculos->vinculosDeFicha($contexto->tenantId, $colaboradorId);
        $enConflicto = $this->reglas->vinculoEnConflicto($fecha, $vinculos);
        if ($enConflicto !== null) {
            $hasta = $enConflicto->fechaSalida === null ? 'sin fecha de salida' : "hasta el {$enConflicto->fechaSalida}";
            throw new VinculoSuperpuesto(
                "La ficha ya tiene un vínculo laboral desde el {$enConflicto->fechaIngreso} ($hasta) que se superpone con esa fecha.",
            );
        }

        $ocupante = $this->reglas->asignacionEnConflicto($fecha, $this->vinculos->asignacionesDePosicionDesde($posicionId, $fecha));
        if ($ocupante !== null) {
            $hasta = $ocupante->fechaFin === null ? 'sin fecha de fin' : "hasta el {$ocupante->fechaFin}";
            throw new PosicionNoDisponible(
                "La posición está ocupada desde el {$ocupante->fechaInicio} ($hasta) durante el periodo solicitado.",
            );
        }

        return $this->vinculos->insertarIngreso(
            $contexto->tenantId,
            $colaboradorId,
            $clave,
            $hash,
            $fecha,
            $contrato,
            $posicionId,
            $actorId,
        );
    }

    /**
     * @param  array{vinculo: VinculoLaboral, hash: string, actor: int}  $existente
     *
     * @throws ClaveDeOperacionReutilizada
     */
    private function repetir(array $existente, string $hash, int $actorId, ContextoDeTenant $contexto): IngresoDTO
    {
        if ($existente['hash'] !== $hash || $existente['actor'] !== $actorId) {
            throw new ClaveDeOperacionReutilizada;
        }

        return $this->resultado($contexto, $existente['vinculo']->colaboradorId, $existente['vinculo'], false);
    }

    private function resultado(ContextoDeTenant $contexto, int $colaboradorId, VinculoLaboral $vinculo, bool $creado): IngresoDTO
    {
        $asignacion = $vinculo->asignacionPrincipal();

        return new IngresoDTO([
            'vinculo' => [
                'id' => $vinculo->id,
                'colaborador_id' => $vinculo->colaboradorId,
                'fecha_ingreso' => $vinculo->fechaIngreso,
                'fecha_salida' => $vinculo->fechaSalida,
                'tipo_contrato' => $this->contratos->describir($vinculo->tipoContratoClave),
                'registrado_en' => $vinculo->createdAt,
                'programado' => $vinculo->fechaIngreso > $contexto->hoy(),
            ],
            'asignacion' => $asignacion === null ? null : [
                'id' => $asignacion->id,
                'posicion_id' => $asignacion->posicionId,
                'fecha_inicio' => $asignacion->fechaInicio,
                'fecha_fin' => $asignacion->fechaFin,
            ],
            'situacion' => $this->situacion->ejecutar($contexto, $colaboradorId),
        ], $creado);
    }
}
