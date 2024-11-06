<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log; 
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Models\Admin;

class AdminController extends Controller
{

    public function loginAdmin(Request $request)
    {
        try {
            // Obtener el nombre de usuario y la contraseña del request
            $usuario = $request->input('usuario');
            $password = $request->input('password');
            // Validar que el nombre de usuario y la contraseña no sean nulos
            if (!$usuario || !$password) {
                return response()->json(['message' => 'El nombre de usuario y la contraseña son requeridos.'], 400);
            }
            $cacheKey = "usuario_login_" . md5($usuario);
            // Eliminar la caché para asegurar que validamos siempre la contraseña
            Cache::forget($cacheKey);
            // Consultar la función almacenada para autenticar al usuario
            $resultado = DB::select('SELECT * FROM sps_admin_login(?, ?)', [$usuario, $password]);
            // Si no se encuentra el usuario o la contraseña no es correcta
            if (empty($resultado) || !$resultado[0]->usuario_id) {
                return response()->json(['message' => 'Usuario o contraseña incorrectos.'], 401);
            }
            // Almacenar el usuario completo en caché para futuras consultas
            $usuarioData = $resultado[0];
            Cache::put($cacheKey, $usuarioData, 60); // Cachear por 60 minutos
            // Buscar al usuario en la base de datos
            $admin = Admin::find($usuarioData->usuario_id);
            if (!$admin) {
                return response()->json(['message' => 'Usuario no encontrado.'], 404);
            }
            // Generar el token JWT
            $token = JWTAuth::fromUser($admin);
            // Devolver todos los detalles del usuario desde la caché
            return response()->json([
                'message' => 'Usuario autenticado exitosamente.',
                'usuario' => $usuarioData, // Devolver todos los detalles del usuario
                'token' => $token
            ]);
        } catch (\Exception $e) {
            Log::error('Error en el inicio de sesión del admin: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor', 'details' => $e->getMessage()], 500);
        }
    }


    public function getClientes()
    {
        try {
            $clientes = DB::select('SELECT * FROM sps_cliente_all()');
            // Verifica si se obtuvieron resultados
            if (empty($clientes)) {
                return response()->json(['message' => 'No se encontraron clientes.'], 404);
            }
            // Retorna la lista de clientes en formato JSON
            return response()->json(['clientes' => $clientes], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener todos los clientes: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    public function getPedidosClientes(Request $request){
        try {
            $cliente_id = $request->input('cliente_id');
            $estado = $request->input('estado');
            
            if (!$estado) {
                return response()->json(['message' => 'El estado es requerido.'], 400);
            }

            $perPage = $request->input('per_page', 5);
            $page = $request->input('page', 1);
            $offset = ($page - 1) * $perPage;

            $total = DB::selectOne('SELECT COUNT(*) AS count FROM sps_pedidos_clientes(?,?) AS c', [$cliente_id, $estado])->count;
            $pedidos = DB::select('SELECT * FROM sps_pedidos_clientes(?,?) AS c LIMIT ? OFFSET ?', [$cliente_id, $estado, $perPage, $offset]);
            foreach ($pedidos as &$pedido) {
                $pedido->canciones = json_decode($pedido->canciones);
            }

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
            Log::error('Error al obtener los pedidos de clientes: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    public function agregarPedido(Request $request)
    {
        try {
            // Obtener los datos del cliente y del usuario
            $usuario_id = $request->input('usuario_id');
            $cliente_id = $request->input('cliente_id');

            // Validar que los parámetros requeridos no sean nulos
            if (is_null($usuario_id) || is_null($cliente_id)) {
                return response()->json(['message' => 'El usuario_id y el cliente_id son requeridos.'], 400);
            }

            // Crear la cadena del array en el formato que PostgreSQL espera
            $datos = sprintf('{"%s", "%s", "%s"}', '0', $usuario_id, $cliente_id);
            $operacion = 1;

            // Llamar a la función de PostgreSQL para insertar el pedido
            $resultado = DB::select('SELECT * FROM crud_pedido(?, ?)', [$datos, $operacion]);

            // Asegurarnos de que el resultado no esté vacío antes de acceder a él
            if (!empty($resultado) && isset($resultado[0]->crud_pedido)) {
                // Decodificar el resultado de PostgreSQL
                $retorno = explode(',', trim($resultado[0]->crud_pedido, '{}'));

                if ($retorno[0] === '0') {
                    return response()->json([
                        'message' => $retorno[1]
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
            Log::error('Error al agregar pedido: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor', 'details' => $e->getMessage()], 500);
        }
    }

    public function closePedido(Request $request){
        try {
            $cliente_nombre = $request->input('cliente_nombre');
            // Validar que los parámetros requeridos no sean nulos
            if (!$cliente_nombre) {
                return response()->json(['message' => 'El nombre del cliente es requerido.'], 400);
            }
            // Llamar a la función de PostgreSQL para insertar el pedido
            $resultado = DB::select('SELECT * FROM spi_cerrar_pedido(?)', [$cliente_nombre]);
            // Asegurarnos de que el resultado no esté vacío antes de acceder a él
            if (!empty($resultado) && isset($resultado[0]->spi_cerrar_pedido)) {
                // Decodificar el resultado de PostgreSQL
                $retorno = explode(',', trim($resultado[0]->spi_cerrar_pedido, '{}'));
                if ($retorno[0] === '0') {
                    return response()->json([
                        'message' => $retorno[1]
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
            Log::error('Error al cerrar pedido: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor', 'details' => $e->getMessage()], 500);
        }
    }

    public function openPedido(Request $request){
        try {
            $pedido_id = $request->input('pedido_id');
            $estado_id = $request->input('estado_id');
            // Validar que los parámetros requeridos no sean nulos
            if (!$pedido_id || !$estado_id) {
                return response()->json(['message' => 'El pedido y estado es requerido'], 400);
            }
            // Llamar a la función de PostgreSQL para insertar el pedido
            $resultado = DB::select('SELECT * FROM spi_estado_pedido(?,?)', [$pedido_id, $estado_id]);
            // Asegurarnos de que el resultado no esté vacío antes de acceder a él
            if (!empty($resultado) && isset($resultado[0]->spi_estado_pedido)) {
                // Decodificar el resultado de PostgreSQL
                $retorno = explode(',', trim($resultado[0]->spi_estado_pedido, '{}'));
                if ($retorno[0] === '0') {
                    return response()->json([
                        'message' => $retorno[1]
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
            Log::error('Error al abrir pedido: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor', 'details' => $e->getMessage()], 500);
        }
    }

    public function copyUrl(Request $request)
    {
        try {
            // Recibir el arreglo de IDs de canciones del request
            $cancionIds = $request->input('cancion_ids');
            // Validar que el parámetro no esté vacío
            if (empty($cancionIds) || !is_array($cancionIds)) {
                return response()->json(['message' => 'Se requiere un arreglo de IDs de canciones.'], 400);
            }
            // Convertir el arreglo en un formato de arreglo literal de PostgreSQL
            $cancionIdsArray = '{' . implode(',', $cancionIds) . '}';
            // Ejecutar la función almacenada en PostgreSQL y obtener los resultados
            $urls = DB::select('SELECT * FROM sps_url_cancion_drive(?)', [$cancionIdsArray]);
            // Verificar si no hay resultados
            if (empty($urls)) {
                return response()->json(['message' => 'No se encontraron URLs para las canciones especificadas.'], 404);
            }
            // Retornar el resultado en formato JSON
            return response()->json(['urls' => $urls], 200);
        } catch (\Exception $e) {
            // Registrar el error en el log y devolver una respuesta de error
            Log::error('Error al obtener URLs de canciones: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    public function copyPedido(Request $request){
        try {
            // Recibir el arreglo de IDs de canciones del request
            $cliente_id = $request->input('cliente_id');
            $folio = $request->input('folio');
            // Validar que el parámetro no esté vacío
            if (!$cliente_id || !$folio ) {
                return response()->json(['message' => 'Se requiere el cliente y folio'], 400);
            }
            // Ejecutar la función almacenada en PostgreSQL y obtener los resultados
            $pedido = DB::select('SELECT * FROM sps_pedido_cadena(?,?)', [$cliente_id, $folio]);
            // Verificar si no hay resultados
            if (empty($pedido)) {
                return response()->json(['message' => 'No se pudo copiar el pedido.'], 404);
            }
            // Retornar el resultado en formato JSON
            return response()->json(['pedido' => $pedido], 200);
        } catch (\Exception $e) {
            // Registrar el error en el log y devolver una respuesta de error
            Log::error('Error al obtener pedido: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    public function addCancionPedido(Request $request) {
        try {
            // Obtener los datos del cliente y del usuario
            $pedido_id = $request->input('pedido_id');
            $cancion_ids = $request->input('cancion_ids');
            // Validar entrada
            if (!$pedido_id || empty($cancion_ids) || !is_array($cancion_ids)) {
                return response()->json(['message' => 'Se requiere el id del pedido y un arreglo de IDs de canciones.'], 400);
            }
            // Convertir a formato de PostgreSQL
            $cancionIdsArray = '{' . implode(',', $cancion_ids) . '}';
            // Llamar a la función de PostgreSQL
            $resultado = DB::select('SELECT * FROM spi_asigna_cancion_a_pedido(?, ?)', [$pedido_id, $cancionIdsArray]);
            // Verificar el resultado
            if (!empty($resultado) && isset($resultado[0]->spi_asigna_cancion_a_pedido)) {
                // Decodificar el resultado
                $retorno = explode(',', trim($resultado[0]->spi_asigna_cancion_a_pedido, '{}'));
                // Determinar el código de estado
                $statusCode = $retorno[0] === '0' ? 200 : 400;
                return response()->json(['message' => $retorno[1]], $statusCode);
            } else {
                return response()->json(['error' => 'Error en la respuesta de la función de PostgreSQL'], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error al agregar cancion(es) al pedido: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor', 'details' => $e->getMessage()], 500);
        }
    }
 
    public function deleteCancionPedido(Request $request) {
        try {
            // Obtener los datos del cliente y del usuario
            $pedido_id = $request->input('pedido_id');
            $cancion_ids = $request->input('cancion_ids');
            // Validar entrada
            if (!$pedido_id || empty($cancion_ids) || !is_array($cancion_ids)) {
                return response()->json(['message' => 'Se requiere el id del pedido y un arreglo de IDs de canciones.'], 400);
            }
            // Convertir a formato de PostgreSQL
            $cancionIdsArray = '{' . implode(',', $cancion_ids) . '}';
            // Llamar a la función de PostgreSQL
            $resultado = DB::select('SELECT * FROM spd_cancion_pedido(?, ?)', [$pedido_id, $cancionIdsArray]);
            // Verificar el resultado
            if (!empty($resultado) && isset($resultado[0]->spd_cancion_pedido)) {
                // Decodificar el resultado
                $retorno = explode(',', trim($resultado[0]->spd_cancion_pedido, '{}'));
                // Determinar el código de estado
                $statusCode = $retorno[0] === '0' ? 200 : 400;
                return response()->json(['message' => $retorno[1]], $statusCode);
            } else {
                return response()->json(['error' => 'Error en la respuesta de la función de PostgreSQL'], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error al eliminar cancion(es) del pedido: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor', 'details' => $e->getMessage()], 500);
        }
    }

    public function getPorcentajeCancion(Request $request)
    {
        try {
            $cancion_id = $request->query('cancion_id');
            // Ejecutar la consulta a la base de datos para obtener los detalles de la canción
            $porcentaje = DB::select('SELECT * FROM sps_bitacora_acciones(?)', [$cancion_id]);

            if (empty($porcentaje)) {
                return response()->json(['message' => 'Porcentaje de canción no encontrado.'], 404);
            }

            // Formatear la respuesta para devolver múltiples registros
            $resultado = array_map(function($registro) {
                return [
                    'id' => $registro->id,
                    'usuario' => $registro->usuario,
                    'accion' => $registro->accion,
                    'fecha_hora' => $registro->fecha_hora,
                    'mac' => $registro->mac
                ];
            }, $porcentaje);

            return response()->json($resultado);
        } catch (\Exception $e) {
            Log::error('Error al obtener porcentaje de la canción: ' . $e->getMessage());
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }


}
