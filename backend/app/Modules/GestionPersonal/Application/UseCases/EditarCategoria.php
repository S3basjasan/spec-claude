<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Domain\Entities\CategoriaDocumental;
use App\Modules\GestionPersonal\Domain\Exceptions\CategoriaModificada;
use App\Modules\GestionPersonal\Domain\Exceptions\CategoriaNoEncontrada;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\EscrituraDeshabilitada;
use App\Modules\GestionPersonal\Domain\Exceptions\NombreDeCategoriaDuplicado;
use App\Modules\GestionPersonal\Domain\Repositories\CategoriaRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\NormalizadorDeTexto;
use App\Modules\GestionPersonal\Domain\Services\ReglasDeCategoria;

final class EditarCategoria
{
    public function __construct(
        private readonly CategoriaRepositoryInterface $categorias,
        private readonly ReglasDeCategoria $reglas,
        private readonly NormalizadorDeTexto $normalizador,
    ) {}

    /**
     * Corrige el nombre, con la categoría Activa o Inactiva. Sin cambios reales no se escribe.
     *
     * @param  array<string, mixed>  $datos
     *
     * @throws EscrituraDeshabilitada
     * @throws CategoriaNoEncontrada
     * @throws CategoriaModificada
     * @throws DatosInvalidos
     */
    public function ejecutar(ContextoDeTenant $contexto, int $actorId, int $id, int $version, array $datos): CategoriaDocumental
    {
        if (! config('hrms.gestion_personal.escritura_habilitada', true)) {
            throw new EscrituraDeshabilitada;
        }

        $actual = $this->categorias->porId($contexto->tenantId, $id) ?? throw new CategoriaNoEncontrada;

        if ($actual->version !== $version) {
            throw new CategoriaModificada;
        }

        $final = $this->reglas->normalizar(['nombre' => array_key_exists('nombre', $datos) ? $datos['nombre'] : $actual->nombre]);
        $errores = $this->reglas->errores($final);
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        $nombre = (string) $final['nombre'];

        if ($nombre === $actual->nombre) {
            return $actual;
        }

        $clave = $this->normalizador->claveDeNombre($nombre);

        try {
            if ($this->categorias->existePorClave($contexto->tenantId, $clave, $id)) {
                throw new NombreDeCategoriaDuplicado;
            }

            return $this->categorias->actualizar($contexto->tenantId, $id, $version, $actorId, [
                'nombre' => $nombre,
                'nombre_clave' => $clave,
                'nombre_busqueda' => $this->normalizador->busquedaDeTexto($nombre),
            ]);
        } catch (NombreDeCategoriaDuplicado $e) {
            throw new DatosInvalidos(['nombre' => [$e->getMessage()]]);
        }
    }
}
