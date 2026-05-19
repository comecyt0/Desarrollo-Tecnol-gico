<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AsignacionEvaluador extends Model
{
    protected $guarded = [];

    protected $table = 'asignaciones_evaluador';

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class);
    }

    public function evaluador()
    {
        return $this->belongsTo(User::class, 'evaluador_id');
    }

    public function asignadoPor()
    {
        return $this->belongsTo(User::class, 'asignado_por');
    }

    public function dictamen()
    {
        return $this->hasOne(Dictamen::class, 'asignacion_id');
    }
}
