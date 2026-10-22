<?php

namespace App\Modules\Desarrollo\Application\Services;

use App\Modules\Desarrollo\Application\Ports\DepartamentosInterface;
use App\Modules\Desarrollo\Application\Ports\PuestosParaRutasInterface;
use App\Modules\Desarrollo\Domain\Entities\EventoDeRuta;
use App\Modules\Desarrollo\Domain\Entities\NivelDeRuta;
use App\Modules\Desarrollo\Domain\Entities\Ruta;
use App\Modules\Desarrollo\Domain\Exceptions\FuenteNoDisponibleParaRuta;
use App\Modules\Desarrollo\Domain\Exceptions\NivelEnUso;
use App\Modules\Desarrollo\Domain\Exceptions\RutaInvalida;
use App\Modules\Desarrollo\Domain\Exceptions\RutaNoPermitida;
use App\Modules\Desarrollo\Domain\Repositories\RutasRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeRuta;
use App\Modules\Desarrollo\Domain\ValueObjects\NombreDeRuta;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeRuta;
use Throwable;

/**
 * Valida los datos de una ruta y de sus niveles contra las reglas y los puertos, arma la entidad y calcula los eventos del
 * historial (alta, edición, reorden, baja de niveles). No guarda nada.
 */
final class PreparadorDeRuta
{
    public const MAXIMO_DESCRIPCION = 500;

    public const MAXIMO_NOMBRE_NIVEL = 80;

    public const MAXIMO_EXPERIENCIA = 60;

    public function __construct(
        private readonly RutasRepositoryInterface $rutas,
        private readonly PuestosParaRutasInterface $puestos,
        private readonly DepartamentosInterface $departamentos,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     * @return array{ruta: Ruta, eventos: list<EventoDeRuta>}
     *
     * @throws RutaInvalida
     * @throws FuenteNoDisponibleParaRuta
     */
    public function crear(int $tenantId, int $usuarioId, array $datos): array
    {
        $errores = [];
        $base = $this->datosGenerales($tenantId, null, $datos, $errores);
        $niveles = $this->niveles($datos['niveles'] ?? [], [], $errores);
        if ($errores !== []) {
            throw new RutaInvalida('Revisa los datos de la ruta.', $errores);
        }

        $ruta = new Ruta(null, $tenantId, null, $base['nombre'], $base['tipo'], $base['descripcion'], $base['departamento_id'], EstadoDeRuta::Borrador, 1, $usuarioId, $usuarioId, $niveles);

        $eventos = [new EventoDeRuta(EventoDeRuta::TIPO_RUTA, 'creada', null, $this->arregloDeRuta($ruta) + ['estado' => EstadoDeRuta::Borrador->value], $usuarioId)];
        foreach ($niveles as $nivel) {
            $eventos[] = new EventoDeRuta(EventoDeRuta::TIPO_NIVEL, 'nivel_agregado', null, $this->arregloDeNivel($nivel), $usuarioId);
        }

        return ['ruta' => $ruta, 'eventos' => $eventos];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{ruta: Ruta, eventos: list<EventoDeRuta>}
     *
     * @throws RutaInvalida
     * @throws NivelEnUso
     * @throws RutaNoPermitida
     * @throws FuenteNoDisponibleParaRuta
     */
    public function actualizar(Ruta $actual, int $usuarioId, array $datos): array
    {
        $errores = [];
        $base = $this->datosGenerales($actual->tenantId, $actual, $datos, $errores);
        $existentes = [];
        foreach ($actual->niveles as $n) {
            $existentes[(int) $n->id] = $n;
        }
        $niveles = $this->niveles($datos['niveles'] ?? [], $existentes, $errores);
        if ($errores !== []) {
            throw new RutaInvalida('Revisa los datos de la ruta.', $errores);
        }

        $enUso = $this->rutas->nivelesEnUso((int) $actual->id);
        foreach ($niveles as $nivel) {
            $previo = $nivel->id === null ? null : ($existentes[$nivel->id] ?? null);
            if ($previo !== null && $previo->puestoId !== $nivel->puestoId && isset($enUso[(int) $nivel->id])) {
                throw new NivelEnUso('No se puede cambiar el puesto de un nivel que es nivel actual u objetivo de una inscripción activa; solo se pueden editar su nombre y su experiencia mínima.');
            }
        }

        $this->validarReorden((int) $actual->id, $niveles);

        $ruta = new Ruta($actual->id, $actual->tenantId, $actual->numero, $base['nombre'], $base['tipo'], $base['descripcion'], $base['departamento_id'], $actual->estado, $actual->version, $actual->creadoPor, $usuarioId, $niveles);

        return ['ruta' => $ruta, 'eventos' => $this->eventosDeEdicion($actual, $ruta, $existentes, $usuarioId)];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, list<string>>  $errores
     * @return array{nombre: string, tipo: TipoDeRuta, descripcion: string|null, departamento_id: int|null}
     */
    private function datosGenerales(int $tenantId, ?Ruta $actual, array $datos, array &$errores): array
    {
        $nombre = new NombreDeRuta((string) ($datos['nombre'] ?? ''));
        if ($nombre->esVacio()) {
            $errores['nombre'][] = 'El nombre es obligatorio.';
        } elseif ($nombre->excedeElMaximo()) {
            $errores['nombre'][] = 'El nombre no puede superar los 80 caracteres.';
        } elseif ($this->rutas->existeNombre($tenantId, $nombre->clave(), $actual?->id)) {
            $errores['nombre'][] = 'Ya existe una ruta con ese nombre.';
        }

        $tipo = TipoDeRuta::tryFrom((string) ($datos['tipo'] ?? ''));
        if ($tipo === null) {
            $errores['tipo'][] = 'Elige el tipo de ruta.';
        }

        $descripcion = isset($datos['descripcion']) ? trim((string) $datos['descripcion']) : null;
        $descripcion = $descripcion === '' ? null : $descripcion;
        if ($descripcion !== null && mb_strlen($descripcion) > self::MAXIMO_DESCRIPCION) {
            $errores['descripcion'][] = 'La descripción no puede superar los 500 caracteres.';
        }

        $departamentoId = isset($datos['departamento_id']) && $datos['departamento_id'] !== '' ? (int) $datos['departamento_id'] : null;
        if ($departamentoId !== null && $tipo !== null) {
            if (! $tipo->admiteDepartamento()) {
                $errores['departamento_id'][] = 'Las rutas Interáreas no pertenecen a un departamento.';
            } elseif (! $this->departamentoActivo($departamentoId)) {
                $errores['departamento_id'][] = 'El departamento no existe o está inactivo.';
            }
        }

        return [
            'nombre' => $nombre->valor,
            'tipo' => $tipo ?? TipoDeRuta::Especialista,
            'descripcion' => $descripcion,
            'departamento_id' => $departamentoId,
        ];
    }

    /**
     * @param  array<int, NivelDeRuta>  $existentes  nivel_id => nivel actual de la ruta (vacío al crear)
     * @param  array<string, list<string>>  $errores
     * @return list<NivelDeRuta>
     */
    private function niveles(mixed $entrada, array $existentes, array &$errores): array
    {
        if (! is_array($entrada)) {
            $errores['niveles'][] = 'Los niveles no son válidos.';

            return [];
        }

        $entrada = array_values($entrada);
        $conOrden = $entrada !== [] && array_reduce($entrada, fn (bool $c, $n) => $c && is_array($n) && isset($n['orden']), true);
        if ($conOrden) {
            usort($entrada, fn ($a, $b) => (int) $a['orden'] <=> (int) $b['orden']);
            foreach ($entrada as $i => $n) {
                if ((int) $n['orden'] !== $i + 1) {
                    $errores['niveles'][] = 'El orden de los niveles debe ser consecutivo desde 1.';
                    break;
                }
            }
        }

        $niveles = [];
        $vistos = [];
        $idsVistos = [];
        foreach ($entrada as $i => $n) {
            $campo = "niveles.{$i}";
            if (! is_array($n)) {
                $errores[$campo][] = 'El nivel no es válido.';

                continue;
            }

            $nivelId = isset($n['id']) && $n['id'] !== '' ? (int) $n['id'] : null;
            if ($nivelId !== null) {
                if (! isset($existentes[$nivelId]) || isset($idsVistos[$nivelId])) {
                    $errores['niveles'][] = 'Hay un nivel que no pertenece a la ruta.';

                    continue;
                }
                $idsVistos[$nivelId] = true;
            }

            $puestoId = (int) ($n['puesto_id'] ?? 0);
            if ($puestoId < 1) {
                $errores["{$campo}.puesto_id"][] = 'Elige el puesto del nivel.';

                continue;
            }
            if (isset($vistos[$puestoId])) {
                $errores["{$campo}.puesto_id"][] = 'Este puesto ya está en la ruta.';

                continue;
            }
            $vistos[$puestoId] = true;

            $previo = $nivelId === null ? null : $existentes[$nivelId];
            $cambiaPuesto = $previo === null || $previo->puestoId !== $puestoId;
            $nombrePuesto = null;
            if ($cambiaPuesto) {
                $perfil = $this->perfilDe($puestoId);
                if ($perfil === null) {
                    $errores["{$campo}.puesto_id"][] = 'El puesto no existe.';

                    continue;
                }
                if (! $perfil['puesto']['activo']) {
                    $errores["{$campo}.puesto_id"][] = 'El puesto está inactivo.';

                    continue;
                }
                $nombrePuesto = $perfil['puesto']['nombre'];
            }

            $nombre = trim((string) ($n['nombre'] ?? ''));
            if ($nombre === '') {
                $nombre = $nombrePuesto ?? ($previo?->nombre ?? $this->perfilDe($puestoId)['puesto']['nombre'] ?? '');
            }
            if (mb_strlen($nombre) > self::MAXIMO_NOMBRE_NIVEL) {
                $errores["{$campo}.nombre"][] = 'El nombre del nivel no puede superar los 80 caracteres.';
            }

            $experiencia = null;
            if (isset($n['experiencia_minima_anios']) && $n['experiencia_minima_anios'] !== '') {
                if (! is_numeric($n['experiencia_minima_anios']) || (float) $n['experiencia_minima_anios'] < 0 || (float) $n['experiencia_minima_anios'] > self::MAXIMO_EXPERIENCIA) {
                    $errores["{$campo}.experiencia_minima_anios"][] = 'La experiencia mínima debe estar entre 0 y 60 años.';
                } else {
                    $experiencia = round((float) $n['experiencia_minima_anios'], 1);
                }
            }

            $niveles[] = new NivelDeRuta($nivelId, count($niveles) + 1, $puestoId, $nombre, $experiencia);
        }

        return $niveles;
    }

    /**
     * Con inscripciones Activas, el nuevo orden no puede dejar un nivel objetivo igual o anterior a su nivel actual.
     *
     * @param  list<NivelDeRuta>  $niveles
     *
     * @throws RutaNoPermitida
     */
    private function validarReorden(int $rutaId, array $niveles): void
    {
        $ordenes = [];
        foreach ($niveles as $n) {
            if ($n->id !== null) {
                $ordenes[$n->id] = $n->orden;
            }
        }
        foreach ($this->rutas->activasDeRuta($rutaId) as $i) {
            if ($i->nivelActualId !== null && $i->nivelObjetivoId !== null
                && isset($ordenes[$i->nivelActualId], $ordenes[$i->nivelObjetivoId])
                && $ordenes[$i->nivelObjetivoId] <= $ordenes[$i->nivelActualId]) {
                throw new RutaNoPermitida('El nuevo orden dejaría el nivel objetivo de una inscripción activa igual o anterior a su nivel actual.', 'reorden_invalido');
            }
        }
    }

    /**
     * @param  array<int, NivelDeRuta>  $existentes
     * @return list<EventoDeRuta>
     */
    private function eventosDeEdicion(Ruta $actual, Ruta $nueva, array $existentes, int $usuarioId): array
    {
        $eventos = [];

        $antes = $this->arregloDeRuta($actual);
        $despues = $this->arregloDeRuta($nueva);
        $cambios = array_keys(array_filter($despues, fn ($v, $k) => $antes[$k] !== $v, ARRAY_FILTER_USE_BOTH));
        if ($cambios !== []) {
            $eventos[] = new EventoDeRuta(EventoDeRuta::TIPO_RUTA, 'editada', array_intersect_key($antes, array_flip($cambios)), array_intersect_key($despues, array_flip($cambios)), $usuarioId);
        }

        $conservados = [];
        foreach ($nueva->niveles as $nivel) {
            if ($nivel->id === null) {
                $eventos[] = new EventoDeRuta(EventoDeRuta::TIPO_NIVEL, 'nivel_agregado', null, $this->arregloDeNivel($nivel), $usuarioId);

                continue;
            }
            $conservados[$nivel->id] = true;
            $previo = $existentes[$nivel->id];
            if ($previo->orden !== $nivel->orden) {
                $eventos[] = new EventoDeRuta(EventoDeRuta::TIPO_NIVEL, 'nivel_reordenado', ['nivel' => $previo->nombre, 'orden' => $previo->orden], ['nivel' => $nivel->nombre, 'orden' => $nivel->orden], $usuarioId);
            }
            $a = $this->arregloDeNivel($previo);
            $d = $this->arregloDeNivel($nivel);
            unset($a['orden'], $d['orden']);
            $difiere = array_keys(array_filter($d, fn ($v, $k) => $a[$k] !== $v, ARRAY_FILTER_USE_BOTH));
            if ($difiere !== []) {
                $eventos[] = new EventoDeRuta(EventoDeRuta::TIPO_NIVEL, 'nivel_editado', ['nivel' => $previo->nombre] + array_intersect_key($a, array_flip($difiere)), ['nivel' => $nivel->nombre] + array_intersect_key($d, array_flip($difiere)), $usuarioId);
            }
        }
        foreach ($existentes as $id => $previo) {
            if (! isset($conservados[$id])) {
                $eventos[] = new EventoDeRuta(EventoDeRuta::TIPO_NIVEL, 'nivel_quitado', $this->arregloDeNivel($previo), null, $usuarioId);
            }
        }

        return $eventos;
    }

    /** @return array<string, mixed> */
    private function arregloDeRuta(Ruta $r): array
    {
        return ['nombre' => $r->nombre, 'tipo' => $r->tipo->value, 'descripcion' => $r->descripcion, 'departamento_id' => $r->departamentoId];
    }

    /** @return array<string, mixed> */
    private function arregloDeNivel(NivelDeRuta $n): array
    {
        return ['orden' => $n->orden, 'puesto_id' => $n->puestoId, 'nombre' => $n->nombre, 'experiencia_minima_anios' => $n->experienciaMinimaAnios];
    }

    /** @return array{puesto: array{id: int, codigo: string, nombre: string, activo: bool}, competencias: list<array<string, mixed>>, requisitos: array<string, list<string>>}|null */
    private function perfilDe(int $puestoId): ?array
    {
        try {
            return $this->puestos->perfil($puestoId);
        } catch (Throwable) {
            throw new FuenteNoDisponibleParaRuta;
        }
    }

    private function departamentoActivo(int $departamentoId): bool
    {
        try {
            return $this->departamentos->estaActivo($departamentoId);
        } catch (Throwable) {
            throw new FuenteNoDisponibleParaRuta('Estructura Empresarial');
        }
    }
}
