<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeRuta;
use Illuminate\Foundation\Http\FormRequest;

/** Pasa una ruta a Vigente o a Archivada (una ruta nunca vuelve a Borrador). */
class CambiarEstadoRutaRequest extends FormRequest
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
            'version' => ['required', 'integer', 'min:1'],
            'estado' => ['required', 'string', 'in:Vigente,Archivada'],
            'confirmar_inscritos' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'version.required' => 'Falta la versión de la ruta.',
            'estado.required' => 'Elige el estado.',
            'estado.in' => 'El estado debe ser Vigente o Archivada.',
            'confirmar_inscritos.boolean' => 'La confirmación no es válida.',
        ];
    }

    public function version(): int
    {
        return (int) $this->validated()['version'];
    }

    public function estado(): EstadoDeRuta
    {
        return EstadoDeRuta::from($this->validated()['estado']);
    }

    public function confirmaInscritos(): bool
    {
        return (bool) ($this->validated()['confirmar_inscritos'] ?? false);
    }
}
