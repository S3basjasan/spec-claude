<?php

use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\CategoriaDocumentalModel;
use Database\Seeders\PersonalSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('el catálogo funciona completo sin ningún documento y sin consultar la tabla documental', function () {
    actuarComoAdminDeTenant();
    $consultas = [];
    DB::listen(function ($consulta) use (&$consultas) {
        $consultas[] = strtolower($consulta->sql);
    });

    expect(Schema::hasTable('categorias_documentales'))->toBeTrue();

    $creada = $this->postJson(URL_CATEGORIAS, ['nombre' => 'Contratos'])->assertCreated()->json('data');
    $this->getJson(URL_CATEGORIAS)->assertOk()->assertJsonCount(1, 'data');
    $this->getJson(urlCategoria($creada['id']))->assertOk();
    $this->patchJson(urlCategoria($creada['id']), ['nombre' => 'Contratos 2', 'version' => 1])->assertOk();
    $this->patchJson(urlCategoria($creada['id'], '/estado'), ['estado' => 'Inactiva', 'version' => 2])->assertOk();

    expect(array_filter($consultas, fn ($sql) => str_contains($sql, 'documentos_colaborador')))->toBe([]);
});

it('no tiene jerarquía, código ni tipos: solo las columnas del modelo de datos', function () {
    expect(Schema::getColumnListing('categorias_documentales'))->toEqualCanonicalizing([
        'id', 'tenant_id', 'nombre', 'nombre_clave', 'nombre_busqueda', 'estado', 'version',
        'created_by', 'updated_by', 'created_at', 'updated_at',
    ]);
});

it('el seeder de personal carga Contratos, Certificados y una categoría inactiva de forma idempotente', function () {
    config(['hrms.seed_password' => 'secreto-de-prueba-123']);

    $this->seed(PersonalSeeder::class);
    $this->seed(PersonalSeeder::class);

    $filas = CategoriaDocumentalModel::query()->where('tenant_id', 1)->orderBy('nombre')->pluck('estado', 'nombre')->all();
    expect($filas)->toHaveKeys(['Contratos', 'Certificados'])
        ->and($filas['Contratos'])->toBe('Activa')
        ->and($filas['Certificados'])->toBe('Activa')
        ->and(in_array('Inactiva', $filas, true))->toBeTrue()
        ->and(CategoriaDocumentalModel::query()->where('tenant_id', 1)->count())->toBe(count($filas));
});
