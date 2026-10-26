<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Compensación de documentos (feature 014): objetos sin referencia de más de 24 horas. Requiere el planificador activo.
Schedule::command('documentos:limpiar-huerfanos')->daily()->withoutOverlapping();

// Aplicación automática de movimientos aprobados (feature 020): ascensos y traslados cuya fecha efectiva ya llegó. Requiere el planificador activo.
Schedule::command('movimientos:aplicar-programados')->everyFiveMinutes()->withoutOverlapping();

// Rutas de carrera (feature 019): retira las inscripciones activas de quien ya no tiene vínculo laboral vigente. Requiere el planificador activo.
Schedule::command('rutas:conciliar-bajas')->hourly()->withoutOverlapping();
