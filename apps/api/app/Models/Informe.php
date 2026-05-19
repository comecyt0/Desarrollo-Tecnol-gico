<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Informe extends Model
{
    protected $guarded = [];

    protected $table = 'informes';

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class);
    }
}
