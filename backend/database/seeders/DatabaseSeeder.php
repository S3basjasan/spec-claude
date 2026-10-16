<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(UsuariosInicialesSeeder::class);
        $this->call(CompetenciasSeeder::class);
        $this->call(ComportamientosSeeder::class);
        $this->call(PreguntasSeeder::class);
        $this->call(PuestosSeeder::class);
        $this->call(CatalogoFormativoSeeder::class);
        $this->call(TiposUnidadSeeder::class);
        $this->call(UnidadesSeeder::class);
        $this->call(PersonalSeeder::class);
        $this->call(IngresosSeeder::class);
        $this->call(DnfSeeder::class);
        $this->call(PlanFormacionSeeder::class);
    }
}
