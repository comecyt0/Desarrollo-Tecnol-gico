<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarouselSlide extends Model
{
    protected $guarded = [];

    protected $table = 'carousel_slides';

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];
}
