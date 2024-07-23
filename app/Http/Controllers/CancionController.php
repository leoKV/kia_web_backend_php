<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Cancion;
use App\Models\Parametro;

class CancionController extends Controller
{
    public function getAllCanciones(Request $request)
    {
        $perPage = $request->input('per_page', 12); // Número de elementos por página, por defecto 12
        $page = $request->input('page', 1); // Página actual, por defecto 1
        $offset = ($page - 1) * $perPage; // Calcular el offset
        // Ejecutar la función pgsqly obtener todos los resultados
        $allResults = DB::select('SELECT * FROM public.sps_canciones_all()');
        // Filtrar los resultados para la página actual
        $results = array_slice($allResults, $offset, $perPage);
        // Contar el total de registros
        $total = count($allResults);
        // Formatear los resultados para el front-end
        $mappedCanciones = Cancion::hydrate($results);
        // Preparar la respuesta para la paginación
        $response = [
            'data' => $mappedCanciones,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
        ];
        return response()->json($response);
    }
    // Obtener canciones por nombre de canción o artista.
    public function getCancionesByNombre(Request $request)
    {
        $nombre = $request->query('nombre');
        if (!$nombre) {
            return response()->json(['error' => 'El nombre es requerido.'], 400);
        }

        $perPage = $request->input('per_page', 12); // Número de elementos por página, por defecto 12
        $page = $request->input('page', 1); // Página actual, por defecto 1

        $offset = ($page - 1) * $perPage;

        // Obtener el total de canciones que coinciden con el nombre
        $total = DB::selectOne('SELECT COUNT(*) AS count FROM sps_cancion_artista(?) AS c', [$nombre])->count;

        // Obtener las canciones paginadas
        $canciones = DB::select('SELECT * FROM sps_cancion_artista(?) AS c LIMIT ? OFFSET ?', [$nombre, $perPage, $offset]);

        if (empty($canciones)) {
            return response()->json(['message' => 'Canción no encontrada.'], 404);
        }
        // Transformar datos para el formato esperado
        $mappedCanciones = Cancion::hydrate($canciones);
        // Devolver los datos en el formato deseado junto con la paginación
        return response()->json([
            'data' => $mappedCanciones,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total
        ]);
    }
    //Obtener detalle de canción por id.
    public function getCancionDetailById(Request $request)
    {
        $id = $request->query('id');
        $cancionDetail = Cache::remember("cancion_detail_{$id}", 60, function() use ($id) {
            return DB::select('SELECT * FROM sps_cancion_detail(?)', [$id]);
        });
        if (empty($cancionDetail)) {
            return response()->json(['message' => 'Canción no encontrada.'], 404);
        }
        $cancion = $cancionDetail[0];
        return response()->json([
            'cancion_id' => $cancion->cancion_id,
            'cancion_nombre' => $cancion->cancion_nombre,
            'artista' => $cancion->artista,
            'tags' => $this->processTags($cancion->tags),
            'urls' => $this->processUrls($cancion->urls),
            'tipos_urls' => $this->processUrls($cancion->tipos_urls),
        ]);
    }
    //Procesando tags para el detalle.
    private function processTags($tags)
    {
        if (!$tags) return [];
        return array_map(function ($tag) {
            return trim($tag, '"');
        }, explode(',', trim($tags, '{}')));
    }
    //Procesando urls para el detalle.
    private function processUrls($urls)
    {
        if (!$urls) return [];
        return array_map('trim', explode(',', trim($urls, '{}')));
    }
    //Obtener estado del parametro url demo.
    public function getUrlDemoState()
    {
        $parametro = Cache::remember('parametro_url_demo', 60, function() {
            return DB::select('SELECT * FROM sps_url_demo_state()');
        });
        $mappedParametro = Parametro::hydrate($parametro);
        return response()->json($mappedParametro[0]);
    }
    //Obtener número de whatsapp.
    public function getNumeroWhatsapp()
    {
        $parametro = Cache::remember('parametro_numero_whatsapp', 60, function() {
            return DB::select('SELECT * FROM sps_numero_whatsapp()');
        });
        $mappedParametro = Parametro::hydrate($parametro);
        return response()->json($mappedParametro[0]);
    }
}
