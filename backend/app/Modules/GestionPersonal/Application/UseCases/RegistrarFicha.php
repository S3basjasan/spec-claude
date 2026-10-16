<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\FichaDetalleDTO;
use App\Modules\GestionPersonal\Application\Services\ArmadorDeFichaDetalle;
use App\Modules\GestionPersonal\Domain\Entities\Colaborador;
use App\Modules\GestionPersonal\Domain\Exceptions\ClaveDeOperacionRequerida;
use App\Modules\GestionPersonal\Domain\Exceptions\ClaveDeOperacionReutilizada;
use App\Modules\GestionPersonal\Domain\Exceptions\ConflictoDeUnicidad;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\EscrituraDeshabilitada;
use App\Modules\GestionPersonal\Domain\Exceptions\IdentificacionDuplicada;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\NormalizadorDeTexto;
use App\Modules\GestionPersonal\Domain\Services\ReglasDeFicha;
use Illuminate\Support\Str;

final class RegistrarFicha
{
    public function __construct(
        private readonly ColaboradorRepositoryInterface $colaboradores,
        private readonly ReglasDeFicha $reglas,
        private readonly NormalizadorDeTexto $normalizador,
        private readonly ArmadorDeFichaDetalle $armador,
    ) {}

    /**
     * Crea la ficha (idempotente por clave de operación) o devuelve la ya creada con la misma clave.
     *
     * @param  array<string, mixed>  $datos  Campos editables.
     *
     * @throws EscrituraDeshabilitada
     * @throws ClaveDeOperacionRequerida
     * @throws ClaveDeOperacionReutilizada
     * @throws DatosInvalidos
     * @throws IdentificacionDuplicada
     */
    public function ejecutar(ContextoDeTenant $contexto, int $actorId, ?string $claveDeOperacion, array $datos): FichaDetalleDTO
    {
        if (! config('hrms.gestion_personal.escritura_habilitada', true)) {
            throw new EscrituraDeshabilitada;
        }

        if ($claveDeOperacion === null || ! Str::isUuid($claveDeOperacion)) {
            throw new ClaveDeOperacionRequerida;
        }

        $claveDeOperacion = strtolower($claveDeOperacion);
        $datos = $this->completar($this->reglas->normalizar($datos));
        $hash = $this->hash($datos);

        $existente = $this->colaboradores->porOperacion($contexto->tenantId, $claveDeOperacion);
        if ($existente !== null) {
            return $this->repetir($existente, $hash, $actorId, $contexto);
        }

        $errores = $this->reglas->errores($datos, $contexto->hoy());
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        $clave = $this->normalizador->claveDeIdentificacion((string) $datos['numero_identificacion']);
        $this->rechazarDuplicada($contexto->tenantId, (string) $datos['tipo_identificacion'], $clave);

        try {
            $colaborador = $this->colaboradores->crear(
                $contexto->tenantId,
                $actorId,
                $claveDeOperacion,
                $hash,
                $datos,
                $clave,
                $this->normalizador->busquedaDeTexto((string) $datos['nombres']),
                $this->normalizador->busquedaDeTexto((string) $datos['apellidos']),
            );
        } catch (ConflictoDeUnicidad $conflicto) {
            // La transacción ya se cerró: se resuelve en una consulta nueva.
            $existente = $this->colaboradores->porOperacion($contexto->tenantId, $claveDeOperacion);
            if ($existente !== null) {
                return $this->repetir($existente, $hash, $actorId, $contexto);
            }
            $this->rechazarDuplicada($contexto->tenantId, (string) $datos['tipo_identificacion'], $clave);

            throw $conflicto;
        }

        return new FichaDetalleDTO($this->armador->armar($colaborador, $contexto), true);
    }

    /**
     * @param  array{colaborador: Colaborador, hash: string, actor: int}  $existente
     */
    private function repetir(array $existente, string $hash, int $actorId, ContextoDeTenant $contexto): FichaDetalleDTO
    {
        if ($existente['hash'] !== $hash || $existente['actor'] !== $actorId) {
            throw new ClaveDeOperacionReutilizada;
        }

        return new FichaDetalleDTO($this->armador->armar($existente['colaborador'], $contexto), false);
    }

    private function rechazarDuplicada(int $tenantId, string $tipo, string $clave): void
    {
        $duplicada = $this->colaboradores->porIdentificacion($tenantId, $tipo, $clave);
        if ($duplicada !== null) {
            throw new IdentificacionDuplicada($duplicada->id, $duplicada->codigo);
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function completar(array $datos): array
    {
        $completo = [];
        foreach ([...ReglasDeFicha::OBLIGATORIOS, ...ReglasDeFicha::OPCIONALES] as $campo) {
            $completo[$campo] = $datos[$campo] ?? null;
        }

        return $completo;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function hash(array $datos): string
    {
        ksort($datos);

        return hash('sha256', (string) json_encode($datos, JSON_UNESCAPED_UNICODE));
    }
}
