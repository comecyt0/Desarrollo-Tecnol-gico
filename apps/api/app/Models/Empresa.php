<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de Empresa (antes Institución).
 *
 * Tabla: `empresas` (renombrada desde `instituciones` en migration
 * 2026_06_02_120000_rename_instituciones_to_empresas).
 */
class Empresa extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'empresas';

    public function municipio()
    {
        return $this->belongsTo(Municipio::class);
    }

    public function solicitantes()
    {
        return $this->hasMany(User::class, 'empresa_id');
    }
}
