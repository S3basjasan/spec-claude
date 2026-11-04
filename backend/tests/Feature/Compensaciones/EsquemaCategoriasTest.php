<?php

use Illuminate\Support\Facades\Schema;

it('no tiene llave foránea hacia competencias y guarda los indicadores dentro de la categoría', function () {
    $destinos = collect(Schema::getForeignKeys('categorias_reconocimiento'))->pluck('foreign_table')->all();

    expect($destinos)->not->toContain('competencias')
        ->and(Schema::hasColumn('categorias_reconocimiento', 'competencia_id'))->toBeTrue()
        ->and(Schema::hasColumn('categorias_reconocimiento', 'indicadores'))->toBeTrue()
        ->and(Schema::hasTable('indicadores_reconocimiento'))->toBeFalse();
});
