<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\PrevisualizacionDTO;
use App\Modules\GestionPersonal\Application\Ports\RelojPort;
use App\Modules\GestionPersonal\Application\Services\ResolvedorDeBorrador;
use App\Modules\GestionPersonal\Domain\Exceptions\BorradorModificado;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\InicioEnElPasado;
use App\Modules\GestionPersonal\Domain\Exceptions\PoliticaNoEncontrada;
use App\Modules\GestionPersonal\Domain\Repositories\PoliticaRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\ClasificadorDeVersiones;
use App\Modules\GestionPersonal\Domain\Services\DiferenciasDePolitica;
use App\Modules\GestionPersonal\Domain\Services\InicioDeVigencia;
use App\Modules\GestionPersonal\Domain\Services\ReglasDePolitica;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;

/** Resumen previo a publicar. No escribe nada: lista las condiciones, las diferencias, el inicio y los errores. */
final class PrevisualizarPublicacion
{
    public function __construct(
        private readonly PoliticaRepositoryInterface $politicas,
        private readonly ResolvedorDeBorrador $borradores,
        private readonly ClasificadorDeVersiones $clasificador,
        private readonly ReglasDePolitica $reglas,
        private readonly DiferenciasDePolitica $diferencias,
        private readonly InicioDeVigencia $inicios,
        private readonly RelojPort $reloj,
    ) {}

    /**
     * @param  array<string, mixed>|null  $inicio
     *
     * @throws PoliticaNoEncontrada
     * @throws BorradorModificado
     */
    public function ejecutar(ContextoDeTenant $contexto, TipoDePolitica $tipo, int $versionId, ?array $inicio): PrevisualizacionDTO
    {
        $ahora = $this->reloj->ahora();
        $cabecera = $this->politicas->cabecera($contexto->tenantId, $tipo) ?? throw new PoliticaNoEncontrada;
        $borrador = $this->borradores->obtener($contexto->tenantId, $cabecera->id, $versionId);
        $versiones = $this->politicas->versionesDe($contexto->tenantId, [$cabecera->id])[$cabecera->id] ?? [];

        $errores = $this->reglas->erroresDeCompletitud($tipo, $borrador->descripcion, $borrador->parametros);

        $resuelto = null;
        if ($inicio !== null) {
            try {
                $resuelto = $this->inicios->resolver($inicio, $ahora, $contexto->zonaHoraria);
            } catch (DatosInvalidos $e) {
                $errores = array_merge($errores, $e->errores);
            } catch (InicioEnElPasado $e) {
                $errores['inicio.fecha_hora_local'] = [$e->getMessage()];
            }
        }

        if ($this->clasificador->programada($versiones, $ahora) !== null) {
            $errores['publicacion'] = ['Ya existe una versión programada para este tipo. Retírala antes de publicar otra.'];
        }

        $vigente = $this->clasificador->vigente($versiones, $ahora);
        $parametros = $this->reglas->canonicos($tipo, $borrador->parametros);
        $sellado = $this->reglas->sellar($tipo, $cabecera->ultimoNumero + 1, $resuelto === null ? '' : $resuelto['utc']->format('Y-m-d\TH:i:s\Z'), $parametros);

        return new PrevisualizacionDTO(
            $sellado['condiciones'],
            $this->diferencias->entre(
                $tipo,
                ['descripcion' => $borrador->descripcion, 'parametros' => $parametros],
                $vigente === null ? null : ['descripcion' => $vigente->descripcion, 'parametros' => $vigente->parametros],
            ),
            $resuelto['utc'] ?? null,
            $resuelto['local'] ?? null,
            [
                'momento' => $resuelto === null ? null : $resuelto['utc']->format('Y-m-d\TH:i:s\Z'),
                'aplica_a' => 'nuevas solicitudes',
                'solicitudes_anteriores' => 'conservan sus condiciones',
                'sustituye' => $vigente?->numero,
            ],
            $errores,
        );
    }
}
