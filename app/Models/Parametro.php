<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Parametro extends Model
{
    use HasFactory;

    // Definición de propiedades.
    protected $appends = [
        'p_Id',
        'p_Nombre',
        'p_Valor'
    ];

    // Definición de atributos a ocultar en las consultas JSON
    protected $hidden = [
        'p_id',
        'p_nombre',
        'p_valor'
    ];

    // Mutadores
    public function getPIdAttribute()
    {
        return $this->attributes['p_id'];
    }

    public function getPNombreAttribute()
    {
        return $this->attributes['p_nombre'];
    }

    public function getPValorAttribute()
    {
        return $this->attributes['p_valor'];
    }

}
