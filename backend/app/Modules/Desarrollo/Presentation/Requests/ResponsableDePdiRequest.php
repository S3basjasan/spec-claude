<?php

namespace App\Modules\Desarrollo\Presentation\Requests;

/** Cambio de responsable: el nuevo responsable y la versión leída. */
class ResponsableDePdiRequest extends VersionDePdiRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return parent::rules() + ['responsable_id' => ['required', 'integer', 'min:1']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return parent::messages() + ['responsable_id.required' => 'Elige el responsable del plan.'];
    }

    public function responsableId(): int
    {
        return (int) $this->validated()['responsable_id'];
    }
}
