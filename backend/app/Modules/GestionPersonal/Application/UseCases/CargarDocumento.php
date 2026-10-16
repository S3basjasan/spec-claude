<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\CargaDeDocumentoDTO;
use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\DocumentoConCategoriaDTO;
use App\Modules\GestionPersonal\Application\Ports\ValidadorDePdfPort;
use App\Modules\GestionPersonal\Application\Services\PublicadorDeDocumento;
use App\Modules\GestionPersonal\Application\Services\ResolvedorDeCategoriasDeDocumentos;
use App\Modules\GestionPersonal\Domain\Entities\DocumentoColaborador;
use App\Modules\GestionPersonal\Domain\Exceptions\ArchivoInvalido;
use App\Modules\GestionPersonal\Domain\Exceptions\CategoriaInactiva;
use App\Modules\GestionPersonal\Domain\Exceptions\ClaveDeOperacionRequerida;
use App\Modules\GestionPersonal\Domain\Exceptions\ClaveDeOperacionReutilizada;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\EscrituraDeshabilitada;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\DocumentoRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\NormalizadorDeTexto;
use App\Modules\GestionPersonal\Domain\Services\ReglasDeDocumento;
use Illuminate\Support\Str;

final class CargarDocumento
{
    public function __construct(
        private readonly ColaboradorRepositoryInterface $colaboradores,
        private readonly DocumentoRepositoryInterface $documentos,
        private readonly ValidadorDePdfPort $validador,
        private readonly ResolvedorDeCategoriasDeDocumentos $categorias,
        private readonly PublicadorDeDocumento $publicador,
        private readonly ReglasDeDocumento $reglas,
        private readonly NormalizadorDeTexto $normalizador,
    ) {}

    /**
     * Carga idempotente por clave de operación: repetir la misma operación devuelve el documento ya publicado.
     *
     * @throws EscrituraDeshabilitada
     * @throws ClaveDeOperacionRequerida
     * @throws ClaveDeOperacionReutilizada
     * @throws ColaboradorNoEncontrado
     * @throws DatosInvalidos
     * @throws ArchivoInvalido
     * @throws CategoriaInactiva
     */
    public function ejecutar(ContextoDeTenant $contexto, int $actorId, int $colaboradorId, ?string $claveDeOperacion, CargaDeDocumentoDTO $carga): DocumentoConCategoriaDTO
    {
        if (! config('hrms.gestion_personal.documentos.habilitado', true)) {
            throw new EscrituraDeshabilitada;
        }

        if ($claveDeOperacion === null || ! Str::isUuid($claveDeOperacion)) {
            throw new ClaveDeOperacionRequerida;
        }
        $operacionId = strtolower($claveDeOperacion);

        $this->colaboradores->porId($contexto->tenantId, $colaboradorId) ?? throw new ColaboradorNoEncontrado;

        $datos = $this->reglas->normalizar(['titulo' => $carga->titulo, 'categoria_id' => $carga->categoriaId, 'visible_colaborador' => $carga->visible]);
        $errores = $this->reglas->errores($datos);
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        $titulo = (string) $datos['titulo'];
        $categoriaId = (int) $datos['categoria_id'];

        $tamano = (int) filesize($carga->rutaTemporal);
        $this->reglas->validarTamano($tamano);
        $sha256 = (string) hash_file('sha256', $carga->rutaTemporal);
        $hash = $this->reglas->hashDeOperacion($colaboradorId, $actorId, $titulo, $categoriaId, $carga->visible, $sha256);

        $existente = $this->documentos->porOperacion($contexto->tenantId, $operacionId);
        if ($existente !== null) {
            return $this->repetir($contexto, $existente, $hash, $actorId);
        }

        $resultado = $this->validador->validar($carga->rutaTemporal);
        if (! $resultado->valido) {
            throw new ArchivoInvalido((string) $resultado->motivo);
        }

        $categoria = $this->categorias->porIds($contexto->tenantId, [$categoriaId])[$categoriaId] ?? null;
        if ($categoria === null) {
            throw new DatosInvalidos(['categoria_id' => ['La categoría seleccionada no existe.']]);
        }
        if ($categoria['estado'] !== 'Activa') {
            throw new CategoriaInactiva;
        }

        $publicado = $this->publicador->publicar(
            $contexto, $actorId, $colaboradorId, $categoriaId, $titulo,
            $this->normalizador->busquedaDeTexto($titulo), $carga->visible,
            $carga->rutaTemporal, $tamano, $sha256, $operacionId, $hash,
        );

        return new DocumentoConCategoriaDTO($publicado['documento'], $categoria, $publicado['creado']);
    }

    /**
     * @param  array{documento: DocumentoColaborador, hash: string, actor: int}  $existente
     */
    private function repetir(ContextoDeTenant $contexto, array $existente, string $hash, int $actorId): DocumentoConCategoriaDTO
    {
        if ($existente['hash'] !== $hash || $existente['actor'] !== $actorId) {
            throw new ClaveDeOperacionReutilizada;
        }

        $documento = $existente['documento'];
        $categoria = $this->categorias->porIds($contexto->tenantId, [$documento->categoriaId])[$documento->categoriaId] ?? null;

        return new DocumentoConCategoriaDTO($documento, $categoria, false);
    }
}
