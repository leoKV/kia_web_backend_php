<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Cancion;
use App\Models\Parametro;

class CancionController extends Controller
{
    // Obtener todas las canciones.
    public function getAllCanciones()
    {
        $canciones = Cache::remember('canciones_all', 60, function() {
            return DB::select('SELECT * FROM sps_canciones_all()');
        });
        $mappedCanciones = Cancion::hydrate($canciones);
        return response()->json($mappedCanciones);
    }
     
    // Obtener canciones por nombre de canción o artista.
    public function getCancionesByNombre(Request $request)
    {
         $nombre = $request->query('nombre');
         if (!$nombre) {
             return response()->json(['error' => 'El nombre es requerido.'], 400);
         }
 
         $canciones = Cache::remember("canciones_nombre_{$nombre}", 60, function() use ($nombre) {
             return DB::select('SELECT * FROM sps_cancion_artista(?)', [$nombre]);
         });
 
         if (empty($canciones)) {
             return response()->json(['message' => 'Canción no encontrada.'], 404);
         }
 
         $mappedCanciones = Cancion::hydrate($canciones);
         return response()->json($mappedCanciones);
    }

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

    private function processTags($tags)
    {
        if (!$tags) return [];
        return array_map(function ($tag) {
            return trim($tag, '"');
        }, explode(',', trim($tags, '{}')));
    }

    private function processUrls($urls)
    {
        if (!$urls) return [];
        return array_map('trim', explode(',', trim($urls, '{}')));
    }

    public function getUrlDemoState()
    {
        $parametro = Cache::remember('parametro_url_demo', 60, function() {
            return DB::select('SELECT * FROM sps_url_demo_state()');
        });
        $mappedParametro = Parametro::hydrate($parametro);
        return response()->json($mappedParametro[0]);
    }

    public function getNumeroWhatsapp()
    {
        $parametro = Cache::remember('parametro_numero_whatsapp', 60, function() {
            return DB::select('SELECT * FROM sps_numero_whatsapp()');
        });
        $mappedParametro = Parametro::hydrate($parametro);
        return response()->json($mappedParametro[0]);
    }
}
