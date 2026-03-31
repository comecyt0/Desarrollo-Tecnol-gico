<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ministracion extends Model
{
    protected $guarded = [];
    protected $table = 'ministraciones';

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class);
    }

    public function banco()
    {
        return $this->belongsTo(Banco::class);
    }
}
