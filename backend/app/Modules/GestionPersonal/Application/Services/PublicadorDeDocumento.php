<?php

namespace App\Modules\GestionPersonal\Application\Services;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Ports\AlmacenDeDocumentosPort;
use App\Modules\GestionPersonal\Application\Ports\CatalogoDeCategoriasPort;
use App\Modules\GestionPersonal\Application\Ports\TransaccionPort;
use App\Modules\GestionPersonal\Domain\Entities\DocumentoColaborador;
use App\Modules\GestionPersonal\Domain\Exceptions\ClaveDeOperacionReutilizada;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\ConflictoDeUnicidad;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\DocumentoRepositoryInterface;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Publicación en dos fases: objeto privado por intento y, después, una transacción corta que vuelve a autorizar,
 * bloquea la categoría activa e inserta los metadatos. Si algo falla se elimina solo el objeto de este intento.
 */
final class PublicadorDeDocumento
{
    public function __construct(
        private readonly AlmacenDeDocumentosPort $almacen,
        private readonly DocumentoRepositoryInterface $documentos,
        private readonly ColaboradorRepositoryInterface $colaboradores,
        private readonly CatalogoDeCategoriasPort $categorias,
        private readonly TransaccionPort $transacciones,
        private readonly LoggerInterface $log,
    ) {}

    /**
     * @return array{documento: DocumentoColaborador, creado: bool}
     */
    public function publicar(
        ContextoDeTenant $contexto,
        int $actorId,
        int $colaboradorId,
        int $categoriaId,
        string $titulo,
        string $tituloBusqueda,
        bool $visible,
        string $rutaTemporal,
        int $tamano,
        string $sha256,
        string $operacionId,
        string $hash,
    ): array {
        $clave = "personal/{$contexto->tenantId}/colaboradores/{$colaboradorId}/".Str::uuid()->toString().'.pdf';

        $this->almacen->guardar($rutaTemporal, $clave);

        try {
            $documento = $this->transacciones->enTransaccion(function () use ($contexto, $actorId, $colaboradorId, $categoriaId, $titulo, $tituloBusqueda, $visible, $clave, $tamano, $sha256, $operacionId, $hash) {
                $this->colaboradores->porId($contexto->tenantId, $colaboradorId) ?? throw new ColaboradorNoEncontrado;
                $this->categorias->confirmarActiva($contexto->tenantId, $categoriaId);

                return $this->documentos->publicar(
                    $contexto->tenantId, $colaboradorId, $categoriaId, $actorId, $titulo, $tituloBusqueda,
                    $clave, 'application/pdf', $tamano, $sha256, $visible, $operacionId, $hash,
                );
            });
        } catch (ConflictoDeUnicidad $conflicto) {
            // Otro intento ganó: la transacción ya se cerró, se limpia solo este objeto y se resuelve en una consulta nueva.
            $this->limpiar($clave);
            $existente = $this->documentos->porOperacion($contexto->tenantId, $operacionId);
            if ($existente === null) {
                throw $conflicto;
            }
            if ($existente['hash'] !== $hash || $existente['actor'] !== $actorId) {
                throw new ClaveDeOperacionReutilizada;
            }

            return ['documento' => $existente['documento'], 'creado' => false];
        } catch (Throwable $e) {
            $this->limpiar($clave);

            throw $e;
        }

        return ['documento' => $documento, 'creado' => true];
    }

    /** Elimina solo el objeto de este intento; si falla, lo registra sin exponerlo y deja el barrido periódico. */
    private function limpiar(string $clave): void
    {
        if (! $this->almacen->eliminar($clave)) {
            $this->log->error('No se pudo eliminar un objeto de documento no publicado; lo recogerá el barrido periódico.', [
                'huella' => hash('sha256', $clave),
            ]);
        }
    }
}
