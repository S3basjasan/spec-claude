<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

use App\Modules\Desarrollo\Domain\Exceptions\PesosInvalidos;

/** Pesos por dimensión: tres enteros no negativos que suman 100. */
final class PesosPorDimension
{
    public function __construct(
        public readonly int $competencias,
        public readonly int $conocimientos,
        public readonly int $experiencia,
    ) {
        if ($competencias < 0 || $conocimientos < 0 || $experiencia < 0) {
            throw PesosInvalidos::negativos();
        }
        if ($competencias + $conocimientos + $experiencia !== 100) {
            throw PesosInvalidos::sumaIncorrecta($competencias + $conocimientos + $experiencia);
        }
    }

    public function de(DimensionDeAdecuacion $dimension): int
    {
        return match ($dimension) {
            DimensionDeAdecuacion::Competencia => $this->competencias,
            DimensionDeAdecuacion::Conocimiento => $this->conocimientos,
            DimensionDeAdecuacion::Experiencia => $this->experiencia,
        };
    }

    /**
     * Pesos de las dimensiones evaluadas, reescalados para que sumen 1. Si todas las dimensiones evaluadas pesan 0
     * se reparten por igual.
     *
     * @param  list<DimensionDeAdecuacion>  $presentes
     * @return array<string, float> dimensión => fracción de 0 a 1
     */
    public function reescalar(array $presentes): array
    {
        if ($presentes === []) {
            return [];
        }

        $suma = array_sum(array_map(fn (DimensionDeAdecuacion $d) => $this->de($d), $presentes));
        $salida = [];
        foreach ($presentes as $d) {
            $salida[$d->value] = $suma === 0 ? 1 / count($presentes) : $this->de($d) / $suma;
        }

        return $salida;
    }

    /** @return array<string, int> */
    public function comoArreglo(): array
    {
        return [
            DimensionDeAdecuacion::Competencia->value => $this->competencias,
            DimensionDeAdecuacion::Conocimiento->value => $this->conocimientos,
            DimensionDeAdecuacion::Experiencia->value => $this->experiencia,
        ];
    }
}
