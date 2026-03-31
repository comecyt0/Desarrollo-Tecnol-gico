<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Solicitud extends Model
{
    use SoftDeletes;

    protected $guarded = [];
    protected $table = 'solicitudes';

    protected $casts = [
        'fecha_inicio_evento' => 'date',
        'fecha_fin_evento' => 'date',
        'monto_solicitado' => 'decimal:2',
        'aportacion_concurrente' => 'decimal:2',
        'fecha_entrega_informe' => 'datetime',
        'fecha_limite_informe' => 'datetime',
    ];

    protected $appends = ['resumen'];

    /**
     * Virtual attribute alias for descripcion_proyecto for frontend compatibility.
     */
    public function getResumenAttribute()
    {
        return $this->descripcion_proyecto;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function institucion()
    {
        return $this->belongsTo(Institucion::class);
    }

    public function areaConocimiento()
    {
        return $this->belongsTo(AreaConocimiento::class, 'area_conocimiento_id');
    }

    public function convocatoria()
    {
        return $this->belongsTo(Convocatoria::class);
    }

    public function observaciones()
    {
        return $this->hasMany(Observacion::class);
    }

    public function asignaciones()
    {
        return $this->hasMany(AsignacionEvaluador::class);
    }

    public function ministracion()
    {
        return $this->hasOne(Ministracion::class);
    }

    public function modalidad()
    {
        return $this->belongsTo(ProgramaModalidad::class);
    }

    public function etapaActual()
    {
        return $this->belongsTo(ProgramaEtapa::class, 'etapa_actual_id');
    }

    public function camposDinamicos()
    {
        return $this->hasMany(SolicitudCampoDinamico::class);
    }

    public function rubrosPrepuesto()
    {
        return $this->hasMany(SolicitudRubroPresupuesto::class);
    }

    public function miembrosEquipo()
    {
        return $this->hasMany(SolicitudMiembroEquipo::class);
    }
}
