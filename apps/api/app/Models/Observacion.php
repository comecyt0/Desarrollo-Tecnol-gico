<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Observacion extends Model
{
    protected $fillable = [
        'solicitud_id',
        'user_id',
        'campo',
        'tipo',
        'comentario',
        'resuelta',
        'respuesta_solicitante'
    ];
    protected $table = 'observaciones';

    //
}
