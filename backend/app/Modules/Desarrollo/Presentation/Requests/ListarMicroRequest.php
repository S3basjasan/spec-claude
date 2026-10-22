<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoMicro;
use App\Modules\Desarrollo\Domain\ValueObjects\FiltroMicro;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoMicro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarMicroRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:50'],
            'tipo' => ['nullable', 'string', Rule::in(array_map(fn (TipoMicro $t) => $t->value, TipoMicro::cases()))],
            'estado' => ['nullable', 'string', Rule::in(['Borrador', 'Publicado', 'Archivado', 'Activos', 'Todos'])],
            'competencia_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,'.FiltroMicro::MAX_POR_PAGINA],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.max' => 'La búsqueda no puede superar los 50 caracteres.',
            'tipo.in' => 'El tipo debe ser Video, PDF, Artículo o Quiz.',
            'estado.in' => 'El estado no es válido.',
            'competencia_id.integer' => 'La competencia no es válida.',
            'page.integer' => 'La página debe ser un número entero.',
            'page.min' => 'La página debe ser 1 o mayor.',
            'per_page.between' => 'El tamaño de página debe estar entre 1 y '.FiltroMicro::MAX_POR_PAGINA.'.',
        ];
    }

    /** El estado solo lo decide el Administrador: por defecto «Activos» (Borrador y Publicado). */
    public function filtro(): FiltroMicro
    {
        $pedido = $this->validated('estado') ?? 'Activos';
        $estados = match ($pedido) {
            'Activos' => [EstadoMicro::Borrador, EstadoMicro::Publicado],
            'Todos' => [],
            default => [EstadoMicro::from($pedido)],
        };

        return new FiltroMicro(
            $this->filled('tipo') ? TipoMicro::from((string) $this->validated('tipo')) : null,
            $estados,
            $this->filled('competencia_id') ? (int) $this->validated('competencia_id') : null,
            $this->validated('q'),
            (int) ($this->validated('page') ?? 1),
            (int) ($this->validated('per_page') ?? 12),
        );
    }
}
