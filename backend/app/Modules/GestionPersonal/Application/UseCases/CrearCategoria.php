<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Domain\Entities\CategoriaDocumental;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\EscrituraDeshabilitada;
use App\Modules\GestionPersonal\Domain\Exceptions\NombreDeCategoriaDuplicado;
use App\Modules\GestionPersonal\Domain\Repositories\CategoriaRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\NormalizadorDeTexto;
use App\Modules\GestionPersonal\Domain\Services\ReglasDeCategoria;

final class CrearCategoria
{
    public function __construct(
        private readonly CategoriaRepositoryInterface $categorias,
        private readonly ReglasDeCategoria $reglas,
        private readonly NormalizadorDeTexto $normalizador,
    ) {}

    /**
     * La categoría nace Activa. La restricción única decide ante dos creaciones simultáneas.
     *
     * @param  array<string, mixed>  $datos
     *
     * @throws EscrituraDeshabilitada
     * @throws DatosInvalidos
     */
    public function ejecutar(ContextoDeTenant $contexto, int $actorId, array $datos): CategoriaDocumental
    {
        if (! config('hrms.gestion_personal.escritura_habilitada', true)) {
            throw new EscrituraDeshabilitada;
        }

        $final = $this->reglas->normalizar(['nombre' => $datos['nombre'] ?? null]);
        $errores = $this->reglas->errores($final);
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        $nombre = (string) $final['nombre'];
        $clave = $this->normalizador->claveDeNombre($nombre);

        try {
            if ($this->categorias->existePorClave($contexto->tenantId, $clave)) {
                throw new NombreDeCategoriaDuplicado;
            }

            return $this->categorias->crear($contexto->tenantId, $actorId, $nombre, $clave, $this->normalizador->busquedaDeTexto($nombre));
        } catch (NombreDeCategoriaDuplicado $e) {
            throw new DatosInvalidos(['nombre' => [$e->getMessage()]]);
        }
    }
}
