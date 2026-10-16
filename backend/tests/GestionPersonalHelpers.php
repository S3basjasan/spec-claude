<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PosicionModel;
use App\Modules\GestionPersonal\Application\Ports\LectorEstructuraPort;
use App\Modules\GestionPersonal\Application\Ports\LectorLaboralPort;
use App\Modules\GestionPersonal\Domain\ValueObjects\Lectura;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\CategoriaDocumentalModel;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorModel;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\DocumentoColaboradorModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\TenantModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

const URL_CATEGORIAS = '/api/categorias-documentales';

/**
 * Helpers de la feature 005 (fichas de colaboradores). Datos sintéticos.
 */
function tenantDos(): TenantModel
{
    return TenantModel::query()->firstOrCreate(
        ['id' => 2],
        ['nombre' => 'Cordillera Andina S.A.', 'zona_horaria' => 'America/Guayaquil'],
    );
}

function actuarComoAdminDeTenant(int $tenantId = 1): UserModel
{
    if ($tenantId === 2) {
        tenantDos();
    }

    $admin = UserModel::factory()->admin()->create(['tenant_id' => $tenantId]);
    Sanctum::actingAs($admin, ['admin']);

    return $admin;
}

function actuarComoUsuarioDeTenant(int $tenantId = 1): UserModel
{
    if ($tenantId === 2) {
        tenantDos();
    }

    $usuario = UserModel::factory()->create(['tenant_id' => $tenantId]);
    Sanctum::actingAs($usuario, ['user']);

    return $usuario;
}

/**
 * Cuerpo válido mínimo (o con cambios) para crear una ficha.
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function datosFicha(array $cambios = []): array
{
    return array_replace([
        'tipo_identificacion' => 'Cedula',
        'numero_identificacion' => '0102030405',
        'nombres' => 'Ana',
        'apellidos' => 'Núñez Paredes',
    ], $cambios);
}

/**
 * @param  array<string, mixed>  $cuerpo
 */
function crearFichaApi(mixed $prueba, array $cuerpo, ?string $clave = null): TestResponse
{
    return $prueba->postJson('/api/colaboradores', $cuerpo, ['Idempotency-Key' => $clave ?? (string) Str::uuid()]);
}

/**
 * @param  array<string, mixed>  $atributos
 */
function fichaDe(array $atributos = [], int $tenantId = 1): ColaboradorModel
{
    return ColaboradorModel::factory()->create(array_merge(['tenant_id' => $tenantId], $atributos));
}

/**
 * Sustituye el lector laboral por uno configurable.
 *
 * @param  array<string, Lectura>  $respuestas  Por nombre de método.
 */
function lectorLaboralFalso(mixed $prueba, array $respuestas): void
{
    app()->bind(LectorLaboralPort::class, fn () => new class($respuestas) implements LectorLaboralPort
    {
        /** @param array<string, Lectura> $r */
        public function __construct(private array $r) {}

        private function dar(string $metodo): Lectura
        {
            return $this->r[$metodo] ?? Lectura::noInstalado();
        }

        public function situacionVigente(int $tenantId, array $colaboradorIds, string $fecha): Lectura
        {
            return $this->dar('situacionVigente');
        }

        public function idsConVinculoVigente(int $tenantId, string $fecha): Lectura
        {
            return $this->dar('idsConVinculoVigente');
        }

        public function idsPorEstado(int $tenantId, string $estado, string $fecha): Lectura
        {
            return $this->dar('idsPorEstado');
        }

        public function colaboradoresEnPosiciones(int $tenantId, array $posicionIds, string $fecha): Lectura
        {
            return $this->dar('colaboradoresEnPosiciones');
        }

        public function trayectoria(int $tenantId, int $colaboradorId): Lectura
        {
            return $this->dar('trayectoria');
        }

        public function estadosPresentes(int $tenantId, string $fecha): Lectura
        {
            return $this->dar('estadosPresentes');
        }

        public function proximoIngreso(int $tenantId, int $colaboradorId, string $fecha): Lectura
        {
            return $this->dar('proximoIngreso');
        }
    });
}

/**
 * @param  array<string, Lectura>  $respuestas  Por nombre de método.
 */
function lectorEstructuraFalso(mixed $prueba, array $respuestas): void
{
    app()->bind(LectorEstructuraPort::class, fn () => new class($respuestas) implements LectorEstructuraPort
    {
        /** @param array<string, Lectura> $r */
        public function __construct(private array $r) {}

        private function dar(string $metodo): Lectura
        {
            return $this->r[$metodo] ?? Lectura::noInstalado();
        }

        public function posiciones(array $posicionIds): Lectura
        {
            return $this->dar('posiciones');
        }

        public function posicionesPorPuesto(string $texto): Lectura
        {
            return $this->dar('posicionesPorPuesto');
        }

        public function posicionesPorUnidad(int $unidadId): Lectura
        {
            return $this->dar('posicionesPorUnidad');
        }

        public function unidades(): Lectura
        {
            return $this->dar('unidades');
        }
    });
}

/**
 * Posición asignable del tenant: puesto Activo, unidad Activa y estado Vacante.
 *
 * @param  array<string, mixed>  $atributos  Atributos de la posición (estado, tenant_id, puesto_id, unidad_organizacional_id).
 */
function posicionAsignable(array $atributos = [], int $tenantId = 1): PosicionModel
{
    $puestoId = $atributos['puesto_id'] ?? puestoDe()->id;
    $unidadId = $atributos['unidad_organizacional_id'] ?? unidadDe()->id;

    return PosicionModel::query()->create(array_merge([
        'puesto_id' => $puestoId,
        'unidad_organizacional_id' => $unidadId,
        'estado' => 'Vacante',
        'tenant_id' => $tenantId,
    ], $atributos));
}

/**
 * Cuerpo válido del alta de un ingreso.
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function datosIngreso(int $posicionId, array $cambios = []): array
{
    return array_replace([
        'fecha_ingreso' => hoyDelTenant(),
        'tipo_contrato' => 'indefinido',
        'posicion_id' => $posicionId,
    ], $cambios);
}

function registrarIngresoApi(mixed $prueba, int $fichaId, array $cuerpo, ?string $clave = null): TestResponse
{
    return $prueba->postJson("/api/colaboradores/$fichaId/ingresos", $cuerpo, [
        'Idempotency-Key' => $clave ?? (string) Str::uuid(),
    ]);
}

/**
 * Inserta un vínculo (y su asignación) directamente, como lo haría una salida o un dato inicial.
 */
function vinculoDe(int $fichaId, string $ingreso, ?string $salida, int $posicionId, ?string $fin = null, int $tenantId = 1, string $contrato = 'indefinido'): int
{
    $vinculoId = DB::table('vinculos_laborales')->insertGetId([
        'tenant_id' => $tenantId,
        'colaborador_id' => $fichaId,
        'fecha_ingreso' => $ingreso,
        'fecha_salida' => $salida,
        'tipo_contrato_clave' => $contrato,
        'version' => 1,
        'operacion_id' => (string) Str::uuid(),
        'operacion_hash' => hash('sha256', (string) Str::uuid()),
        'created_by' => 1,
        'updated_by' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('asignaciones_laborales')->insert([
        'tenant_id' => $tenantId,
        'vinculo_id' => $vinculoId,
        'posicion_id' => $posicionId,
        'fecha_inicio' => $ingreso,
        'fecha_fin' => $fin ?? $salida,
        'version' => 1,
        'created_by' => 1,
        'updated_by' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $vinculoId;
}

/** Fecha de hoy en la zona horaria del tenant de prueba (la misma que usa el servidor). */
function hoyDelTenant(int $dias = 0): string
{
    return now('America/Guayaquil')->addDays($dias)->toDateString();
}

/**
 * PDF mínimo y estructuralmente válido (xref real), generado en memoria y sin datos personales.
 * Con `$bytesObjetivo` el archivo mide exactamente esa cantidad de bytes (relleno dentro de un flujo).
 */
function pdfSintetico(int $paginas = 1, string $texto = 'Documento de prueba', ?int $bytesObjetivo = null): string
{
    $construir = function (int $relleno) use ($paginas, $texto): string {
        $objetos = [1 => '<< /Type /Catalog /Pages 2 0 R >>'];
        $hijos = [];
        for ($i = 0; $i < $paginas; $i++) {
            $hijos[] = (3 + $i * 2).' 0 R';
        }
        $objetos[2] = '<< /Type /Pages /Count '.$paginas.' /Kids ['.implode(' ', $hijos).'] >>';
        $fuente = 3 + $paginas * 2;
        for ($i = 0; $i < $paginas; $i++) {
            $pagina = 3 + $i * 2;
            $flujo = 'BT /F1 12 Tf 72 720 Td ('.$texto.' '.($i + 1).') Tj ET'.str_repeat(' ', $relleno);
            $objetos[$pagina] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents '.($pagina + 1)." 0 R /Resources << /Font << /F1 {$fuente} 0 R >> >> >>";
            $objetos[$pagina + 1] = '<< /Length '.strlen($flujo)." >>\nstream\n{$flujo}\nendstream";
        }
        $objetos[$fuente] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        $pdf = "%PDF-1.4\n";
        $desplazamientos = [];
        foreach ($objetos as $numero => $cuerpo) {
            $desplazamientos[$numero] = strlen($pdf);
            $pdf .= "{$numero} 0 obj\n{$cuerpo}\nendobj\n";
        }
        $xref = strlen($pdf);
        $total = count($objetos) + 1;
        $pdf .= "xref\n0 {$total}\n0000000000 65535 f \n";
        foreach ($desplazamientos as $desplazamiento) {
            $pdf .= sprintf("%010d 00000 n \n", $desplazamiento);
        }

        return $pdf."trailer\n<< /Size {$total} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    };

    $pdf = $construir(0);
    if ($bytesObjetivo === null) {
        return $pdf;
    }

    $relleno = $bytesObjetivo - strlen($pdf);
    for ($intento = 0; $intento < 4; $intento++) {
        $pdf = $construir(max(0, $relleno));
        $relleno += $bytesObjetivo - strlen($pdf);
    }

    return $pdf;
}

/** Archivo que no es un PDF aunque se suba con extensión `.pdf` (imagen PNG mínima). */
function archivoNoPdf(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
}

/** Disco privado de documentos falso y vacío, con un nombre único por prueba para no heredar restos de otras ejecuciones. */
function almacenDeDocumentos(): Filesystem
{
    $disco = 'documentos_'.Str::lower(Str::random(10));
    config(['hrms.gestion_personal.documentos.disco' => $disco]);

    return Storage::fake($disco);
}

/** Nombre del disco de documentos vigente en la prueba. */
function discoDeDocumentos(): string
{
    return (string) config('hrms.gestion_personal.documentos.disco', 'local');
}

function categoriaActiva(array $atributos = [], int $tenantId = 1): CategoriaDocumentalModel
{
    return CategoriaDocumentalModel::factory()->create(array_merge(['tenant_id' => $tenantId], $atributos));
}

/**
 * Sube un documento por la API como Administrador autenticado (multipart).
 *
 * @param  array<string, mixed>  $campos
 */
function subirDocumento(mixed $prueba, int $fichaId, array $campos = [], ?string $contenido = null, ?string $clave = null, ?int $categoriaId = null): TestResponse
{
    $archivo = UploadedFile::fake()->createWithContent('contrato.pdf', $contenido ?? pdfSintetico());

    return $prueba->post("/api/colaboradores/{$fichaId}/documentos", array_merge([
        'titulo' => 'Contrato',
        'categoria_id' => $categoriaId ?? categoriaActiva()->id,
        'archivo' => $archivo,
    ], $campos), ['Accept' => 'application/json', 'Idempotency-Key' => $clave ?? (string) Str::uuid()]);
}

/**
 * Documento ya publicado con su objeto escrito en el disco falso.
 *
 * @param  array<string, mixed>  $atributos
 */
function documentoConArchivo(array $atributos = [], ?string $contenido = null): DocumentoColaboradorModel
{
    $documento = DocumentoColaboradorModel::factory()->create($atributos);
    $contenido ??= pdfSintetico();
    Storage::disk((string) config('hrms.gestion_personal.documentos.disco', 'local'))->put($documento->object_key, $contenido);
    $documento->forceFill(['size_bytes' => strlen($contenido), 'sha256' => hash('sha256', $contenido)])->save();

    return $documento->fresh();
}

function urlDocumentos(int $ficha, string $sufijo = ''): string
{
    return "/api/colaboradores/{$ficha}/documentos{$sufijo}";
}

function urlCategoria(int $id, string $sufijo = ''): string
{
    return "/api/categorias-documentales/{$id}{$sufijo}";
}
