<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListaNegra extends Model
{
    protected $guarded = [];
    protected $table = 'lista_negra';

    public function institucion() { return $this->belongsTo(Institucion::class); }
    public function solicitud() { return $this->belongsTo(Solicitud::class); }
    public function sancionador() { return $this->belongsTo(User::class, 'sancionado_por'); }
}
