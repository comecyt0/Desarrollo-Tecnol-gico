<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitudAcceso extends Model
{
    use HasFactory;

    protected $table = 'solicitudes_acceso';

    protected $guarded = [];

    protected $hidden = ['password'];

    protected $casts = [
        'revisado_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * The admin user who reviewed this access request.
     */
    public function revisadoPor()
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }
}
