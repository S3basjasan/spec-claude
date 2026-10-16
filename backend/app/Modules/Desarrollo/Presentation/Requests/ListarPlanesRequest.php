<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoPlan;
use App\Modules\Desarrollo\Domain\ValueObjects\FiltroPlanes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarPlanesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'anio' => ['nullable', 'integer', 'between:2000,2100'],
            'estado' => ['nullable', Rule::enum(EstadoPlan::class)],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'anio.integer' => 'El año debe ser un número entero.',
            'anio.between' => 'El año no es válido.',
            'estado.Illuminate\Validation\Rules\Enum' => 'El estado debe ser Borrador, Revision, Aprobado, Ejecutando o Cerrado.',
            'q.max' => 'La búsqueda no puede superar 120 caracteres.',
            'page.integer' => 'La página debe ser un número entero.',
            'page.min' => 'La página debe ser 1 o mayor.',
            'per_page.integer' => 'El tamaño de página debe ser un número entero.',
            'per_page.between' => 'El tamaño de página debe estar entre 1 y 100.',
        ];
    }

    public function filtro(): FiltroPlanes
    {
        $d = $this->validated();

        return new FiltroPlanes(
            isset($d['anio']) ? (int) $d['anio'] : null,
            isset($d['estado']) ? EstadoPlan::from($d['estado']) : null,
            $d['q'] ?? null,
            (int) ($d['page'] ?? 1),
            (int) ($d['per_page'] ?? 10),
        );
    }
}
