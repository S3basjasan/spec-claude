<?php

namespace App\Modules\Compensaciones\Domain\Entities;

use App\Modules\Compensaciones\Domain\Exceptions\DatosDeCategoriaInvalidos;
use App\Modules\Compensaciones\Domain\ValueObjects\EstadoCategoria;
use App\Modules\Compensaciones\Domain\ValueObjects\Icono;
use App\Modules\Compensaciones\Domain\ValueObjects\Indicador;
use App\Modules\Compensaciones\Domain\ValueObjects\NombreCategoria;
use App\Modules\Compensaciones\Domain\ValueObjects\Normalizador;
use App\Modules\Compensaciones\Domain\ValueObjects\PuntuacionBase;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Categoría del catálogo maestro de reconocimiento. Nunca se elimina: se activa y se desactiva.
 */
final class Categoria
{
    public const MIN_INDICADORES = 1;

    public const MAX_INDICADORES = 5;

    public const MIN_DESCRIPCION = 10;

    public const MAX_DESCRIPCION = 500;

    /**
     * @param  array<int, Indicador>  $indicadores
     * @param  string|null  $version  Valor tal como está guardado en `updated_at`; es el token de concurrencia.
     */
    public function __construct(
        public readonly ?int $id,
        public readonly ?string $codigo,
        public readonly NombreCategoria $nombre,
        public readonly string $descripcion,
        public readonly int $competenciaId,
        public readonly PuntuacionBase $puntuacion,
        public readonly Icono $icono,
        public readonly EstadoCategoria $estado,
        public readonly array $indicadores,
        public readonly ?DateTimeImmutable $creadaEn = null,
        public readonly ?DateTimeImmutable $actualizadaEn = null,
        public readonly ?int $creadaPor = null,
        public readonly ?int $actualizadaPor = null,
        public readonly ?string $version = null,
    ) {}

    /**
     * Valida y arma los datos de una categoría nueva (nace Activa) o de la versión propuesta en una edición.
     *
     * @param  array<int, string>  $textosDeIndicadores
     *
     * @throws DatosDeCategoriaInvalidos
     */
    public static function propuesta(
        string $nombre,
        string $descripcion,
        int $competenciaId,
        mixed $puntuacion,
        string $icono,
        array $textosDeIndicadores,
        EstadoCategoria $estado = EstadoCategoria::Activo,
    ): self {
        $descripcion = Normalizador::recortar($descripcion);
        $largo = mb_strlen($descripcion);

        if ($largo < self::MIN_DESCRIPCION || $largo > self::MAX_DESCRIPCION) {
            throw new DatosDeCategoriaInvalidos('descripcion', 'La descripción debe tener entre 10 y 500 caracteres.');
        }

        if (Normalizador::contieneHtml($descripcion)) {
            throw new DatosDeCategoriaInvalidos('descripcion', 'La descripción no puede contener etiquetas HTML.');
        }

        $iconoValido = Icono::tryFrom($icono)
            ?? throw new DatosDeCategoriaInvalidos('icono', 'El ícono seleccionado no es válido.');

        return new self(
            null,
            null,
            new NombreCategoria($nombre),
            $descripcion,
            $competenciaId,
            new PuntuacionBase($puntuacion),
            $iconoValido,
            $estado,
            self::indicadoresDesde($textosDeIndicadores),
        );
    }

    /**
     * Convierte la lista enviada en indicadores con su orden consecutivo desde 1.
     *
     * @param  array<int, string>  $textos
     * @return array<int, Indicador>
     *
     * @throws DatosDeCategoriaInvalidos
     */
    public static function indicadoresDesde(array $textos): array
    {
        $textos = array_values($textos);

        if (count($textos) < self::MIN_INDICADORES || count($textos) > self::MAX_INDICADORES) {
            throw new DatosDeCategoriaInvalidos('indicadores', 'La categoría debe tener entre 1 y 5 indicadores.');
        }

        $indicadores = [];
        $vistos = [];

        foreach ($textos as $posicion => $texto) {
            $indicador = new Indicador((string) $texto, $posicion + 1, $posicion);

            if (isset($vistos[$indicador->normalizado()])) {
                throw new DatosDeCategoriaInvalidos("indicadores.$posicion", 'Los indicadores no pueden repetirse.');
            }

            $vistos[$indicador->normalizado()] = true;
            $indicadores[] = $indicador;
        }

        return $indicadores;
    }

    public function estaActiva(): bool
    {
        return $this->estado === EstadoCategoria::Activo;
    }

    /** Seleccionable: Activa y con su competencia activa. Se calcula al consultar; no se guarda. */
    public function esSeleccionable(bool $competenciaActiva): bool
    {
        return $this->estaActiva() && $competenciaActiva;
    }

    /** Indica si el token recibido (ISO 8601 con microsegundos) corresponde a la versión guardada. */
    public function coincideVersion(string $tokenRecibido): bool
    {
        if ($this->actualizadaEn === null) {
            return false;
        }

        try {
            $recibido = new DateTimeImmutable($tokenRecibido);
        } catch (\Exception) {
            return false;
        }

        return self::formatoComparable($recibido) === self::formatoComparable($this->actualizadaEn);
    }

    /** ISO 8601 en UTC con microsegundos: el valor que viaja al cliente como `updated_at`. */
    public function tokenParaCliente(): ?string
    {
        return $this->actualizadaEn?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
    }

    /**
     * Campos que cambian respecto de otra versión, con su valor anterior y nuevo en texto (para la auditoría).
     * El estado se compara solo si interesa: las ediciones de datos lo conservan.
     *
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    public function diferenciasCon(self $propuesta): array
    {
        $cambios = [];
        $pares = [
            'nombre' => [$this->nombre->valor, $propuesta->nombre->valor],
            'descripcion' => [$this->descripcion, $propuesta->descripcion],
            'competencia_id' => [(string) $this->competenciaId, (string) $propuesta->competenciaId],
            'puntuacion_base' => [(string) $this->puntuacion->valor, (string) $propuesta->puntuacion->valor],
            'icono' => [$this->icono->value, $propuesta->icono->value],
            'indicadores' => [$this->indicadoresComoJson(), $propuesta->indicadoresComoJson()],
            'estado' => [$this->estado->value, $propuesta->estado->value],
        ];

        foreach ($pares as $campo => [$antes, $despues]) {
            if ($antes !== $despues) {
                $cambios[$campo] = [$antes, $despues];
            }
        }

        return $cambios;
    }

    public function indicadoresComoJson(): string
    {
        return (string) json_encode(
            array_map(fn (Indicador $i) => $i->texto, $this->indicadores),
            JSON_UNESCAPED_UNICODE,
        );
    }

    private static function formatoComparable(DateTimeImmutable $fecha): string
    {
        return $fecha->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
