<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log; 

class ClienteController extends Controller
{

    // Función para autenticar al cliente por su teléfono.
    public function loginCliente(Request $request)
    {
        try {
            // Obtener el número de teléfono del cliente desde el request
            $telefono = $request->input('telefono');
            
            // Validar que el teléfono no sea nulo
            if (!$telefono) {
                return response()->json(['message' => 'El número de teléfono es requerido.'], 400);
            }

            // Limpiar el número de teléfono para usarlo como clave de caché
            $cacheKey = "cliente_login_" . preg_replace('/[^0-9]/', '', $telefono);
            
            // Intentar obtener el resultado de la caché
            $clienteId = Cache::get($cacheKey);

            // Si el resultado no está en caché, realizar la consulta a la base de datos
            if (!$clienteId) {
                // Llamar a la función de PostgreSQL sps_cliente_login
                $resultado = DB::select('SELECT sps_cliente_login(?) AS cliente_id', [$telefono]);

                // Verificar si se obtuvo un resultado válido
                if (empty($resultado) || !$resultado[0]->cliente_id) {
                    return response()->json(['message' => 'Cliente no encontrado o no autorizado.'], 401); // No autorizado
                }

                // Guardar el id del cliente en la variable
                $clienteId = $resultado[0]->cliente_id;

                // Guardar el id del cliente en la caché por 60 minutos
                Cache::put($cacheKey, $clienteId, 60);
            }

            // Retornar el id del cliente y un mensaje de éxito
            return response()->json([
                'message' => 'Cliente autenticado exitosamente.',
                'cliente_id' => $clienteId
            ]);

        } catch (\Exception $e) {
            Log::error('Error en el inicio de sesión del cliente: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    // public function getPedidosByClienteId(Request $request)
    // {
    //     try {
    //         $clienteId = $request->query('clienteId');
    //         // Validar que el clienteId no sea nulo
    //         if (!$clienteId) {
    //             return response()->json(['message' => 'El ID del cliente es requerido.'], 400);
    //         }
    //         $cacheKey = "cliente_id_{$clienteId}";
    //         // Intentar obtener los pedidos desde la caché
    //         $pedidosCliente = Cache::get($cacheKey);
    //         // Si no están en caché, hacer la consulta
    //         if (!$pedidosCliente) {
    //             $pedidosCliente = DB::select('SELECT * FROM sps_cliente_pedidos(?)', [$clienteId]);
    //             // Decodificar el JSON en el campo canciones antes de almacenar en caché
    //             foreach ($pedidosCliente as &$pedido) {
    //                 $pedido->canciones = json_decode($pedido->canciones);
    //             }
    //             // Verificar que haya resultados antes de cachar
    //             if (!empty($pedidosCliente)) {
    //                 Cache::put($cacheKey, $pedidosCliente, 60);
    //             } else {
    //                 return response()->json(['message' => 'Pedidos no encontrados.'], 404);
    //             }
    //         }
    //         // Retornar la lista de pedidos
    //         return response()->json($pedidosCliente);

    //     } catch (\Exception $e) {
    //         Log::error('Error al obtener los pedidos del cliente: ' . $e->getMessage());
    //         return response()->json(['error' => 'Error interno del servidor'], 500);
    //     }
    // }

    public function getPedidosByClienteId(Request $request)
    {
        try {
    
            $clienteId = $request->query('clienteId');
            // Validar que el clienteId no sea nulo
            if (!$clienteId) {
                return response()->json(['message' => 'El ID del cliente es requerido.'], 400);
            }

            $perPage = $request->input('per_page', 1);
            $page = $request->input('page', 1);
            $offset = ($page - 1) * $perPage;

            $totalKey = "total_pedidos_cliente_" .md5($clienteId);
            $total = Cache::get($totalKey);

            if ($total === null) {
                $total = DB::selectOne('SELECT COUNT(*) AS count FROM sps_cliente_pedidos(?) AS c', [$clienteId])->count;
                Cache::put($totalKey, $total, 60);
            }

            $pedidosKey = "pedidos_cliente_" . md5($clienteId) . "_page_$page";
            $pedidos = Cache::get($pedidosKey);

            if ($pedidos === null) {
                $pedidos = DB::select('SELECT * FROM sps_cliente_pedidos(?) AS c LIMIT ? OFFSET ?', [$clienteId, $perPage, $offset]);
                foreach ($pedidos as &$pedido) {
                    $pedido->canciones = json_decode($pedido->canciones);
                }
                if (!empty($pedidos)) {
                    Cache::put($pedidosKey, $pedidos, 60);
                } else {
                    return response()->json(['message' => 'Pedidos no encontrados.'], 404);
                }
            }

            return response()->json([
                'data' => $pedidos,
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener los pedidos del cliente: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }


}
