<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cancion extends Model
{
    use HasFactory;

    // Definición de propiedades.
    protected $appends = [
        'id',
        'nombreCancion',
        'artistaId',
        'nombreArtista'
    ];

    // Definición de atributos a ocultar en las consultas JSON
    protected $hidden = [
        'cancion_id',
        'cancion_nombre',
        'nombre',
        'artista_id',
        'artista',
        'artista_nombre'
    ];

    // Mutadores
    public function getIdAttribute()
    {
        return $this->attributes['cancion_id'] ?? $this->attributes['id'];
    }
 
    public function getNombreCancionAttribute()
    {
        return $this->attributes['cancion_nombre'] ?? $this->attributes['nombre'];
    }
 
    public function getArtistaIdAttribute()
    {
        return $this->attributes['artista_id'];
    }
 
    public function getNombreArtistaAttribute()
    {
        return $this->attributes['artista'] ?? $this->attributes['artista_nombre'];
    }
}
