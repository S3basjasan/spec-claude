<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\FiltroColaboradoresDTO;
use App\Modules\GestionPersonal\Application\DTO\ListadoColaboradoresDTO;
use App\Modules\GestionPersonal\Application\Ports\LectorEstructuraPort;
use App\Modules\GestionPersonal\Application\Ports\LectorLaboralPort;
use App\Modules\GestionPersonal\Domain\Entities\Colaborador;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\InformacionNoDisponible;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\NormalizadorDeTexto;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoLaboral;
use App\Modules\GestionPersonal\Domain\ValueObjects\Lectura;

final class ListarColaboradores
{
    public function __construct(
        private readonly ColaboradorRepositoryInterface $colaboradores,
        private readonly LectorLaboralPort $laboral,
        private readonly LectorEstructuraPort $estructura,
        private readonly NormalizadorDeTexto $normalizador,
    ) {}

    /**
     * @throws DatosInvalidos
     * @throws InformacionNoDisponible
     */
    public function ejecutar(ContextoDeTenant $contexto, FiltroColaboradoresDTO $filtro): ListadoColaboradoresDTO
    {
        $hoy = $contexto->hoy();
        $incluir = null;
        $excluir = null;

        // Filtro de estado.
        if ($filtro->estado !== null && $filtro->estado !== 'Todos') {
            if ($filtro->estado === EstadoLaboral::SinVinculoVigente->value) {
                $vinculados = $this->laboral->idsConVinculoVigente($contexto->tenantId, $hoy);
                $this->exigirSinFallo($vinculados->fallo());
                $excluir = $vinculados->estaDisponible() ? array_map('intval', $vinculados->datos) : null;
            } else {
                $porEstado = $this->laboral->idsPorEstado($contexto->tenantId, $filtro->estado, $hoy);
                if ($porEstado->noEstaInstalado()) {
                    throw new DatosInvalidos(['estado' => ['El estado seleccionado no está disponible.']]);
                }
                $this->exigirSinFallo($porEstado->fallo());
                $incluir = array_map('intval', $porEstado->datos);
            }
        }

        // Filtro de unidad organizacional.
        if ($filtro->unidadId !== null) {
            $unidades = $this->estructura->unidades();
            $this->exigirSinFallo($unidades->fallo());
            $ids = $unidades->estaDisponible() ? array_map(fn (array $u) => (int) $u['id'], $unidades->datos) : [];
            if (! in_array($filtro->unidadId, $ids, true)) {
                throw new DatosInvalidos(['unidad_id' => ['La unidad organizacional seleccionada no está disponible.']]);
            }

            $posiciones = $this->estructura->posicionesPorUnidad($filtro->unidadId);
            $this->exigirSinFallo($posiciones->fallo());
            $enUnidad = $this->laboral->colaboradoresEnPosiciones($contexto->tenantId, $posiciones->datos ?? [], $hoy);
            $this->exigirSinFallo($enUnidad->fallo());
            $enUnidad = array_map('intval', $enUnidad->datos ?? []);
            $incluir = $incluir === null ? $enUnidad : array_values(array_intersect($incluir, $enUnidad));
        }

        // Búsqueda: nombres y apellidos por tokens, código y puesto.
        $busqueda = $this->normalizador->busquedaDeTexto((string) $filtro->q);
        $tokens = $busqueda === '' ? [] : array_values(array_filter(preg_split('/\s+/', $busqueda) ?: []));
        $idsPorPuesto = null;
        if ($busqueda !== '') {
            $posiciones = $this->estructura->posicionesPorPuesto($busqueda);
            if ($posiciones->estaDisponible() && $posiciones->datos !== []) {
                $fichas = $this->laboral->colaboradoresEnPosiciones($contexto->tenantId, $posiciones->datos, $hoy);
                $idsPorPuesto = $fichas->estaDisponible() ? array_map('intval', $fichas->datos) : null;
            }
        }

        $pagina = $this->colaboradores->listar(
            $contexto->tenantId,
            $tokens,
            $busqueda === '' ? null : $busqueda,
            $idsPorPuesto,
            $incluir,
            $excluir,
            $filtro->pagina,
            $filtro->porPagina,
        );

        return new ListadoColaboradoresDTO(
            $this->enriquecer($contexto, $pagina['items'], $hoy),
            $pagina['total'],
            $filtro->pagina,
            $filtro->porPagina,
        );
    }

    /**
     * @param  list<Colaborador>  $items
     * @return list<array<string, mixed>>
     */
    private function enriquecer(ContextoDeTenant $contexto, array $items, string $hoy): array
    {
        $ids = array_map(fn (Colaborador $c) => $c->id, $items);
        $situacion = $ids === [] ? Lectura::noInstalado()
            : $this->laboral->situacionVigente($contexto->tenantId, $ids, $hoy);

        $posiciones = [];
        $estructuraFallo = false;
        if ($situacion->estaDisponible()) {
            $posicionIds = array_values(array_unique(array_filter(
                array_map(fn (array $s) => $s['posicion_id'] ?? null, $situacion->datos),
                fn ($id) => $id !== null,
            )));
            if ($posicionIds !== []) {
                $lectura = $this->estructura->posiciones(array_map('intval', $posicionIds));
                $estructuraFallo = $lectura->fallo();
                $posiciones = $lectura->estaDisponible() ? $lectura->datos : [];
            }
        }

        $filas = [];
        foreach ($items as $c) {
            $vigente = $situacion->estaDisponible() ? ($situacion->datos[$c->id] ?? null) : null;
            $posicion = $vigente !== null && ($vigente['posicion_id'] ?? null) !== null
                ? ($posiciones[(int) $vigente['posicion_id']] ?? null)
                : null;

            $filas[] = [
                'id' => $c->id,
                'codigo' => $c->codigo,
                'nombres' => $c->datos['nombres'],
                'apellidos' => $c->datos['apellidos'],
                'estado' => $vigente['estado'] ?? EstadoLaboral::SinVinculoVigente->value,
                'puesto' => $posicion['puesto'] ?? null,
                'unidad' => $posicion['unidad'] ?? null,
                'laboral_disponible' => ! $situacion->fallo() && ! $estructuraFallo,
            ];
        }

        return $filas;
    }

    private function exigirSinFallo(bool $fallo): void
    {
        if ($fallo) {
            throw new InformacionNoDisponible;
        }
    }
}
