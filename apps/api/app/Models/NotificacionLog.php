<?php

namespace App\Models;

use App\Events\NotificacionCreada;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class NotificacionLog extends Model
{
    protected $guarded = [];

    protected $table = 'notificaciones_log';

    protected static function booted(): void
    {
        // Broadcast en tiempo real al crear: la campana del frontend se actualiza al instante
        // (cae a polling si Reverb no está disponible — el broadcast es idempotente al log de DB)
        static::created(function (NotificacionLog $n) {
            if ($n->user_id) {
                try {
                    broadcast(new NotificacionCreada($n))->toOthers();
                } catch (\Throwable $e) {
                    // No interrumpir el flujo si el broadcaster está caído
                    Log::warning('NotificacionCreada broadcast failed', [
                        'error' => $e->getMessage(),
                        'notificacion_id' => $n->id,
                    ]);
                }
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class);
    }
}
