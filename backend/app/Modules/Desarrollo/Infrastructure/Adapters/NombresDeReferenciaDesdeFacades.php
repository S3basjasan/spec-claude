<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;
use App\Modules\GestionPersonal\Presentation\Facades\PersonalFacade;

/** Resuelve nombres de colaboradores, puestos, competencias y unidades por las Facades públicas. */
final class NombresDeReferenciaDesdeFacades implements NombresDeReferenciaInterface
{
    public function colaboradores(int $tenantId, array $ids): array
    {
        $nombres = [];
        foreach (PersonalFacade::getColaboradoresPorIds($tenantId, array_values(array_unique($ids))) as $c) {
            $nombres[(int) $c['id']] = trim($c['nombres'].' '.$c['apellidos']);
        }

        return $nombres;
    }

    public function puestos(array $ids): array
    {
        $puestos = [];
        foreach (EstructuraFacade::getPuestosPorIds($ids) as $p) {
            $puestos[(int) $p['id']] = ['codigo' => (string) $p['codigo'], 'nombre' => (string) $p['nombre']];
        }

        return $puestos;
    }

    public function competencias(array $ids): array
    {
        $competencias = [];
        foreach (EstructuraFacade::getCompetenciasPorIds($ids) as $c) {
            $competencias[(int) $c['id']] = [
                'codigo' => (string) $c['codigo'],
                'nombre' => (string) $c['nombre'],
                'activa' => $c['estado'] === 'Activo',
            ];
        }

        return $competencias;
    }

    public function unidades(array $ids): array
    {
        $unidades = [];
        foreach (EstructuraFacade::getUnidadesPorIds($ids) as $u) {
            $unidades[(int) $u['id']] = (string) $u['nombre'];
        }

        return $unidades;
    }
}
