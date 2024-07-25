<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class CancionTagController extends Controller
{
    public function getCancionesByTags(Request $request)
    {
        try {
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

            // Verificar si el total está en caché
            $total = Cache::get($cacheKey . '_count');
            if (!$total) {
                // Si no está en caché, hacer la consulta a la base de datos y guardarla en caché
                $total = DB::selectOne("SELECT COUNT(*) AS count FROM sps_canciones_por_tags(ARRAY[$tagsString]::int[])")->count;
                Cache::put($cacheKey . '_count', $total, 60); // Guardar en caché por 120 minutos
            }

            // Verificar si las canciones están en caché
            $canciones = Cache::get($cacheKey . "_page_$page");
            if (!$canciones) {
                // Si no están en caché, hacer la consulta a la base de datos y guardarla en caché
                $canciones = DB::select("SELECT * FROM sps_canciones_por_tags(ARRAY[$tagsString]::int[]) LIMIT ? OFFSET ?", [$perPage, $offset]);
                Cache::put($cacheKey . "_page_$page", $canciones, 60); // Guardar en caché por 120 minutos
            }

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
                'total' => $total,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener canciones por tags: ' . $e->getMessage());
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }

}
