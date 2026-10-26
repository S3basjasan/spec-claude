<?php

namespace App\Modules\GestionPersonal\Application\Ports;

use DateTimeImmutable;

/**
 * Evaluación del colaborador frente a los requisitos del puesto destino, entregada por el módulo propietario cuando
 * su contrato exista. Cualquier fallo del adaptador se interpreta como «no verificable», nunca como cumplido.
 */
interface EvaluacionDelDestinoPort
{
    /**
     * `contexto` (cuando existe) lleva `colaborador_id`, `puesto_id`, `requisitos` (lista no vacía), `evaluada_en`
     * (ISO 8601), `resultado` (texto) y opcionalmente `vigente_hasta`. Nunca un porcentaje ni un umbral.
     *
     * @return array{estado: 'Valida'|'NoDisponible'|'NoVerificable', contexto: array<string, mixed>|null}
     */
    public function consultar(int $tenantId, int $colaboradorId, int $posicionDestinoId, DateTimeImmutable $instante): array;
}
