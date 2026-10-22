<?php

namespace App\Modules\Desarrollo\Domain\Entities;

use App\Modules\Desarrollo\Domain\ValueObjects\CodigoDeRuta;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeRuta;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeRuta;
use DateTimeImmutable;

/** Ruta de carrera: guía de desarrollo formada por niveles ordenados. No promete un puesto y nunca se elimina. */
final class Ruta
{
    /**
     * @param  list<NivelDeRuta>  $niveles  ordenados por `orden`
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly ?int $numero,
        public readonly string $nombre,
        public readonly TipoDeRuta $tipo,
        public readonly ?string $descripcion,
        public readonly ?int $departamentoId,
        public readonly EstadoDeRuta $estado,
        public readonly int $version,
        public readonly ?int $creadoPor,
        public readonly ?int $actualizadoPor,
        public readonly array $niveles = [],
        public readonly ?DateTimeImmutable $creadoEn = null,
        public readonly ?DateTimeImmutable $actualizadoEn = null,
    ) {}

    public function codigo(): ?string
    {
        return $this->numero === null ? null : CodigoDeRuta::desde($this->numero);
    }

    /** @return list<int> */
    public function idsDePuestos(): array
    {
        return array_map(fn (NivelDeRuta $n) => $n->puestoId, $this->niveles);
    }

    public function nivelPorId(int $nivelId): ?NivelDeRuta
    {
        foreach ($this->niveles as $nivel) {
            if ($nivel->id === $nivelId) {
                return $nivel;
            }
        }

        return null;
    }

    /** @return array<int, int> nivel_id => orden */
    public function ordenesPorNivel(): array
    {
        $r = [];
        foreach ($this->niveles as $nivel) {
            if ($nivel->id !== null) {
                $r[$nivel->id] = $nivel->orden;
            }
        }

        return $r;
    }

    public function ultimoOrden(): int
    {
        return $this->niveles === [] ? 0 : max(array_map(fn (NivelDeRuta $n) => $n->orden, $this->niveles));
    }
}
