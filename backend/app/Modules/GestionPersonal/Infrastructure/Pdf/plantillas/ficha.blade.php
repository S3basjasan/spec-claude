<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Ficha {{ $s->codigo }}</title>
<style>
    @page { margin: 70px 50px 60px 50px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1f2933; line-height: 1.4; }
    h1 { font-size: 17px; margin: 0 0 2px 0; }
    h2 { font-size: 12px; margin: 18px 0 6px 0; padding-bottom: 3px; border-bottom: 1px solid #9aa5b1; page-break-after: avoid; }
    .sub { color: #52606d; margin: 0 0 6px 0; }
    .campo { margin: 0 0 3px 0; }
    .etiqueta { font-weight: bold; }
    .bloque { margin: 0 0 8px 0; padding: 5px 7px; border: 1px solid #cbd2d9; page-break-inside: avoid; word-wrap: break-word; }
    .bloque-largo { margin: 0 0 8px 0; padding: 5px 7px; border: 1px solid #cbd2d9; word-wrap: break-word; }
    .vacio { color: #52606d; font-style: italic; }
    p { margin: 0 0 3px 0; word-wrap: break-word; }
</style>
</head>
<body>
<h1>Ficha integral del colaborador</h1>
<p class="sub">Código {{ $s->codigo }} · {{ $s->identificacion['nombres'] }} {{ $s->identificacion['apellidos'] }}</p>
<p class="sub">Información guardada al {{ $s->leidoEn }} ({{ $s->zonaHoraria }}). Generado el {{ $s->generadoEn }}.</p>

<h2>1. Identificación</h2>
<p class="campo"><span class="etiqueta">Código:</span> {{ $s->codigo }}</p>
<p class="campo"><span class="etiqueta">Tipo de identificación:</span> {{ $s->identificacion['tipo_identificacion'] }}</p>
<p class="campo"><span class="etiqueta">Número de identificación:</span> {{ $s->identificacion['numero_identificacion'] }}</p>
<p class="campo"><span class="etiqueta">Nombres:</span> {{ $s->identificacion['nombres'] }}</p>
<p class="campo"><span class="etiqueta">Apellidos:</span> {{ $s->identificacion['apellidos'] }}</p>

<h2>2. Datos personales</h2>
@foreach ($s->personales as $etiqueta => $valor)
    <p class="campo"><span class="etiqueta">{{ $etiqueta }}:</span> {{ $valor }}</p>
@endforeach

<h2>3. Situación laboral</h2>
<p class="campo"><span class="etiqueta">Fecha de referencia:</span> {{ $s->laboral['fecha_de_referencia'] }}</p>
<p class="campo"><span class="etiqueta">Estado:</span> {{ $s->laboral['estado'] }}</p>
@if ($s->laboral['vinculo_vigente'] !== null)
    <div class="bloque">
        <p><span class="etiqueta">Vínculo vigente</span></p>
        @foreach ($s->laboral['vinculo_vigente'] as $etiqueta => $valor)
            <p class="campo"><span class="etiqueta">{{ $etiqueta }}:</span> {{ $valor }}</p>
        @endforeach
    </div>
@endif
@if ($s->laboral['proximo_ingreso'] !== null)
    <div class="bloque">
        <p><span class="etiqueta">Ingreso programado (aún no vigente)</span></p>
        @foreach ($s->laboral['proximo_ingreso'] as $etiqueta => $valor)
            <p class="campo"><span class="etiqueta">{{ $etiqueta }}:</span> {{ $valor }}</p>
        @endforeach
    </div>
@endif
@if ($s->laboral['vinculo_vigente'] === null && $s->laboral['proximo_ingreso'] === null)
    <p class="vacio">Sin vínculo vigente ni ingreso programado.</p>
@endif

<h2>4. Formación académica</h2>
@forelse ($s->formaciones as $registro)
    <div class="bloque-largo">
        @foreach ($registro as $etiqueta => $valor)
            <p class="campo"><span class="etiqueta">{{ $etiqueta }}:</span> {{ $valor }}</p>
        @endforeach
    </div>
@empty
    <p class="vacio">Sin registros.</p>
@endforelse

<h2>5. Experiencia anterior</h2>
@forelse ($s->experiencias as $registro)
    <div class="bloque-largo">
        @foreach ($registro as $etiqueta => $valor)
            <p class="campo"><span class="etiqueta">{{ $etiqueta }}:</span> {{ $valor }}</p>
        @endforeach
    </div>
@empty
    <p class="vacio">Sin registros.</p>
@endforelse

<h2>6. Movilidad</h2>
<p class="campo"><span class="etiqueta">Estado:</span> {{ $s->movilidad['estado'] }}</p>
<p class="campo"><span class="etiqueta">Observaciones:</span> {{ $s->movilidad['observaciones'] }}</p>
<p class="campo"><span class="etiqueta">Última actualización:</span> {{ $s->movilidad['actualizada'] }}</p>

<h2>7. Trayectoria interna</h2>
@forelse ($s->trayectoria as $registro)
    <div class="bloque">
        @foreach ($registro as $etiqueta => $valor)
            <p class="campo"><span class="etiqueta">{{ $etiqueta }}:</span> {{ $valor }}</p>
        @endforeach
    </div>
@empty
    <p class="vacio">Sin registros.</p>
@endforelse
</body>
</html>
