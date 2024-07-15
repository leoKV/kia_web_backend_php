<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class CancionTagController extends Controller
{
    // public function getCancionesByTags(Request $request)
    // {
    //     $tags = $request->query('tags');
    //     if (!$tags) {
    //         return response()->json(['error' => 'Las tags son requeridas.'], 400);
    //     }

    //     $tagsArray = explode(',', $tags);
    //     $tagsIntArray = array_map('intval', $tagsArray);

    //     // Convertir el array a formato de cadena para la consulta
    //     $tagsString = implode(',', $tagsIntArray);

    //     $canciones = DB::select("SELECT * FROM sps_canciones_por_tags(ARRAY[$tagsString]::int[])");

    //     // Convertir las cadenas de tags y tipos_tags en arrays
    //     $canciones = array_map(function($cancion) {
    //         $cancion->tags = explode(',', trim($cancion->tags, '{}'));
    //         $cancion->tipos_tags = explode(',', trim($cancion->tipos_tags, '{}'));
    //         return $cancion;
    //     }, $canciones);

    //     return response()->json($canciones);
    // }

    public function getCancionesByTags(Request $request)
    {
        $tags = $request->query('tags');
        if (!$tags) {
            return response()->json(['error' => 'Las tags son requeridas.'], 400);
        }

        $tagsArray = explode(',', $tags);
        $tagsIntArray = array_map('intval', $tagsArray);
        $tagsString = implode(',', $tagsIntArray);

        // Usar los tags como clave de caché
        $cacheKey = 'canciones_by_tags_' . md5($tagsString);

        $canciones = Cache::remember($cacheKey, 60, function() use ($tagsString) {
            return DB::select("SELECT * FROM sps_canciones_por_tags(ARRAY[$tagsString]::int[])");
        });

        // Convertir las cadenas de tags y tipos_tags en arrays
        $canciones = array_map(function($cancion) {
            $cancion->tags = explode(',', trim($cancion->tags, '{}'));
            $cancion->tipos_tags = explode(',', trim($cancion->tipos_tags, '{}'));
            return $cancion;
        }, $canciones);

        return response()->json($canciones);
    }
}
