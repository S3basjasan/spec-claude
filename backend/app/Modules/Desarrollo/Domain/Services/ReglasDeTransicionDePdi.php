<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\Entities\Pdi;
use App\Modules\Desarrollo\Domain\Exceptions\AcuerdoNoVigente;
use App\Modules\Desarrollo\Domain\Exceptions\PdiNoEditable;
use App\Modules\Desarrollo\Domain\Exceptions\TransicionInvalidaDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDePdi;

/**
 * Qué operaciones permite cada estado del plan (FR-026 a FR-034). Cada método lanza si la operación no es válida.
 * Borrador → Acordado ocurre cuando el colaborador acepta con el acuerdo del responsable vigente; Cerrado es final.
 */
final class ReglasDeTransicionDePdi
{
    /** Solo se edita la estructura en Borrador. */
    public function exigirEditable(Pdi $pdi): void
    {
        if ($pdi->estado !== EstadoDePdi::Borrador) {
            throw new PdiNoEditable;
        }
    }

    /** Confirmar el acuerdo del responsable: Borrador y sin acuerdo vigente. */
    public function exigirParaAcuerdo(Pdi $pdi): void
    {
        $this->exigirEditable($pdi);
        if ($pdi->tieneAcuerdoDelResponsable()) {
            throw AcuerdoNoVigente::yaExiste();
        }
    }

    /** Aceptar o devolver con comentario: Borrador con el acuerdo del responsable ya dado. */
    public function exigirParaRespuestaDelColaborador(Pdi $pdi): void
    {
        if ($pdi->estado !== EstadoDePdi::Borrador) {
            throw new TransicionInvalidaDePdi('El plan ya no está en Borrador.');
        }
        if (! $pdi->tieneAcuerdoDelResponsable()) {
            throw AcuerdoNoVigente::faltante();
        }
    }

    /** Devolver a Borrador (administrador): solo desde Acordado. */
    public function exigirParaDevolverABorrador(Pdi $pdi): void
    {
        if ($pdi->estado !== EstadoDePdi::Acordado) {
            throw new TransicionInvalidaDePdi('Solo se puede devolver a Borrador un plan Acordado.');
        }
    }

    /** Cambiar el responsable: Borrador o Acordado, nunca Cerrado (FR-006). */
    public function exigirParaCambiarResponsable(Pdi $pdi): void
    {
        if ($pdi->estado === EstadoDePdi::Cerrado) {
            throw new TransicionInvalidaDePdi('Un plan Cerrado no admite cambios.');
        }
    }

    /** Registrar avances de acciones e hitos: solo en un plan Acordado. */
    public function exigirParaSeguimiento(Pdi $pdi): void
    {
        if ($pdi->estado !== EstadoDePdi::Acordado) {
            throw new TransicionInvalidaDePdi('Solo se registran avances en un plan Acordado.');
        }
    }

    /** Vincular o desvincular acciones: Borrador o Acordado. */
    public function exigirParaVincular(Pdi $pdi): void
    {
        if ($pdi->estado === EstadoDePdi::Cerrado) {
            throw new TransicionInvalidaDePdi('Un plan Cerrado no admite cambios.');
        }
    }

    /** Cerrar: solo desde Acordado. */
    public function exigirParaCerrar(Pdi $pdi): void
    {
        if ($pdi->estado !== EstadoDePdi::Acordado) {
            throw new TransicionInvalidaDePdi('Solo se puede cerrar un plan Acordado.');
        }
    }

    /** En Borrador, cualquier edición borra los acuerdos (FR-028); en Acordado el responsable y los vínculos no. */
    public function borraAcuerdosAlEditar(Pdi $pdi): bool
    {
        return $pdi->estado === EstadoDePdi::Borrador;
    }
}
