<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** El seguimiento a 30, 60 o 90 días no se puede responder; el `code` indica el motivo estable. */
final class SeguimientoNoPermitido extends ErrorDeReporte
{
    public const HITO_INVALIDO = 'hito_invalido';

    public const HITO_NO_VENCIDO = 'hito_no_vencido';

    public const VENTANA_CERRADA = 'ventana_cerrada';

    public const SIN_ASISTENCIA = 'sin_asistencia';

    public const YA_RESPONDIDA = 'ya_respondida';

    public const ACTIVIDAD_NO_FINALIZADA = 'actividad_no_finalizada';

    private const MENSAJES = [
        self::HITO_INVALIDO => 'El hito debe ser 30, 60 o 90 días.',
        self::HITO_NO_VENCIDO => 'Este seguimiento todavía no está disponible.',
        self::VENTANA_CERRADA => 'El plazo de 15 días para responder este seguimiento ya terminó.',
        self::SIN_ASISTENCIA => 'Solo quienes asistieron a la actividad pueden responder el seguimiento.',
        self::YA_RESPONDIDA => 'Ya respondiste este seguimiento.',
        self::ACTIVIDAD_NO_FINALIZADA => 'El seguimiento solo se puede responder cuando la actividad ha finalizado.',
    ];

    public function __construct(string $motivo)
    {
        parent::__construct(self::MENSAJES[$motivo] ?? 'No puedes responder este seguimiento.', $motivo, $motivo === self::HITO_INVALIDO ? 'hito' : 'actividad_id');
    }
}
