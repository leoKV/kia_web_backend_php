<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Cache;

class TagController extends Controller
{
    public function getTagsByTipoTag()
    {
        try {
            // Intentar obtener los datos de la caché
            $cacheKey = 'tags_by_tipo_tag';
            $tags = Cache::get($cacheKey);

            if (!$tags) {
                // Si no están en la caché, ejecutar la consulta a la base de datos
                $tags = DB::select('SELECT * FROM sps_tag_info()');

                // Guardar los resultados en la caché por 120 minutos
                Cache::put($cacheKey, $tags, 60);
            }

            // Procesar los tags obtenidos
            foreach ($tags as &$tag) {
                $tag->tags = $tag->tags ? array_map(function($value) {
                    return trim($value, ' "');
                }, explode(',', trim($tag->tags, '{}'))) : [];
            }

            // Devolver los tags en formato JSON
            return response()->json($tags);

        } catch (\Exception $e) {
            // Manejar errores, registrarlos y devolver una respuesta de error
            Log::error('Error al obtener tags: ' . $e->getMessage());
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }
}
