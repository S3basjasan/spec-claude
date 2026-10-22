<?php

beforeEach(function () {
    $this->falsos = rutasFalsos();
    $this->admin = actuarComoAdminDeTenant();
});

it('devuelve los niveles ordenados con competencias, grado y diferencial', function () {
    $ruta = rutaEnBd([1, 2, 3], 'Vigente', ['created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);

    $r = $this->getJson("/api/rutas/{$ruta['id']}")->assertOk()
        ->assertJsonPath('data.codigo', 'CAR-0001')
        ->assertJsonPath('data.fuentes_no_disponibles', [])
        ->assertJsonCount(3, 'data.niveles');
    $niveles = $r->json('data.niveles');

    expect(array_column($niveles, 'orden'))->toBe([1, 2, 3])
        ->and($niveles[0]['puesto'])->toBe(['codigo' => 'PU-001', 'nombre' => 'Asistente de datos', 'activo' => true])
        // Nivel 1: sin diferencial.
        ->and($niveles[0]['competencias'][0]['diferencial'])->toBeNull()
        ->and($niveles[0]['experiencia']['diferencial_anios'])->toBeNull()
        // Nivel 2 frente al 1: Comunicación C→B y Análisis D→C suben; Liderazgo es nuevo.
        ->and($niveles[1]['competencias'][0])->toMatchArray(['competencia_id' => 1, 'grado' => 'B', 'diferencial' => ['tipo' => 'sube', 'antes' => 'C', 'delta' => 1]])
        ->and($niveles[1]['competencias'][1]['diferencial'])->toBe(['tipo' => 'sube', 'antes' => 'D', 'delta' => 1])
        ->and($niveles[1]['competencias'][2]['diferencial'])->toBe(['tipo' => 'nuevo'])
        // Nivel 3 frente al 2: Análisis queda igual.
        ->and($niveles[2]['competencias'][1]['diferencial'])->toBe(['tipo' => 'igual'])
        ->and($niveles[2]['competencias_quitadas'])->toBe([]);
});

it('calcula los años adicionales de experiencia solo si ambos niveles la tienen', function () {
    $ruta = rutaEnBd([1, 2, 3]); // niveles con experiencia: null, 1.0, 2.0

    $niveles = $this->getJson("/api/rutas/{$ruta['id']}")->assertOk()->json('data.niveles');

    expect($niveles[1]['experiencia'])->toMatchArray(['anios_minimos' => 1.0, 'diferencial_anios' => null])
        ->and($niveles[2]['experiencia'])->toMatchArray(['anios_minimos' => 2.0, 'diferencial_anios' => 1.0, 'requisitos' => ['3 años']]);
});

it('los conocimientos son solo informativos: texto de Formación y Capacitación, sin grado ni diferencial', function () {
    $ruta = rutaEnBd([1, 2, 3]);

    $nivel = $this->getJson("/api/rutas/{$ruta['id']}")->assertOk()->json('data.niveles.2');

    expect($nivel['conocimientos'])->toBe(['informativo' => true, 'formacion' => ['Ingeniería'], 'capacitacion' => ['Excel avanzado', 'SQL']]);
});

it('lista las competencias que el nivel anterior pedía y este ya no', function () {
    $ruta = rutaEnBd([3, 4, 5]);

    $niveles = $this->getJson("/api/rutas/{$ruta['id']}")->assertOk()->json('data.niveles');

    expect(array_column($niveles[2]['competencias_quitadas'], 'competencia_id'))->toBe([1])
        ->and($niveles[1]['competencias_quitadas'])->toBe([['competencia_id' => 2, 'codigo' => 'COM-002', 'nombre' => 'Análisis', 'grado_anterior' => 'C'], ['competencia_id' => 3, 'codigo' => 'COM-003', 'nombre' => 'Liderazgo', 'grado_anterior' => 'B']]);
});

it('marca los niveles en uso y cuenta los inscritos activos por nivel', function () {
    $ruta = rutaEnBd([1, 2, 3]);
    inscripcionEnBd($ruta['id'], 10, $ruta['niveles'][0], $ruta['niveles'][2]);
    inscripcionEnBd($ruta['id'], 11, null, $ruta['niveles'][1]);

    $d = $this->getJson("/api/rutas/{$ruta['id']}")->assertOk()->json('data');

    expect(array_column($d['niveles'], 'en_uso'))->toBe([true, true, true])
        ->and(array_column($d['niveles'], 'inscritos_activos'))->toBe([1, 0, 0])
        ->and($d['sin_nivel'])->toBe(1)
        ->and($d['inscritos_count'])->toBe(2);
});

it('muestra el creador y el último modificador con nombre y fecha', function () {
    $ruta = rutaEnBd([1, 2], 'Vigente', ['created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);

    $d = $this->getJson("/api/rutas/{$ruta['id']}")->assertOk()->json('data');

    expect($d['creada_por'])->toBe($this->admin->name)->and($d['modificada_por'])->toBe($this->admin->name)
        ->and($d['creada_at'])->not->toBeNull()->and($d['modificada_at'])->not->toBeNull();
});

it('marca el puesto desactivado de un nivel', function () {
    $ruta = rutaEnBd([1, 6]);

    $this->getJson("/api/rutas/{$ruta['id']}")->assertOk()->assertJsonPath('data.niveles.1.puesto.activo', false);
});

it('si Estructura no responde, devuelve los niveles propios, fuentes_no_disponibles y ningún requisito inventado', function () {
    $ruta = rutaEnBd([1, 2, 3]);
    rutasFalsos(['falla' => true]);

    $d = $this->getJson("/api/rutas/{$ruta['id']}")->assertOk()->assertJsonPath('data.fuentes_no_disponibles', ['estructura'])->json('data');

    expect($d['niveles'])->toHaveCount(3)
        ->and($d['niveles'][1]['nombre'])->toBe('Nivel 2')
        ->and($d['niveles'][1]['competencias'])->toBe([])
        ->and($d['niveles'][1]['conocimientos'])->toBe(['informativo' => true, 'formacion' => [], 'capacitacion' => []])
        ->and($d['niveles'][1]['puesto'])->toBe(['codigo' => null, 'nombre' => null, 'activo' => null]);
});

it('responde 404 si la ruta no existe o es de otro tenant', function () {
    $this->getJson('/api/rutas/9999')->assertNotFound()->assertJsonPath('code', 'ruta_no_encontrada');

    $ajena = rutaEnBd([1, 2], 'Vigente', [], tenantId: 2);
    $this->getJson("/api/rutas/{$ajena['id']}")->assertNotFound();
});

it('el colaborador no consulta el detalle de gestión', function () {
    $ruta = rutaEnBd([1, 2]);
    actuarComoUsuarioDeTenant();

    $this->getJson("/api/rutas/{$ruta['id']}")->assertForbidden();
});

it('las opciones traen tipos, departamentos y puestos activos', function () {
    $d = $this->getJson('/api/rutas/opciones')->assertOk()->json('data');

    expect(array_column($d['tipos'], 'valor'))->toBe(['Especialista', 'Gerencial', 'Interareas', 'JovenesProfesionales'])
        ->and($d['tipos'][2])->toBe(['valor' => 'Interareas', 'nombre' => 'Interáreas', 'admite_departamento' => false])
        ->and(array_column($d['departamentos'], 'nombre'))->toBe(['Tecnología', 'Talento Humano'])
        ->and(array_column($d['puestos'], 'id'))->toBe([1, 2, 3, 4, 5])
        ->and($d['fuentes_no_disponibles'])->toBe([]);
});

it('las opciones avisan si Estructura no responde', function () {
    rutasFalsos(['falla' => true]);

    $this->getJson('/api/rutas/opciones')->assertOk()->assertJsonPath('data.fuentes_no_disponibles', ['estructura'])->assertJsonPath('data.puestos', []);
});

it('solo el administrador accede a las opciones', function () {
    actuarComoUsuarioDeTenant();

    $this->getJson('/api/rutas/opciones')->assertForbidden();
});
