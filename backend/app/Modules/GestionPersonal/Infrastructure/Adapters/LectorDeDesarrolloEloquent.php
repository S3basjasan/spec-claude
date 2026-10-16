<?php

namespace App\Modules\GestionPersonal\Infrastructure\Adapters;

use App\Modules\GestionPersonal\Application\Ports\LectorDeDesarrolloPort;
use App\Modules\GestionPersonal\Application\Services\ArmadorDeDesarrollo;
use App\Modules\GestionPersonal\Domain\Repositories\DesarrolloRepositoryInterface;
use App\Modules\GestionPersonal\Domain\ValueObjects\Lectura;
use Throwable;

final class LectorDeDesarrolloEloquent implements LectorDeDesarrolloPort
{
    public function __construct(
        private readonly DesarrolloRepositoryInterface $desarrollo,
        private readonly ArmadorDeDesarrollo $armador,
    ) {}

    public function deColaboradores(int $tenantId, array $colaboradorIds): Lectura
    {
        try {
            $datos = [];
            foreach ($this->desarrollo->deColaboradores($tenantId, $colaboradorIds) as $id => $bloque) {
                $datos[$id] = [
                    'formaciones' => array_map(fn ($f) => $this->sinAuditoria($this->armador->formacion($f)), $bloque['formaciones']),
                    'experiencias' => array_map(fn ($e) => $this->sinAuditoria($this->armador->experiencia($e)), $bloque['experiencias']),
                    'movilidad' => $this->sinAuditoria($this->armador->movilidad($bloque['movilidad'])),
                ];
            }

            return Lectura::disponible($datos);
        } catch (Throwable) {
            return Lectura::noDisponible();
        }
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private function sinAuditoria(array $fila): array
    {
        unset($fila['version'], $fila['created_at'], $fila['updated_at']);

        return $fila;
    }
}
