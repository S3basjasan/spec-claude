<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

/** Exportación: los filtros comunes más el formato (xlsx o pdf; cualquier otro valor lo rechaza el caso de uso con `formato_invalido`). */
class ExportarReportesRequest extends FiltrosReportesRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return parent::rules() + ['formato' => ['required', 'string', 'max:10']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return parent::messages() + ['formato.required' => 'El formato de exportación es obligatorio (xlsx o pdf).'];
    }
}
