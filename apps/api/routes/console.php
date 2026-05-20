<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cierre automático de convocatorias vencidas — se ejecuta cada hora
Schedule::command('convocatorias:close-expired')->hourly();

// Backup de DB diario a las 02:00; conserva 30 días
Schedule::command('comecyt:backup-db', ['--keep=30'])
    ->dailyAt('02:00')
    ->onFailure(function () {
        Log::error('Backup DB falló — revisar pg_dump y permisos de storage/app/backups');
    });
