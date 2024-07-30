<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Cancion;
use App\Models\Parametro;

class CancionController extends Controller
{
       // Obtener todas las canciones con paginación.
       public function getAllCanciones(Request $request)
       {
           try {
               $perPage = $request->input('per_page', 12); // Número de elementos por página, por defecto 12
               $page = $request->input('page', 1); // Página actual, por defecto 1
               $offset = ($page - 1) * $perPage; // Calcular el offset
   
               $cacheKey = 'all_canciones';
               $allResults = Cache::get($cacheKey);
   
               if (!$allResults) {
                   // Si no están en la caché, ejecutar la consulta a la base de datos
                   $allResults = DB::select('SELECT * FROM public.sps_canciones_all()');
                   // Guardar los resultados en la caché por 120 minutos
                   Cache::put($cacheKey, $allResults, 60);
               }
   
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
           } catch (\Exception $e) {
               Log::error('Error al obtener todas las canciones: ' . $e->getMessage());
               return response()->json(['error' => 'Internal Server Error'], 500);
           }
       }
   
       // Obtener canciones por nombre, con paginación.
        public function getCancionesByNombre(Request $request)
        {
            try {
                $nombre = $request->query('nombre');
                if (!$nombre) {
                    return response()->json(['error' => 'El nombre es requerido.'], 400);
                }

                $perPage = $request->input('per_page', 12); // Número de elementos por página, por defecto 12
                $page = $request->input('page', 1); // Página actual, por defecto 1
                $offset = ($page - 1) * $perPage; // Calcular el offset

                // Obtener el total de canciones que coinciden con el nombre
                $totalKey = "total_canciones_nombre_" . md5($nombre);
                $total = Cache::get($totalKey);

                if ($total === null) {
                    // Si no está en la caché, ejecutar la consulta a la base de datos
                    $total = DB::selectOne('SELECT COUNT(*) AS count FROM sps_cancion_artista(?) AS c', [$nombre])->count;
                    // Guardar el total en la caché por 120 minutos
                    Cache::put($totalKey, $total, 120);
                }

                // Obtener las canciones paginadas
                $cancionesKey = "canciones_nombre_" . md5($nombre) . "_page_$page";
                $canciones = Cache::get($cancionesKey);

                if ($canciones === null) {
                    // Si no están en la caché, ejecutar la consulta a la base de datos
                    $canciones = DB::select('SELECT * FROM sps_cancion_artista(?) AS c LIMIT ? OFFSET ?', [$nombre, $perPage, $offset]);
                    // Guardar las canciones en la caché por 120 minutos
                    Cache::put($cancionesKey, $canciones, 120);
                }

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
                    'total' => $total,
                ]);
            } catch (\Exception $e) {
                Log::error('Error al obtener canciones por nombre: ' . $e->getMessage());
                return response()->json(['error' => 'Internal Server Error'], 500);
            }
        }

   
       // Obtener detalles de canción por su id.
       public function getCancionDetailById(Request $request)
       {
           try {
               $id = $request->query('id');
               $cacheKey = "cancion_detail_{$id}";
   
               $cancionDetail = Cache::get($cacheKey);
   
               if (!$cancionDetail) {
                   // Si no están en la caché, ejecutar la consulta a la base de datos
                   $cancionDetail = DB::select('SELECT * FROM sps_cancion_detail(?)', [$id]);
                   // Guardar los detalles en la caché por 120 minutos
                   Cache::put($cacheKey, $cancionDetail, 60);
               }
   
               if (empty($cancionDetail)) {
                   return response()->json(['message' => 'Canción no encontrada.'], 404);
               }
   
               $cancion = $cancionDetail[0];
   
               return response()->json([
                   'cancion_id' => $cancion->cancion_id,
                   'cancion_nombre' => $cancion->cancion_nombre,
                   'artista' => $cancion->artista,
                   'tags' => $this->processTags($cancion->tags),
                   'url' => $cancion->url
               ]);
           } catch (\Exception $e) {
               Log::error('Error al obtener detalle de la canción: ' . $e->getMessage());
               return response()->json(['error' => 'Internal Server Error'], 500);
           }
       }
   
       // Procesar tags para el detalle.
       private function processTags($tags)
       {
           if (!$tags) return [];
           return array_map(function ($tag) {
               return trim($tag, '"');
           }, explode(',', trim($tags, '{}')));
       }
       
       // Obtener número de whatsapp.
       public function getNumeroWhatsapp()
       {
           try {
               $cacheKey = 'parametro_numero_whatsapp';
   
               $parametro = Cache::get($cacheKey);
   
               if (!$parametro) {
                   // Si no está en la caché, ejecutar la consulta a la base de datos
                   $parametro = DB::select('SELECT * FROM sps_numero_whatsapp()');
                   // Guardar los resultados en la caché por 120 minutos
                   Cache::put($cacheKey, $parametro, 60);
               }
   
               $mappedParametro = Parametro::hydrate($parametro);
               return response()->json($mappedParametro[0]);
           } catch (\Exception $e) {
               Log::error('Error al obtener el número de WhatsApp: ' . $e->getMessage());
               return response()->json(['error' => 'Internal Server Error'], 500);
           }
       }
}
