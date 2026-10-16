<?php

namespace Database\Seeders;

use App\Modules\GestionPersonal\Domain\Services\NormalizadorDeTexto;
use App\Modules\GestionPersonal\Domain\ValueObjects\CodigoColaborador;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorCuentaModel;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\TenantModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Datos sintéticos de la feature 005: dos tenants, fichas y asociaciones cuenta-ficha (idempotente).
 */
class PersonalSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) config('hrms.seed_password');

        TenantModel::query()->updateOrCreate(['id' => 1], ['nombre' => 'Altavista S.A.', 'zona_horaria' => 'America/Guayaquil']);
        TenantModel::query()->updateOrCreate(['id' => 2], ['nombre' => 'Cordillera Andina S.A.', 'zona_horaria' => 'America/Guayaquil']);

        $this->cuenta('Administrador de prueba', 'admin@hrms.test', 'admin', 1, $password);
        $usuario = $this->cuenta('Usuario de prueba', 'usuario@hrms.test', 'user', 1, $password);
        $colaborador2 = $this->cuenta('Colaborador dos', 'colaborador2@hrms.test', 'user', 1, $password);
        $this->cuenta('Colaborador sin ficha', 'sinficha@hrms.test', 'user', 1, $password);
        $sinPermiso = $this->cuenta('Colaborador sin permiso', 'sinpermiso@hrms.test', 'user', 1, $password);
        $this->cuenta('Administrador del tenant dos', 'admin2@hrms.test', 'admin', 2, $password);

        $admin = UserModel::query()->where('email', 'admin@hrms.test')->value('id');

        $ana = $this->ficha(1, 'Cedula', '0102030405', 'Ana', 'Núñez Paredes', (int) $admin, [
            'fecha_nacimiento' => '1990-04-12', 'nacionalidad' => 'Ecuatoriana', 'genero' => 'Femenino',
            'estado_civil' => 'Soltera', 'numero_hijos' => 0, 'direccion' => 'Av. Principal 123',
            'telefono' => '+593 2 555 0101', 'correo_contacto' => 'ana.nunez@example.test',
            'emergencia_nombre' => 'Rosa Núñez', 'emergencia_relacion' => 'Madre', 'emergencia_telefono' => '0999999999',
        ]);
        $luis = $this->ficha(1, 'Cedula', '0912345678', 'Luis', 'Núñez Andrade', (int) $admin);
        $maria = $this->ficha(1, 'Pasaporte', 'AB123', 'María', 'Pérez Soto', (int) $admin);
        $this->ficha(1, 'Otro', '0102-03', 'José Ángel', 'Mora', (int) $admin);
        $this->ficha(1, 'Cedula', '0000123456', 'Carla', 'Vera Lema', (int) $admin);
        $this->ficha(2, 'Cedula', '0102030405', 'Rosa', 'Chávez Díaz', (int) $admin);
        $this->ficha(2, 'Pasaporte', 'ZX900', 'Pablo', 'Rivas Ortiz', (int) $admin);

        $this->acceso($usuario, $ana, true);
        $this->acceso($colaborador2, $luis, true);
        $this->acceso($sinPermiso, $maria, false);
    }

    private function cuenta(string $nombre, string $correo, string $rol, int $tenantId, string $password): int
    {
        $usuario = UserModel::query()->updateOrCreate(
            ['email' => $correo],
            ['name' => $nombre, 'role' => $rol, 'password' => $password, 'tenant_id' => $tenantId],
        );

        return (int) $usuario->id;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function ficha(int $tenantId, string $tipo, string $numero, string $nombres, string $apellidos, int $actor, array $extra = []): int
    {
        $normalizador = new NormalizadorDeTexto;
        $clave = $normalizador->claveDeIdentificacion($numero);

        $existente = ColaboradorModel::query()
            ->where('tenant_id', $tenantId)->where('tipo_identificacion', $tipo)->where('identificacion_clave', $clave)
            ->first();
        if ($existente !== null) {
            return (int) $existente->id;
        }

        $modelo = ColaboradorModel::query()->create(array_merge([
            'tenant_id' => $tenantId,
            'codigo' => 'TMP-'.$tenantId.'-'.$clave,
            'tipo_identificacion' => $tipo,
            'numero_identificacion' => $numero,
            'identificacion_clave' => $clave,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'nombres_busqueda' => $normalizador->busquedaDeTexto($nombres),
            'apellidos_busqueda' => $normalizador->busquedaDeTexto($apellidos),
            'version' => 1,
            'operacion_id' => (string) Str::uuid(),
            'operacion_hash' => hash('sha256', $tenantId.$tipo.$clave),
            'created_by' => $actor,
            'updated_by' => $actor,
        ], $extra));

        $modelo->codigo = CodigoColaborador::desdeId((int) $modelo->id);
        $modelo->save();

        return (int) $modelo->id;
    }

    private function acceso(int $userId, int $colaboradorId, bool $puedeConsultar): void
    {
        $tenantId = (int) ColaboradorModel::query()->whereKey($colaboradorId)->value('tenant_id');

        ColaboradorCuentaModel::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'tenant_id' => $tenantId,
                'colaborador_id' => $colaboradorId,
                'puede_consultar_ficha' => $puedeConsultar,
                'puede_consultar_documentos' => false,
                'puede_exportar' => false,
            ],
        );
    }
}
