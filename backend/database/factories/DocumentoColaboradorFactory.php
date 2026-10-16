<?php

namespace Database\Factories;

use App\Modules\GestionPersonal\Domain\Services\NormalizadorDeTexto;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\DocumentoColaboradorModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DocumentoColaboradorModel>
 */
class DocumentoColaboradorFactory extends Factory
{
    protected $model = DocumentoColaboradorModel::class;

    /**
     * Metadatos sintéticos; el objeto del almacén no se crea (usar el helper de pruebas si se necesita el contenido).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'tenant_id' => 1,
            'colaborador_id' => 1,
            'categoria_id' => 1,
            'titulo' => 'Documento '.fake()->unique()->numerify('######'),
            'titulo_busqueda' => '',
            'object_key' => "personal/1/colaboradores/1/{$uuid}.pdf",
            'mime_type' => 'application/pdf',
            'size_bytes' => 600,
            'sha256' => hash('sha256', $uuid),
            'visible_colaborador' => false,
            'estado' => 'Disponible',
            'version' => 1,
            'created_by' => 1,
            'updated_by' => 1,
            'operacion_id' => $uuid,
            'operacion_hash' => hash('sha256', 'op'.$uuid),
        ];
    }

    public function visible(): static
    {
        return $this->state(fn () => ['visible_colaborador' => true]);
    }

    public function archivado(int $por = 1): static
    {
        return $this->state(fn () => ['estado' => 'Archivado', 'archived_by' => $por, 'archived_at' => now()]);
    }

    public function configure(): static
    {
        return $this->afterMaking(function (DocumentoColaboradorModel $modelo) {
            $modelo->titulo_busqueda = (new NormalizadorDeTexto)->busquedaDeTexto((string) $modelo->titulo);
            if (! str_contains((string) $modelo->object_key, "/colaboradores/{$modelo->colaborador_id}/")) {
                $modelo->object_key = "personal/{$modelo->tenant_id}/colaboradores/{$modelo->colaborador_id}/".Str::uuid().'.pdf';
            }
        });
    }
}
