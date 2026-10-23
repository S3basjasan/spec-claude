<?php

namespace App\Modules\Desarrollo\Application\Services;

use App\Modules\Desarrollo\Application\Ports\CuentaColaboradorInterface;
use App\Modules\Desarrollo\Domain\Entities\Pdi;
use App\Modules\Desarrollo\Domain\Exceptions\PdiNoEncontrado;
use App\Modules\Desarrollo\Domain\Exceptions\SinAccesoAlPdi;
use App\Modules\Desarrollo\Domain\Repositories\RepositorioDePdiInterface;

/**
 * Carga un plan para quien lo consulta o responde. El administrador ve todos los planes de su tenant; el colaborador solo
 * los suyos y solo cuando ya tienen el acuerdo del responsable (FR-047). Lo no accesible se trata como inexistente.
 */
final class AccesoAPdi
{
    public function __construct(
        private readonly RepositorioDePdiInterface $repositorio,
        private readonly CuentaColaboradorInterface $cuentas,
    ) {}

    public function cargar(int $tenantId, int $usuarioId, int $id, bool $esAdministrador): Pdi
    {
        $pdi = $this->repositorio->obtener($tenantId, $id) ?? throw new PdiNoEncontrado;

        if ($esAdministrador) {
            return $pdi;
        }

        if ($this->colaboradorDe($usuarioId, $tenantId) !== $pdi->colaboradorId || ! $pdi->visibleParaColaborador()) {
            throw new PdiNoEncontrado;
        }

        return $pdi;
    }

    /** Para aceptar o devolver: el plan existe pero no es del usuario ⇒ 403; si no es visible para él ⇒ 404. */
    public function cargarParaResponder(int $tenantId, int $usuarioId, int $id): Pdi
    {
        $pdi = $this->repositorio->obtener($tenantId, $id) ?? throw new PdiNoEncontrado;

        if ($this->colaboradorDe($usuarioId, $tenantId) !== $pdi->colaboradorId) {
            throw new SinAccesoAlPdi;
        }
        if (! $pdi->visibleParaColaborador()) {
            throw new PdiNoEncontrado;
        }

        return $pdi;
    }

    /** Identificador del colaborador asociado a la cuenta del usuario en ese tenant, o nulo. */
    public function colaboradorDe(int $usuarioId, int $tenantId): ?int
    {
        $cuenta = $this->cuentas->cuentaDe($usuarioId);

        return $cuenta !== null && $cuenta['tenant_id'] === $tenantId ? (int) $cuenta['colaborador_id'] : null;
    }
}
