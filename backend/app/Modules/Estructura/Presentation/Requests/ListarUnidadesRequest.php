<?php

namespace App\Modules\Estructura\Presentation\Requests;

use App\Modules\Estructura\Application\DTO\FiltroUnidadesDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarUnidadesRequest extends FormRequest
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
            'estado' => ['nullable', 'string', Rule::in(['Activa', 'Inactiva', 'Todas'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.max' => 'La búsqueda no puede superar los 50 caracteres.',
            'estado.in' => 'El estado debe ser Activa, Inactiva o Todas.',
        ];
    }

    /**
     * Resuelve el estado efectivo según el rol: quien no es administrador solo recibe unidades `Activa` (el parámetro
     * se ignora); el administrador recibe `Activa` por defecto, o `Inactiva`/`Todas` si lo pide.
     */
    public function filtro(bool $esAdministrador): FiltroUnidadesDTO
    {
        $pedido = $esAdministrador ? ($this->validated('estado') ?? 'Activa') : 'Activa';

        return new FiltroUnidadesDTO($this->validated('q'), $pedido === 'Todas' ? null : $pedido);
    }
}
