<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log; 
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Models\Cliente;

class ClienteController extends Controller
{
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
                Cache::put($cacheKey, $clienteId, 60);
            }
            // Buscar al cliente en la base de datos
            $cliente = Cliente::find($clienteId);
            if (!$cliente) {
                return response()->json(['message' => 'Cliente no encontrado.'], 404); // Cliente no encontrado
            }
            // Generar el token JWT
            $token = JWTAuth::fromUser($cliente);
            // Retornar el token y un mensaje de éxito
            return response()->json([
                'message' => 'Cliente autenticado exitosamente.',
                'cliente_id' => $clienteId,
                'token' => $token
            ]);
        } catch (\Exception $e) {
            Log::error('Error en el inicio de sesión del cliente: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    public function loginClienteClave(Request $request)
    {
        try {
            // Obtener la clave del cliente desde el request
            $clave = $request->input('clave');
            // Validar que la clave no sea nula
            if (!$clave) {
                return response()->json(['message' => 'La clave es requerida.'], 400);
            }
            // Limpiar la clave para usarla como clave de caché (opcional, puedes ajustarlo según sea necesario)
            $cacheKey = "cliente_login_clave_" . $clave;
            // Intentar obtener el resultado de la caché
            $clienteId = Cache::get($cacheKey);
            // Si el resultado no está en caché, realizar la consulta a la base de datos
            if (!$clienteId) {
                // Llamar a la función de PostgreSQL sps_cliente_login_clave
                $resultado = DB::select('SELECT sps_cliente_login_clave(?) AS cliente_id', [$clave]);
                // Verificar si se obtuvo un resultado válido
                if (empty($resultado) || !$resultado[0]->cliente_id) {
                    return response()->json(['message' => 'Cliente no encontrado o no autorizado.'], 401); // No autorizado
                }
                // Guardar el id del cliente en la variable
                $clienteId = $resultado[0]->cliente_id;
                Cache::put($cacheKey, $clienteId, 60); // Cachear el resultado por 60 minutos
            }
            // Buscar al cliente en la base de datos
            $cliente = Cliente::find($clienteId);
            if (!$cliente) {
                return response()->json(['message' => 'Cliente no encontrado.'], 404); // Cliente no encontrado
            }
            // Generar el token JWT
            $token = JWTAuth::fromUser($cliente);
            // Retornar el token y un mensaje de éxito
            return response()->json([
                'message' => 'Cliente autenticado exitosamente.',
                'cliente_id' => $clienteId,
                'token' => $token
            ]);
        } catch (\Exception $e) {
            Log::error('Error en el inicio de sesión del cliente por clave: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    public function getPedidosByClienteId(Request $request)
    {
        try {
            $clienteId = $request->query('clienteId');
            if (!$clienteId) {
                return response()->json(['message' => 'El ID del cliente es requerido.'], 400);
            }

            $perPage = $request->input('per_page', 5);
            $page = $request->input('page', 1);
            $offset = ($page - 1) * $perPage;

            // Consultar el total de pedidos en tiempo real
            $total = DB::selectOne('SELECT COUNT(*) AS count FROM sps_cliente_pedidos(?) AS c', [$clienteId])->count;

            // Consultar los pedidos en tiempo real
            $pedidos = DB::select('SELECT * FROM sps_cliente_pedidos(?) AS c LIMIT ? OFFSET ?', [$clienteId, $perPage, $offset]);
            foreach ($pedidos as &$pedido) {
                $pedido->canciones = json_decode($pedido->canciones);
            }

            // Si no hay pedidos, enviar una respuesta 404
            if (empty($pedidos)) {
                return response()->json(['message' => 'Pedidos no encontrados.'], 404);
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


    public function updateEstadoPagoCliente(Request $request) {
        try {
            // Obtener los datos del cliente y del usuario
            $pedido_id = $request->input('pedido_id');
            $cancion_ids = $request->input('cancion_ids');
            $estado_pago = $request->input('estado_pago');
            // Validar entrada
            if (!$pedido_id || empty($cancion_ids) || !is_array($cancion_ids) || is_null($estado_pago)) {
                return response()->json(['message' => 'Se requiere el id del pedido, IDs de canciones y estado de pago.'], 400);
            }
            // Convertir a formato de PostgreSQL
            $cancionIdsArray = '{' . implode(',', $cancion_ids) . '}';
            // Llamar a la función de PostgreSQL
            $resultado = DB::select('SELECT * FROM spu_cancion_pedido_estado_cliente(?, ?, ?)', [$pedido_id, $cancionIdsArray, $estado_pago]);
            // Verificar el resultado
            if (!empty($resultado) && isset($resultado[0]->spu_cancion_pedido_estado_cliente)) {
                // Decodificar el resultado
                $retorno = explode(',', trim($resultado[0]->spu_cancion_pedido_estado_cliente, '{}'));
                // Determinar el código de estado
                $statusCode = $retorno[0] === '0' ? 200 : 400;
                return response()->json(['message' => $retorno[1]], $statusCode);
            } else {
                return response()->json(['error' => 'Error en la respuesta de la función de PostgreSQL'], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error al actualizar estado de pago de canciones: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor', 'details' => $e->getMessage()], 500);
        }
    }

    public function updateComentarioCancion(Request $request) {
        try {
            // Obtener los datos del cliente y del usuario
            $pedido_id = $request->input('pedido_id');
            $cancion_id = $request->input('cancion_id');
            $comentario = $request->input('comentario');
            // Validar entrada
            if (!$pedido_id || !$cancion_id || !$comentario) {
                return response()->json(['message' => 'Se requiere el id del pedido, ID de canción y comentario.'], 400);
            }
            // Llamar a la función de PostgreSQL
            $resultado = DB::select('SELECT * FROM spu_cancion_comentario(?, ?, ?)', [$pedido_id, $cancion_id, $comentario]);
            // Verificar el resultado
            if (!empty($resultado) && isset($resultado[0]->spu_cancion_comentario)) {
                // Decodificar el resultado
                $retorno = explode(',', trim($resultado[0]->spu_cancion_comentario, '{}'));
                // Determinar el código de estado
                $statusCode = $retorno[0] === '0' ? 200 : 400;
                return response()->json(['message' => $retorno[1]], $statusCode);
            } else {
                return response()->json(['error' => 'Error en la respuesta de la función de PostgreSQL'], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error al actualizar comentario de canción: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor', 'details' => $e->getMessage()], 500);
        }
    }

    public function updateDescargas(Request $request) {
        try {
            // Obtener los datos del cliente y del usuario
            $cancion_id = $request->input('cancion_id');
            // Validar entrada
            if (!$cancion_id) {
                return response()->json(['message' => 'Se requiere el id de la canción'], 400);
            }
            // Llamar a la función de PostgreSQL
            $resultado = DB::select('SELECT * FROM spu_descargas_count(?)', [$cancion_id]);
            // Verificar el resultado
            if (!empty($resultado) && isset($resultado[0]->spu_descargas_count)) {
                // Decodificar el resultado
                $retorno = explode(',', trim($resultado[0]->spu_descargas_count, '{}'));
                // Determinar el código de estado
                $statusCode = $retorno[0] === '0' ? 200 : 400;
                return response()->json(['message' => $retorno[1]], $statusCode);
            } else {
                return response()->json(['error' => 'Error en la respuesta de la función de PostgreSQL'], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error al actualizar contador de descargas: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor', 'details' => $e->getMessage()], 500);
        }
    }

    public function getCancionesRandom(Request $request)
    {
        try {
            $cliente_id = $request->input('cliente_id');
            if (!$cliente_id) {
                return response()->json(['message' => 'El id del cliente es necesario.'], 400); // Código 400 para error de solicitud
            }
            // Ejecutar la consulta a la base de datos para obtener los detalles de las canciones
            $cancionDetails = DB::select('SELECT * FROM sps_canciones_random(?)', [$cliente_id]);
            if (empty($cancionDetails)) {
                return response()->json(['message' => 'Canciones no encontradas.'], 404);
            }
            // Procesar canciones
            $result = array_map(function ($cancion) {
                return [
                    'cancion_id' => $cancion->cancion_id,
                    'cancion_nombre' => $cancion->cancion_nombre,
                    'artista' => $cancion->artista,
                    'valor' => $this->processValues($cancion->valor),
                    'tags' => $this->processTags($cancion->tags),
                    'tags_ids' => $this->processTagsIds($cancion->tags_ids),
                    'url' => $cancion->url,
                ];
            }, $cancionDetails);
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error al obtener detalles de canciones: ' . $e->getMessage());
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }

     //Procesar valores para el detalle.
     private function processValues($values)
     {
          if (!$values) return [];
          return array_map('intval', explode(',', trim($values, '{}')));
     }

     // Procesar tags para el detalle.
     private function processTags($tags)
     {
         if (!$tags) return [];
         return array_map(function ($tag) {
             return trim($tag, '"');
         }, explode(',', trim($tags, '{}')));
     }

     // Procesar tags_ids para el detalle.
     private function processTagsIds($tagsIds)
     {
          if (!$tagsIds) return [];
          return array_map('intval', explode(',', trim($tagsIds, '{}')));
     }

     public function canjearCupon(Request $request){
        try {
            $cliente_id = $request->input('cliente_id');
            $codigo = $request->input('codigo');
            $cancion_id = $request->input('cancion_id');
            // Validar que los parámetros requeridos no sean nulos
            if (!$cliente_id || !$codigo || !$cancion_id) {
                return response()->json(['message' => 'Se requiere id del cliente, codigo y la canción.'], 400);
            }
            // Llamar a la función de PostgreSQL para insertar el pedido
            $resultado = DB::select('SELECT * FROM sps_canjear_cupon(?,?,?)', [$cliente_id, $codigo, $cancion_id]);
            // Asegurarnos de que el resultado no esté vacío antes de acceder a él
            if (!empty($resultado) && isset($resultado[0]->sps_canjear_cupon)) {
                // Decodificar el resultado de PostgreSQL
                $retorno = explode(',', trim($resultado[0]->sps_canjear_cupon, '{}'));
                if ($retorno[0] === '0') {
                    return response()->json([
                        'message' => $retorno[2]
                    ], 200);
                } else {
                    return response()->json([
                        'message' => $retorno[1]
                    ], 400);
                }
            } else {
                return response()->json(['error' => 'Error en la respuesta de la función de PostgreSQL'], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error al canjear canción con el cupón: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor', 'details' => $e->getMessage()], 500);
        }
    }


}
