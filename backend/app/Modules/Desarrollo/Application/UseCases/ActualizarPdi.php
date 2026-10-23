<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\ArmadorDeDetalleDePdi;
use App\Modules\Desarrollo\Application\Services\ConstructorDePdi;
use App\Modules\Desarrollo\Application\Services\ValidadorDePdi;
use App\Modules\Desarrollo\Domain\Exceptions\DatosInvalidosDePdi;
use App\Modules\Desarrollo\Domain\Exceptions\PdiNoEncontrado;
use App\Modules\Desarrollo\Domain\Exceptions\VersionDePdiDesfasada;
use App\Modules\Desarrollo\Domain\Repositories\RepositorioDePdiInterface;
use App\Modules\Desarrollo\Domain\Services\ReglasDeTransicionDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeHistorialPdi;

/**
 * Edita un plan en Borrador: reemplaza datos generales, compromisos, acciones e hitos, borra el acuerdo del responsable y
 * la aceptación del colaborador e incrementa la versión (FR-028). El tipo y el colaborador no cambian (FR-003).
 */
final class ActualizarPdi
{
    public function __construct(
        private readonly ConstructorDePdi $constructor,
        private readonly ValidadorDePdi $validador,
        private readonly ReglasDeTransicionDePdi $reglas,
        private readonly RepositorioDePdiInterface $repositorio,
        private readonly ArmadorDeDetalleDePdi $armador,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function ejecutar(int $tenantId, int $actorId, int $id, array $datos): array
    {
        $actual = $this->repositorio->obtener($tenantId, $id) ?? throw new PdiNoEncontrado;
        $this->reglas->exigirEditable($actual);

        if (isset($datos['tipo']) && $datos['tipo'] !== $actual->tipo->value) {
            throw DatosInvalidosDePdi::campo('tipo', 'El tipo del plan no se puede cambiar.');
        }
        if ($actual->version !== (int) $datos['version']) {
            throw new VersionDePdiDesfasada($actual->version);
        }

        $this->validador->validarCabecera($tenantId, $actual->tipo, $datos);
        $nuevo = $this->constructor->deDatos($tenantId, $actorId, $actual->tipo, $actual->colaboradorId, $datos)->con([
            'id' => $actual->id,
            'codigo' => $actual->codigo,
            'estado' => $actual->estado,
            'version' => $actual->version + 1,
            'comentarioColaborador' => $actual->comentarioColaborador,
            'createdBy' => $actual->createdBy,
            'createdAt' => $actual->createdAt,
        ]);
        $this->validador->validarEstructura($nuevo);

        $recargado = $this->repositorio->transaccion(function () use ($nuevo, $actual, $actorId) {
            $this->repositorio->actualizarCabecera($nuevo, $actual->version, $actorId);
            $this->repositorio->registrarHistorial(
                $actual->tenantId, (int) $actual->id, TipoDeHistorialPdi::Edicion, null,
                'v'.$actual->version, 'v'.$nuevo->version,
                $actual->tieneAcuerdoDelResponsable() ? 'Se borraron los acuerdos por la edición.' : null,
                $actorId,
            );

            return $this->repositorio->reemplazarEstructura($nuevo, $actorId);
        });

        return $this->armador->detalle($recargado, true, $actorId);
    }
}
