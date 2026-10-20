<?php

use App\Modules\Desarrollo\Application\Ports\GeneradorDeReportePdfInterface;
use App\Modules\Desarrollo\Domain\Exceptions\ExportacionNoDisponible;
use App\Modules\Desarrollo\Infrastructure\Adapters\ReportePdfDompdf;

/** Abre el .xlsx devuelto y entrega `nombre de hoja => XML de la hoja`. */
function hojasDelExcel(string $contenido): array
{
    $ruta = tempnam(sys_get_temp_dir(), 'prueba-xlsx');
    file_put_contents($ruta, $contenido);
    $zip = new ZipArchive;
    expect($zip->open($ruta))->toBeTrue();
    $libro = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
    $hojas = [];
    foreach ($libro->sheets->sheet as $i => $hoja) {
        $n = count($hojas) + 1;
        $hojas[(string) $hoja['name']] = (string) $zip->getFromName("xl/worksheets/sheet$n.xml");
    }
    $zip->close();
    @unlink($ruta);

    return $hojas;
}

/** Cuenta las filas de datos (sin el encabezado) de una hoja. */
function filasDeDatos(string $xmlDeHoja): int
{
    return max(0, substr_count($xmlDeHoja, '<row ') - 1);
}

beforeEach(function () {
    $this->falsos = actividadFalsos(['hoy' => '2026-10-17', 'vigentes' => [10 => 'Ana', 11 => 'Luis', 12 => 'Eva', 13 => 'Pedro']]);
    departamentosActuales([10 => 1, 11 => 1, 12 => 2, 13 => 2]);
    $this->exportar = fn (array $q = []) => $this->get('/api/reportes/exportar?'.http_build_query($q), ['Accept' => '*/*']);
    $this->admin = actuarComoAdminDeTenant();
});

it('exporta un Excel con las cuatro hojas y los datos filtrados', function () {
    $a = actividadConAsistencia([10 => 'Presente', 11 => 'Presente', 12 => 'Presente', 13 => 'Ausente'], ['nombre' => 'Excel avanzado']);
    encuestaDe($a['actividad'], 10, 5, 'Excelente');
    impactoDeAccion($a['accion'], 2400);

    $r = ($this->exportar)(['formato' => 'xlsx'])->assertOk();

    expect($r->headers->get('Content-Type'))->toContain('spreadsheetml.sheet')
        ->and($r->headers->get('Content-Disposition'))->toContain('reportes-evaluacion-2026-01-01_2026-12-31.xlsx');

    $hojas = hojasDelExcel($r->getContent());
    expect(array_keys($hojas))->toBe(['Indicadores', 'Detalle', 'Impacto 30-60-90', 'Evaluación de impacto'])
        ->and($hojas['Indicadores'])->toContain('% Plan cumplido')->toContain('ROI estimado')->toContain('Inversión por colaborador formado')
        ->and($hojas['Detalle'])->toContain('ACT-0001')->toContain('Excel avanzado')
        ->and(filasDeDatos($hojas['Detalle']))->toBe(1)
        ->and($hojas['Evaluación de impacto'])->toContain('Tiempo de cierre')->toContain('200');
});

it('el Excel respeta los filtros aplicados', function () {
    actividadConAsistencia([10 => 'Presente', 11 => 'Presente']);

    $todas = hojasDelExcel(($this->exportar)(['formato' => 'xlsx'])->assertOk()->getContent());
    $dep2 = hojasDelExcel(($this->exportar)(['formato' => 'xlsx', 'departamento_id' => 2])->assertOk()->getContent());
    $otroPeriodo = hojasDelExcel(($this->exportar)(['formato' => 'xlsx', 'fecha_desde' => '2025-01-01', 'fecha_hasta' => '2025-12-31'])->assertOk()->getContent());

    expect(filasDeDatos($todas['Detalle']))->toBe(1)->and(filasDeDatos($dep2['Detalle']))->toBe(0)->and(filasDeDatos($otroPeriodo['Detalle']))->toBe(0);
});

it('el contenido del Excel coincide con la tabla detallada de la pantalla', function () {
    actividadConAsistencia([10 => 'Presente', 11 => 'Presente'], ['nombre' => 'Primera']);
    actividadConAsistencia([10 => 'Presente'], ['nombre' => 'Segunda']);

    $pantalla = $this->getJson('/api/reportes/detalle')->json('data');
    $hoja = hojasDelExcel(($this->exportar)(['formato' => 'xlsx'])->getContent())['Detalle'];

    expect(filasDeDatos($hoja))->toBe(count($pantalla));
    foreach ($pantalla as $fila) {
        expect($hoja)->toContain($fila['codigo'])->toContain($fila['nombre']);
    }
});

it('un período sin datos exporta archivos válidos con tablas vacías', function () {
    $hojas = hojasDelExcel(($this->exportar)(['formato' => 'xlsx', 'fecha_desde' => '2020-01-01', 'fecha_hasta' => '2020-12-31'])->assertOk()->getContent());

    expect(array_keys($hojas))->toHaveCount(4)->and(filasDeDatos($hojas['Detalle']))->toBe(0)->and(filasDeDatos($hojas['Impacto 30-60-90']))->toBe(0)
        ->and(filasDeDatos($hojas['Evaluación de impacto']))->toBe(0)->and($hojas['Indicadores'])->toContain('% Plan cumplido');
});

it('exporta el seguimiento 30-60-90 con la alerta de reingreso', function () {
    $a = actividadConAsistencia([10 => 'Presente', 11 => 'Presente', 12 => 'Presente']);
    foreach ([10 => 5, 11 => 4, 12 => 3] as $c => $n) {
        seguimientoDe($a['actividad'], $c, 30, $n);
    }
    foreach ([10 => 4, 11 => 3, 12 => 2] as $c => $n) {
        seguimientoDe($a['actividad'], $c, 60, $n);
    }

    $hoja = hojasDelExcel(($this->exportar)(['formato' => 'xlsx'])->getContent())['Impacto 30-60-90'];

    expect(filasDeDatos($hoja))->toBe(1)->and($hoja)->toContain('Sí');
});

it('el PDF lleva indicadores y tablas, sin gráficos', function () {
    $capturado = null;
    app()->instance(GeneradorDeReportePdfInterface::class, new class($capturado) implements GeneradorDeReportePdfInterface
    {
        public function __construct(private &$capturado) {}

        public function generar(array $datos): string
        {
            $this->capturado = $datos;

            return '%PDF-1.4 prueba';
        }
    });
    actividadConAsistencia([10 => 'Presente']);

    $r = ($this->exportar)(['formato' => 'pdf'])->assertOk();

    expect($r->headers->get('Content-Type'))->toContain('application/pdf')
        ->and($r->headers->get('Content-Disposition'))->toContain('.pdf')
        ->and(array_column($capturado['tablas'], 'titulo'))->toBe(['Indicadores', 'Detalle por actividad', 'Impacto a 30, 60 y 90 días', 'Evaluación de impacto por acción'])
        ->and(json_encode($capturado))->not->toContain('Gráfico');
});

it('el adaptador de dompdf genera un PDF válido con datos escapados', function () {
    $pdf = (new ReportePdfDompdf)->generar([
        'titulo' => 'Reportes <script>alert(1)</script>', 'subtitulo' => 'Período',
        'tablas' => [['titulo' => 'Indicadores', 'columnas' => ['Indicador', 'Valor'], 'filas' => [['% Plan cumplido', 50.5], ['Vacío', null]]]],
    ]);

    expect($pdf)->toStartWith('%PDF-');
})->skip(! class_exists(Dompdf\Dompdf::class), 'dompdf no está instalado en este entorno (composer install).');

it('rechaza un formato inválido o ausente', function () {
    ($this->exportar)(['formato' => 'csv'])->assertUnprocessable()->assertJsonPath('code', 'formato_invalido');
    ($this->exportar)([])->assertUnprocessable()->assertJsonValidationErrors('formato');
});

it('rechaza un rango de fechas inválido', function () {
    ($this->exportar)(['formato' => 'xlsx', 'fecha_desde' => '2026-05-01', 'fecha_hasta' => '2026-04-01'])->assertUnprocessable()->assertJsonPath('code', 'periodo_invalido');
});

it('informa un error seguro si no se puede generar el archivo', function () {
    app()->instance(GeneradorDeReportePdfInterface::class, new class implements GeneradorDeReportePdfInterface
    {
        public function generar(array $datos): string
        {
            throw new ExportacionNoDisponible;
        }
    });

    ($this->exportar)(['formato' => 'pdf'])->assertStatus(503)->assertJsonPath('code', 'exportacion_no_disponible');
});

it('las exportaciones no se registran en ninguna tabla propia', function () {
    actividadConAsistencia([10 => 'Presente']);

    ($this->exportar)(['formato' => 'xlsx'])->assertOk();

    expect(DB::table('encuestas_satisfaccion')->count() + DB::table('seguimientos_impacto')->count() + DB::table('impactos_accion')->count())->toBe(0);
});

it('el colaborador no puede exportar', function () {
    actuarComoUsuarioDeTenant();

    ($this->exportar)(['formato' => 'xlsx'])->assertForbidden();
});
