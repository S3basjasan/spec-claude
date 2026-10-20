<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** La encuesta de satisfacción no se puede responder; el `code` indica el motivo estable. */
final class EncuestaNoPermitida extends ErrorDeReporte
{
    public const ACTIVIDAD_NO_FINALIZADA = 'actividad_no_finalizada';

    public const SIN_ASISTENCIA = 'sin_asistencia';

    public const VENTANA_CERRADA = 'ventana_cerrada';

    public const YA_RESPONDIDA = 'ya_respondida';

    private const MENSAJES = [
        self::ACTIVIDAD_NO_FINALIZADA => 'La encuesta solo se puede responder cuando la actividad ha finalizado.',
        self::SIN_ASISTENCIA => 'Solo quienes asistieron a la actividad pueden responder la encuesta.',
        self::VENTANA_CERRADA => 'El plazo de 30 días para responder la encuesta ya terminó.',
        self::YA_RESPONDIDA => 'Ya respondiste la encuesta de esta actividad.',
    ];

    public function __construct(string $motivo)
    {
        parent::__construct(self::MENSAJES[$motivo] ?? 'No puedes responder esta encuesta.', $motivo, 'actividad_id');
    }
}
