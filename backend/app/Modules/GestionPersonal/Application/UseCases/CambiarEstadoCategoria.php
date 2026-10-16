<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Domain\Entities\CategoriaDocumental;
use App\Modules\GestionPersonal\Domain\Exceptions\CategoriaModificada;
use App\Modules\GestionPersonal\Domain\Exceptions\CategoriaNoEncontrada;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\EscrituraDeshabilitada;
use App\Modules\GestionPersonal\Domain\Repositories\CategoriaRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\ReglasDeCategoria;

final class CambiarEstadoCategoria
{
    public function __construct(
        private readonly CategoriaRepositoryInterface $categorias,
        private readonly ReglasDeCategoria $reglas,
    ) {}

    /**
     * Desactiva o reactiva. No consulta ni modifica documentos: solo cambia la categoría.
     *
     * @throws EscrituraDeshabilitada
     * @throws CategoriaNoEncontrada
     * @throws CategoriaModificada
     * @throws DatosInvalidos
     */
    public function ejecutar(ContextoDeTenant $contexto, int $actorId, int $id, int $version, mixed $estado): CategoriaDocumental
    {
        if (! config('hrms.gestion_personal.escritura_habilitada', true)) {
            throw new EscrituraDeshabilitada;
        }

        $actual = $this->categorias->porId($contexto->tenantId, $id) ?? throw new CategoriaNoEncontrada;

        if ($actual->version !== $version) {
            throw new CategoriaModificada;
        }

        $errores = $this->reglas->erroresEstado($estado);
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        if ($estado === $actual->estado) {
            return $actual;
        }

        return $this->categorias->actualizar($contexto->tenantId, $id, $version, $actorId, ['estado' => (string) $estado]);
    }
}
