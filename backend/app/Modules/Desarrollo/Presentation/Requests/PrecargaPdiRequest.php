<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

use App\Modules\Desarrollo\Domain\ValueObjects\TipoDePdi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Colaborador, tipo y (en Carrera y Talento) puesto objetivo desde los que se sugieren los compromisos. */
class PrecargaPdiRequest extends FormRequest
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
            'colaborador_id' => ['required', 'integer', 'min:1'],
            'tipo' => ['required', Rule::in(TipoDePdi::valores())],
            'puesto_objetivo_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'colaborador_id.required' => 'Elige un colaborador.',
            'tipo.required' => 'Elige el tipo de plan.',
            'tipo.in' => 'El tipo de plan no es válido.',
        ];
    }

    public function colaboradorId(): int
    {
        return (int) $this->validated()['colaborador_id'];
    }

    public function tipo(): TipoDePdi
    {
        return TipoDePdi::from($this->validated()['tipo']);
    }

    public function puestoObjetivoId(): ?int
    {
        $v = $this->validated();

        return isset($v['puesto_objetivo_id']) ? (int) $v['puesto_objetivo_id'] : null;
    }
}
