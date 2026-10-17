<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** El plan no puede quitar de una acción a un colaborador cuya asistencia ya está registrada en su actividad. */
final class ParticipanteConAsistencia extends ErrorDePlan
{
    public function __construct()
    {
        parent::__construct('No se puede quitar a un participante que ya tiene asistencia registrada en la actividad.', 'participante_con_asistencia', 'colaborador_ids');
    }
}
