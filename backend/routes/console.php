<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Compensación de documentos (feature 014): objetos sin referencia de más de 24 horas. Requiere el planificador activo.
Schedule::command('documentos:limpiar-huerfanos')->daily()->withoutOverlapping();
