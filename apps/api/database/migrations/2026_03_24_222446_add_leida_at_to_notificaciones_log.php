<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notificaciones_log', function (Blueprint $table) {
            if (!Schema::hasColumn('notificaciones_log', 'leida_at')) {
                $table->timestamp('leida_at')->nullable()->after('tipo');
            }
            if (!Schema::hasColumn('notificaciones_log', 'descripcion')) {
                $table->text('descripcion')->nullable()->after('leida_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notificaciones_log', function (Blueprint $table) {
            $table->dropColumnIfExists('leida_at');
            $table->dropColumnIfExists('descripcion');
        });
    }
};
