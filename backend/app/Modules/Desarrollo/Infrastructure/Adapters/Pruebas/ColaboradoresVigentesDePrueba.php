<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters\Pruebas;

use App\Modules\Desarrollo\Application\Ports\ColaboradoresVigentesInterface;
use App\Modules\GestionPersonal\Presentation\Facades\PersonalFacade;

/**
 * Solo para el entorno `e2e` (nunca se enlaza en otro entorno): considera con vínculo vigente a las tres fichas
 * sembradas que ya usa la DNF, para que los E2E del plan de formación tengan participantes sin depender de que el
 * seeder de ingresos deje vínculos vigentes.
 */
final class ColaboradoresVigentesDePrueba implements ColaboradoresVigentesInterface
{
    private const VIGENTES = [['Cedula', '0102030405'], ['Cedula', '0912345678'], ['Pasaporte', 'AB123']];

    public function buscar(int $tenantId, ?string $texto, int $pagina, int $porPagina, string $fecha): array
    {
        $buscado = mb_strtolower(trim((string) $texto));
        $items = array_values(array_filter(
            $this->fichas($tenantId),
            fn (array $f) => $buscado === '' || str_contains(mb_strtolower($f['nombre'].' '.$f['codigo']), $buscado),
        ));

        return ['items' => array_slice($items, max(0, $pagina - 1) * $porPagina, $porPagina), 'total' => count($items)];
    }

    public function vigentesPorIds(int $tenantId, array $ids, string $fecha): array
    {
        $vigentes = array_column($this->fichas($tenantId), 'id');

        return array_values(array_filter(array_map('intval', $ids), fn (int $id) => in_array($id, $vigentes, true)));
    }

    /** @return list<array{id: int, codigo: string, nombre: string}> */
    private function fichas(int $tenantId): array
    {
        $fichas = [];
        foreach (self::VIGENTES as [$tipo, $numero]) {
            $ficha = PersonalFacade::buscarPorIdentificacion($tenantId, $tipo, $numero);
            if ($ficha !== null) {
                $fichas[] = ['id' => (int) $ficha['id'], 'codigo' => (string) $ficha['codigo'], 'nombre' => trim($ficha['nombres'].' '.$ficha['apellidos'])];
            }
        }

        return $fichas;
    }
}
