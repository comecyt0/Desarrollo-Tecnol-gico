<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Institucion extends Model
{
    protected $guarded = [];
    protected $table = 'instituciones';

    public function municipio()
    {
        return $this->belongsTo(Municipio::class);
    }

    public function solicitantes()
    {
        return $this->hasMany(User::class, 'institucion_id');
    }
}
