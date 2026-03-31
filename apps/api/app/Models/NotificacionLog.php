<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificacionLog extends Model
{
    protected $guarded = [];
    protected $table = 'notificaciones_log';

    public function user() { return $this->belongsTo(User::class); }
    public function solicitud() { return $this->belongsTo(Solicitud::class); }
}
