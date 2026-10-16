<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\Ports\AlmacenDeDocumentosPort;
use App\Modules\GestionPersonal\Domain\Repositories\DocumentoRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Barrido de compensación: elimina solo los objetos del prefijo técnico que ninguna fila referencia y que llevan más
 * de `$horas` horas. Nunca toca documentos publicados ni archivados.
 */
final class LimpiarObjetosHuerfanos
{
    public const PREFIJO = 'personal';

    public function __construct(
        private readonly AlmacenDeDocumentosPort $almacen,
        private readonly DocumentoRepositoryInterface $documentos,
        private readonly LoggerInterface $log,
    ) {}

    /** @return array{revisados: int, eliminados: int, conservados: int, fallidos: int} */
    public function ejecutar(int $horas, ?int $ahora = null): array
    {
        $limite = ($ahora ?? time()) - $horas * 3600;
        $resumen = ['revisados' => 0, 'eliminados' => 0, 'conservados' => 0, 'fallidos' => 0];

        foreach ($this->almacen->listar(self::PREFIJO) as $objeto) {
            $resumen['revisados']++;

            if ($objeto['modificado_en'] > $limite || $this->documentos->claveReferenciada($objeto['clave'])) {
                $resumen['conservados']++;

                continue;
            }

            if ($this->almacen->eliminar($objeto['clave'])) {
                $resumen['eliminados']++;
            } else {
                $resumen['fallidos']++;
                $this->log->error('No se pudo eliminar un objeto huérfano de documentos.', ['huella' => hash('sha256', $objeto['clave'])]);
            }
        }

        return $resumen;
    }
}
