<?php

namespace App\Modules\GestionPersonal\Presentation\Requests;

use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;
use Illuminate\Validation\Rule;

class ListarPoliticasRequest extends PoliticaRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'tipo' => ['nullable', 'string', Rule::in(TipoDePolitica::identificadores())],
            'situacion' => ['nullable', 'string', Rule::in(['SinConfigurar', 'Vigente', 'NoAdmiteSolicitudes', 'ConProgramada'])],
        ];
    }

    public function tipo(): ?TipoDePolitica
    {
        $tipo = $this->validated('tipo');

        return is_string($tipo) ? TipoDePolitica::desdeIdentificador($tipo) : null;
    }

    public function situacion(): ?string
    {
        $situacion = $this->validated('situacion');

        return is_string($situacion) ? $situacion : null;
    }
}
