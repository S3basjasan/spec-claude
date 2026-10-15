<?php

namespace App\Modules\Estructura\Presentation\Requests;

use App\Modules\Estructura\Domain\ValueObjects\BorradorDeTipo;
use Illuminate\Foundation\Http\FormRequest;

class GuardarCadenaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Solo la forma de la lista. Que contenga exactamente los tipos existentes, el tipo raíz primero, nombres únicos y que
     * ninguna unidad quede en conflicto lo comprueba el repositorio contra el estado vigente.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'string', 'max:64'],
            'tipos' => ['required', 'array', 'min:1'],
            'tipos.*.id' => ['required', 'integer', 'min:1'],
            'tipos.*.nombre' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'version.required' => 'Falta la versión de la cadena. Recarga e inténtalo de nuevo.',
            'tipos.required' => 'Falta la lista de tipos.',
            'tipos.array' => 'La lista de tipos no es válida.',
            'tipos.min' => 'La lista de tipos no puede estar vacía.',
            'tipos.*.id.required' => 'Falta el identificador del tipo.',
            'tipos.*.id.integer' => 'El identificador del tipo no es válido.',
            'tipos.*.nombre.required' => 'El nombre es obligatorio.',
            'tipos.*.nombre.string' => 'El nombre no es válido.',
        ];
    }

    public function version(): string
    {
        return (string) $this->validated('version');
    }

    /**
     * @return array<int, BorradorDeTipo>
     */
    public function borrador(): array
    {
        return array_map(
            fn (array $tipo) => new BorradorDeTipo((int) $tipo['id'], trim((string) $tipo['nombre'])),
            array_values($this->validated('tipos')),
        );
    }
}
