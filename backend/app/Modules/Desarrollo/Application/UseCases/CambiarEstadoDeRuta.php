<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\PuestosParaRutasInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDeRutas;
use App\Modules\Desarrollo\Domain\Entities\EventoDeRuta;
use App\Modules\Desarrollo\Domain\Exceptions\ConflictoDeVersionRuta;
use App\Modules\Desarrollo\Domain\Exceptions\FuenteNoDisponibleParaRuta;
use App\Modules\Desarrollo\Domain\Exceptions\RutaNoEncontrada;
use App\Modules\Desarrollo\Domain\Exceptions\RutaNoPermitida;
use App\Modules\Desarrollo\Domain\Repositories\RutasRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\ReglasDePublicacionDeRuta;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeRuta;
use Throwable;

/** Publica, archiva o reactiva una ruta. Archivar con inscripciones Activas exige confirmación e informa la cantidad. */
final class CambiarEstadoDeRuta
{
    public function __construct(
        private readonly RutasRepositoryInterface $rutas,
        private readonly PuestosParaRutasInterface $puestos,
        private readonly ReglasDePublicacionDeRuta $reglas,
        private readonly ArmadorDeRutas $armador,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws RutaNoEncontrada
     * @throws ConflictoDeVersionRuta
     * @throws RutaNoPermitida con `transicion_invalida`, `no_publicable` o `requiere_confirmacion`
     * @throws FuenteNoDisponibleParaRuta
     */
    public function ejecutar(int $tenantId, int $usuarioId, int $rutaId, int $version, EstadoDeRuta $destino, bool $confirmarInscritos = false): array
    {
        $ruta = $this->rutas->obtener($tenantId, $rutaId) ?? throw new RutaNoEncontrada;
        if ($version !== $ruta->version) {
            throw new ConflictoDeVersionRuta;
        }
        if (! $ruta->estado->puedePasarA($destino)) {
            throw new RutaNoPermitida("Una ruta {$ruta->estado->value} no puede pasar a {$destino->value}.", 'transicion_invalida');
        }

        if ($destino === EstadoDeRuta::Vigente) {
            $motivos = $this->reglas->motivos($ruta, $this->puestosInactivos($ruta->idsDePuestos()));
            if ($motivos !== []) {
                throw new RutaNoPermitida(implode(' ', $motivos), 'no_publicable', ['motivos' => $motivos]);
            }
        }

        if ($destino === EstadoDeRuta::Archivada) {
            $inscritos = count($this->rutas->activasDeRuta($rutaId));
            if ($inscritos > 0 && ! $confirmarInscritos) {
                throw new RutaNoPermitida("La ruta tiene {$inscritos} inscripciones activas; confirma para archivarla.", 'requiere_confirmacion', ['inscritos' => $inscritos]);
            }
        }

        $evento = new EventoDeRuta(EventoDeRuta::TIPO_RUTA, 'estado_cambiado', ['estado' => $ruta->estado->value], ['estado' => $destino->value], $usuarioId);

        return $this->armador->detalle($this->rutas->cambiarEstado($tenantId, $rutaId, $version, $destino, $usuarioId, $evento));
    }

    /**
     * @param  list<int>  $puestoIds
     * @return list<int>
     *
     * @throws FuenteNoDisponibleParaRuta
     */
    private function puestosInactivos(array $puestoIds): array
    {
        try {
            return array_values(array_filter(array_unique($puestoIds), fn (int $id) => ! $this->puestos->estaActivo($id)));
        } catch (Throwable) {
            throw new FuenteNoDisponibleParaRuta;
        }
    }
}
