<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\AccesoAPdi;
use App\Modules\Desarrollo\Domain\Exceptions\DatosInvalidosDePdi;
use App\Modules\Desarrollo\Domain\Exceptions\VersionDePdiDesfasada;
use App\Modules\Desarrollo\Domain\Repositories\RepositorioDePdiInterface;
use App\Modules\Desarrollo\Domain\Services\ReglasDeTransicionDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeHistorialPdi;

/**
 * El colaborador devuelve su plan con un comentario: sigue en Borrador, se borran los acuerdos y se guarda el comentario
 * (FR-029). Al borrarse el acuerdo del responsable, el plan deja de ser visible para el colaborador.
 */
final class DevolverPdiDelColaborador
{
    public function __construct(
        private readonly AccesoAPdi $acceso,
        private readonly RepositorioDePdiInterface $repositorio,
        private readonly ReglasDeTransicionDePdi $reglas,
    ) {}

    /** @return array{id: int, codigo: string, estado: string, version: int} */
    public function ejecutar(int $tenantId, int $usuarioId, int $id, string $comentario, int $version): array
    {
        $comentario = trim($comentario);
        if ($comentario === '') {
            throw DatosInvalidosDePdi::campo('comentario', 'Escribe un comentario para devolver el plan.');
        }

        $pdi = $this->acceso->cargarParaResponder($tenantId, $usuarioId, $id);
        $this->reglas->exigirParaRespuestaDelColaborador($pdi);
        if ($pdi->version !== $version) {
            throw new VersionDePdiDesfasada($pdi->version);
        }

        $nuevo = $pdi->con([
            'comentarioColaborador' => $comentario,
            'acuerdoResponsableAt' => null,
            'acuerdoResponsableBy' => null,
            'aceptacionColaboradorAt' => null,
            'version' => $pdi->version + 1,
        ]);

        $this->repositorio->transaccion(function () use ($nuevo, $pdi, $usuarioId, $comentario) {
            $this->repositorio->actualizarCabecera($nuevo, $pdi->version, $usuarioId);
            $this->repositorio->registrarHistorial($pdi->tenantId, (int) $pdi->id, TipoDeHistorialPdi::Devolucion, null, 'Acuerdo del responsable', 'Sin acuerdo', $comentario, $usuarioId);
        });

        return ['id' => (int) $pdi->id, 'codigo' => (string) $pdi->codigo, 'estado' => $nuevo->estado->value, 'version' => $nuevo->version];
    }
}
