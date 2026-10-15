<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\UnidadModel;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

it('el código es único', function () {
    $unidad = unidadDe();
    $otra = UnidadModel::factory()->conPadre($unidad->unidad_padre_id)->create();

    expect(fn () => DB::table('unidades_organizacionales')->where('id', $otra->id)->update(['codigo' => $unidad->codigo]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('el nombre no es único: dos unidades con el mismo nombre se guardan', function () {
    $raiz = UnidadModel::factory()->raiz()->create(['nombre' => 'Operaciones']);
    UnidadModel::factory()->conPadre($raiz)->create(['nombre' => 'Operaciones']);

    expect(UnidadModel::query()->where('nombre', 'Operaciones')->count())->toBe(2);
});

it('el índice parcial impide dos raíces activas, pero permite una raíz inactiva junto a una activa', function () {
    UnidadModel::factory()->raiz()->create();

    expect(fn () => UnidadModel::factory()->raiz()->create())->toThrow(UniqueConstraintViolationException::class);

    UnidadModel::factory()->raiz()->inactiva()->create();
    UnidadModel::factory()->raiz()->inactiva()->create();
    expect(UnidadModel::query()->whereNull('unidad_padre_id')->count())->toBe(3);
});

it('las claves foráneas no tienen cascada: no se borra un tipo en uso, una unidad con hijas ni una unidad con posiciones', function () {
    $a = arbolDeEjemplo();
    posicionesDeUnidad($a['reclutamiento'], 'Ocupada');

    expect(fn () => DB::table('tipos_unidad')->where('id', $a['raiz']->tipo_unidad_id)->delete())->toThrow(QueryException::class)
        ->and(fn () => DB::table('unidades_organizacionales')->where('id', $a['raiz']->id)->delete())->toThrow(QueryException::class)
        ->and(fn () => DB::table('unidades_organizacionales')->where('id', $a['reclutamiento']->id)->delete())->toThrow(QueryException::class);
});

it('todas() devuelve el árbol en orden jerárquico con sus niveles', function () {
    $a = arbolDeEjemplo();

    $todas = repositorioDeUnidades()->todas();

    expect(array_map(fn ($u) => $u->nombre, $todas))->toBe(['Gerencia general', 'Talento humano', 'Reclutamiento', 'Tecnología', 'Desarrollo'])
        ->and(array_map(fn ($u) => $u->nivel, $todas))->toBe([0, 1, 2, 1, 2])
        ->and($todas[0]->padre)->toBeNull()
        ->and($todas[2]->padre->codigo)->toBe($a['talento']->codigo)
        ->and($todas[1]->tipo->nombre)->toBe('Departamento');
});

it('ordena los hermanos por el número del código, también pasando del 999', function () {
    $raiz = UnidadModel::factory()->raiz()->create();
    $diez = UnidadModel::factory()->conPadre($raiz)->create(['nombre' => 'Diez']);
    $nueve = UnidadModel::factory()->conPadre($raiz)->create(['nombre' => 'Nueve']);
    DB::table('unidades_organizacionales')->where('id', $diez->id)->update(['codigo' => 'UO-10']);
    DB::table('unidades_organizacionales')->where('id', $nueve->id)->update(['codigo' => 'UO-9']);
    $mil = UnidadModel::factory()->conPadre($raiz)->create(['nombre' => 'Mil']);
    DB::table('unidades_organizacionales')->where('id', $mil->id)->update(['codigo' => 'UO-1000']);

    $hijas = array_slice(array_map(fn ($u) => $u->nombre, repositorioDeUnidades()->todas()), 1);

    expect($hijas)->toBe(['Nueve', 'Diez', 'Mil']);
});

it('cuenta solo las dependientes activas y las posiciones Ocupadas o Vacantes propias', function () {
    $a = arbolDeEjemplo();
    UnidadModel::factory()->conPadre($a['talento'])->inactiva()->create();
    posicionesDeUnidad($a['talento'], 'Ocupada', 2);
    posicionesDeUnidad($a['talento'], 'Vacante');
    posicionesDeUnidad($a['talento'], 'Suprimida', 4);
    posicionesDeUnidad($a['reclutamiento'], 'Ocupada', 5);

    $talento = repositorioDeUnidades()->obtener($a['talento']->id);
    $raiz = repositorioDeUnidades()->obtener($a['raiz']->id);

    expect($talento->dependientesActivas)->toBe(1)
        ->and($talento->posicionesVigentes)->toBe(3)
        ->and($raiz->dependientesActivas)->toBe(2)
        ->and($raiz->posicionesVigentes)->toBe(0);
});

it('obtener() incluye las dependientes de todo estado, las fechas y la versión', function () {
    $a = arbolDeEjemplo();
    $inactiva = UnidadModel::factory()->conPadre($a['tecnologia'])->inactiva()->create(['nombre' => 'Operaciones']);

    $tecnologia = repositorioDeUnidades()->obtener($a['tecnologia']->id);
    $suprimida = repositorioDeUnidades()->obtener($inactiva->id);

    expect(array_map(fn ($d) => [$d->nombre, $d->estado->value], $tecnologia->dependientes))
        ->toBe([['Desarrollo', 'Activa'], ['Operaciones', 'Inactiva']])
        ->and($tecnologia->version)->toBe(1)
        ->and($tecnologia->fechaCreacion)->toBe(now()->toDateString())
        ->and($tecnologia->fechaSupresion)->toBeNull()
        ->and($suprimida->fechaSupresion)->toBe(now()->toDateString())
        ->and(repositorioDeUnidades()->obtener(9999))->toBeNull();
});

it('opciones() deja fuera la unidad y sus dependientes y solo ofrece unidades activas', function () {
    $a = arbolDeEjemplo();
    UnidadModel::factory()->conPadre($a['raiz'])->inactiva()->create(['nombre' => 'Retirada']);

    $sin = repositorioDeUnidades()->opciones(null);
    $conExcluir = repositorioDeUnidades()->opciones($a['tecnologia']->id);

    expect(array_map(fn ($u) => $u->nombre, $sin->unidades))->toBe(['Gerencia general', 'Talento humano', 'Reclutamiento', 'Tecnología', 'Desarrollo'])
        ->and(array_map(fn ($u) => $u->nombre, $conExcluir->unidades))->toBe(['Gerencia general', 'Talento humano', 'Reclutamiento'])
        ->and(array_map(fn ($t) => $t->nombre, $sin->tipos))->toContain('Departamento')
        ->and($sin->hayOtraRaizActiva)->toBeTrue();
});

it('opciones() informa de la raíz activa distinta de la excluida', function () {
    $a = arbolDeEjemplo();

    expect(repositorioDeUnidades()->opciones($a['raiz']->id)->hayOtraRaizActiva)->toBeFalse()
        ->and(repositorioDeUnidades()->opciones($a['talento']->id)->hayOtraRaizActiva)->toBeTrue();

    DB::table('unidades_organizacionales')->where('id', $a['raiz']->id)->update(['estado' => 'Inactiva']);
    expect(repositorioDeUnidades()->opciones(null)->hayOtraRaizActiva)->toBeFalse();
});

it('con unas 500 unidades devuelve el árbol completo con un número de consultas acotado', function () {
    $raiz = UnidadModel::factory()->raiz()->create();
    $padres = [$raiz->id];
    $total = 1;

    foreach ([1, 2, 3] as $profundidad) {
        $filas = [];
        foreach ($padres as $padre) {
            foreach (range(1, 8) as $i) {
                if ($total >= 500) {
                    break 2;
                }
                $filas[] = ['nombre' => "D$profundidad-".$total, 'tipo_unidad_id' => $raiz->tipo_unidad_id, 'unidad_padre_id' => $padre,
                    'estado' => 'Activa', 'fecha_creacion' => now()->toDateString(), 'version' => 1, 'created_at' => now(), 'updated_at' => now()];
                $total++;
            }
        }
        foreach (array_chunk($filas, 100) as $lote) {
            DB::table('unidades_organizacionales')->insert($lote);
        }
        $padres = DB::table('unidades_organizacionales')->where('nombre', 'like', "D$profundidad-%")->pluck('id')->all();
    }
    DB::table('unidades_organizacionales')->whereNull('codigo')->update(['codigo' => DB::raw("'UO-' || id")]);

    DB::enableQueryLog();
    $todas = repositorioDeUnidades()->todas();
    $consultas = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect(count($todas))->toBe(500)
        ->and($todas[0]->nivel)->toBe(0)
        ->and(max(array_map(fn ($u) => $u->nivel, $todas)))->toBe(3)
        ->and($consultas)->toBeLessThanOrEqual(5);
});
