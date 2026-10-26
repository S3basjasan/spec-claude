<?php

namespace App\Modules\GestionPersonal\Application\DTO;

/** Quién presenta y a quién afecta: el Administrador puede actuar en nombre del colaborador, dejando constancia. */
final class Solicitante
{
    public function __construct(
        public readonly int $userId,
        public readonly int $colaboradorId,
        public readonly bool $comoColaborador,
        public readonly bool $enNombreDeColaborador,
        public readonly bool $puedeConsultarDocumentos,
    ) {}
}
