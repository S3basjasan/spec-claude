<?php

namespace App\Modules\GestionPersonal\Application\Services;

use App\Modules\GestionPersonal\Application\DTO\Solicitante;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Repositories\DocumentoRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\ReglasDeActuacion;

/**
 * Verifica que los respaldos referenciados existan en el tenant, pertenezcan a la ficha afectada, estén Disponibles y
 * sean accesibles para quien presenta. Solo se guardan referencias: nunca se copia contenido ni se registra el título.
 * Un archivo no accesible no cuenta como respaldo cumplido.
 */
final class ValidadorDeRespaldos
{
    public function __construct(private readonly DocumentoRepositoryInterface $documentos) {}

    /**
     * @param  list<mixed>  $ids
     * @return list<int> Identificadores únicos y válidos.
     *
     * @throws DatosInvalidos
     */
    public function validar(int $tenantId, Solicitante $solicitante, array $ids): array
    {
        $limpios = [];
        foreach ($ids as $id) {
            if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
                throw new DatosInvalidos(['documento_ids' => ['Los respaldos deben indicarse por su identificador.']]);
            }
            $limpios[(int) $id] = (int) $id;
        }
        $limpios = array_values($limpios);

        if (count($limpios) > ReglasDeActuacion::MAX_RESPALDOS) {
            throw new DatosInvalidos(['documento_ids' => ['Se admiten hasta '.ReglasDeActuacion::MAX_RESPALDOS.' respaldos.']]);
        }

        foreach ($limpios as $id) {
            if (! $this->esAccesible($tenantId, $solicitante, $id)) {
                throw new DatosInvalidos(['documento_ids' => ['Un respaldo no está disponible o no puedes usarlo.']]);
            }
        }

        return $limpios;
    }

    /**
     * Cuántos de los respaldos conservados siguen disponibles hoy para el responsable de la ficha (Administrador).
     *
     * @param  list<int>  $ids
     */
    public function disponibles(int $tenantId, int $colaboradorId, array $ids): int
    {
        $disponibles = 0;
        foreach ($ids as $id) {
            if ($this->documentos->porId($tenantId, $colaboradorId, $id)?->estaDisponible() === true) {
                $disponibles++;
            }
        }

        return $disponibles;
    }

    /**
     * Disponibilidad actual de cada respaldo conservado (sin título ni contenido).
     *
     * @param  list<int>  $ids
     * @return list<array{id: int, disponible: bool}>
     */
    public function estados(int $tenantId, int $colaboradorId, array $ids): array
    {
        return array_map(
            fn (int $id) => ['id' => $id, 'disponible' => $this->documentos->porId($tenantId, $colaboradorId, $id)?->estaDisponible() === true],
            $ids,
        );
    }

    private function esAccesible(int $tenantId, Solicitante $solicitante, int $documentoId): bool
    {
        $documento = $this->documentos->porId($tenantId, $solicitante->colaboradorId, $documentoId);
        if ($documento === null || ! $documento->estaDisponible()) {
            return false;
        }

        return $solicitante->comoColaborador
            ? $solicitante->puedeConsultarDocumentos && $documento->visibleColaborador
            : true;
    }
}
