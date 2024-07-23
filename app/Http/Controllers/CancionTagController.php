<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class CancionTagController extends Controller
{
    //Obtener canciones por filtros tags.
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

        // Obtener el número de elementos por página y la página actual
        $perPage = $request->input('per_page', 12); // Número de elementos por página, por defecto 12
        $page = $request->input('page', 1); // Página actual, por defecto 1
        $offset = ($page - 1) * $perPage;

        // Obtener el total de canciones que coinciden con los tags
        $total = Cache::remember($cacheKey . '_count', 60, function() use ($tagsString) {
            return DB::selectOne("SELECT COUNT(*) AS count FROM sps_canciones_por_tags(ARRAY[$tagsString]::int[])")->count;
        });

        // Obtener las canciones paginadas
        $canciones = Cache::remember($cacheKey . "_page_$page", 60, function() use ($tagsString, $perPage, $offset) {
            return DB::select("SELECT * FROM sps_canciones_por_tags(ARRAY[$tagsString]::int[]) LIMIT ? OFFSET ?", [$perPage, $offset]);
        });

        // Convertir las cadenas de tags y tipos_tags en arrays
        $canciones = array_map(function($cancion) {
            $cancion->tags = explode(',', trim($cancion->tags, '{}'));
            $cancion->tipos_tags = explode(',', trim($cancion->tipos_tags, '{}'));
            return $cancion;
        }, $canciones);

        // Devolver los datos en el formato deseado junto con la paginación
        return response()->json([
            'data' => $canciones,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total
        ]);
    }

}
