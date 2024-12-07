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

    public function getClientesPage(Request $request)
    {
        try {
            // Parámetros de paginación
            $perPage = $request->input('per_page');
            $page = $request->input('page');
            $offset = ($page - 1) * $perPage;
            // Contar el total de clientes
            $total = DB::selectOne('SELECT COUNT(*) AS count FROM sps_cliente_all()')->count;
            // Obtener clientes con límite y desplazamiento
            $clientes = DB::select('SELECT * FROM sps_cliente_all() LIMIT ? OFFSET ?', [$perPage, $offset]);
            // Verificar si se encontraron clientes
            if (empty($clientes)) {
                return response()->json(['message' => 'No se encontraron clientes.'], 404);
            }
            // Retornar los datos en formato JSON con paginación
            return response()->json([
                'data' => $clientes,
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener clientes con paginación: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    public function getClientesByNombre(Request $request)
    {
        try {
            $nombre = $request->input('nombre');
            if (!$nombre) {
                return response()->json(['error' => 'El nombre es requerido.'], 400);
            }
            // Parámetros de paginación
            $perPage = $request->input('per_page');
            $page = $request->input('page');
            $offset = ($page - 1) * $perPage;
            // Contar el total de clientes
            $total = DB::selectOne('SELECT COUNT(*) AS count FROM sps_buscar_cliente(?) AS c', [$nombre])->count;
            // Obtener clientes con límite y desplazamiento
            $clientes = DB::select('SELECT * FROM sps_buscar_cliente(?) AS c LIMIT ? OFFSET ?', [$nombre, $perPage, $offset]);
            // Verificar si se encontraron clientes
            if (empty($clientes)) {
                return response()->json(['message' => 'No se encontraron clientes.'], 404);
            }
            // Retornar los datos en formato JSON con paginación
            return response()->json([
                'data' => $clientes,
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener clientes con paginación: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    public function getCreadores()
    {
        try {
            $creadores = DB::select('SELECT * FROM sps_creador_all()');
            // Verifica si se obtuvieron resultados
            if (empty($creadores)) {
                return response()->json(['message' => 'No se encontraron creadores.'], 404);
            }
            // Retorna la lista de clientes en formato JSON
            return response()->json(['creadores' => $creadores], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener todos los creadores: ' . $e->getMessage());
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

            $perPage = $request->input('per_page');
            $page = $request->input('page');
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

    public function getPedidosEstadisticas(Request $request){
        try {
            $cliente_id = $request->input('cliente_id');
            $estado = $request->input('estado');
            
            if (!$estado) {
                return response()->json(['message' => 'El estado es requerido.'], 400);
            }

            $resultado = DB::select('SELECT * FROM sps_pedidos_clientes_estadisticas(?,?)', [$cliente_id, $estado]);

            if (empty($resultado)) {
                return response()->json(['message' => 'Estadísticas no encontradas.'], 404);
            }

            return response()->json([
                'data' => $resultado[0]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de pedidos: ' . $e->getMessage());
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

    public function messageCliente(Request $request) {
        try {
            // Obtener los datos del cliente y del usuario
            $cliente_id = $request->input('cliente_id');
            // Validar entrada
            if (!$cliente_id ) {
                return response()->json(['message' => 'Se requiere el id del cliente'], 400);
            }

            // Llamar a la función de PostgreSQL
            $resultado = DB::select('SELECT * FROM sps_cadena_mensaje_cliente(?)', [$cliente_id]);
            // Verificar el resultado
            if (!empty($resultado) && isset($resultado[0]->sps_cadena_mensaje_cliente)) {
                // Decodificar el resultado
                $retorno = explode(',', trim($resultado[0]->sps_cadena_mensaje_cliente, '{}'));
                // Determinar el código de estado
                $statusCode = $retorno[0] === '0' ? 200 : 400;
                return response()->json(['message' => $retorno[1]], $statusCode);
            } else {
                return response()->json(['error' => 'Error en la respuesta de la función de PostgreSQL'], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error al copiar mensaje para el cliente: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor', 'details' => $e->getMessage()], 500);
        }
    }

    public function updateCostoCancion(Request $request) {
        try {
            // Obtener los datos del cliente y del usuario
            $monto = $request->input('monto');
            $cancion_ids = $request->input('cancion_ids');
            $pedido_id = $request->input('pedido_id');
            // Validar entrada
            if (is_null($monto) || empty($cancion_ids) || !is_array($cancion_ids ) || !$pedido_id) {
                return response()->json(['message' => 'Se requiere el monto, id del pedido y un arreglo de IDs de canciones.'], 400);
            }
            // Convertir a formato de PostgreSQL
            $cancionIdsArray = '{' . implode(',', $cancion_ids) . '}';
            // Llamar a la función de PostgreSQL
            $resultado = DB::select('SELECT * FROM spu_costo_cancion_pedido(?,?,?)', [$monto, $cancionIdsArray, $pedido_id ]);
            // Verificar el resultado
            if (!empty($resultado) && isset($resultado[0]->spu_costo_cancion_pedido)) {
                // Decodificar el resultado
                $retorno = explode(',', trim($resultado[0]->spu_costo_cancion_pedido, '{}'));
                // Determinar el código de estado
                $statusCode = $retorno[0] === '0' ? 200 : 400;
                return response()->json(['message' => $retorno[1]], $statusCode);
            } else {
                return response()->json(['error' => 'Error en la respuesta de la función de PostgreSQL'], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error al actualizar el costo: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor', 'details' => $e->getMessage()], 500);
        }
    }

    public function updateEstadoPago(Request $request) {
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
            $resultado = DB::select('SELECT * FROM spu_cancion_pedido_estado(?, ?, ?)', [$pedido_id, $cancionIdsArray, $estado_pago]);
            // Verificar el resultado
            if (!empty($resultado) && isset($resultado[0]->spu_cancion_pedido_estado)) {
                // Decodificar el resultado
                $retorno = explode(',', trim($resultado[0]->spu_cancion_pedido_estado, '{}'));
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

    public function updateCaracteristica(Request $request) {
        try {
            // Obtener los datos del cliente y del usuario
            $p_nombre = $request->input('p_nombre');
            $p_valor = $request->input('p_valor');
            $cancion_ids = $request->input('cancion_ids');
            // Validar entrada
            if (!$p_nombre || empty($cancion_ids) || !is_array($cancion_ids) || !$p_valor) {
                return response()->json(['message' => 'Se requiere el nombre, IDs de canciones y el valor.'], 400);
            }
            // Convertir a formato de PostgreSQL
            $cancionIdsArray = '{' . implode(',', $cancion_ids) . '}';
            // Llamar a la función de PostgreSQL
            $resultado = DB::select('SELECT * FROM spu_caracteristica_valor(?, ?, ?::integer[])', [$p_nombre, $p_valor, $cancionIdsArray]);
            // Verificar el resultado
            if (!empty($resultado) && isset($resultado[0]->spu_caracteristica_valor)) {
                // Decodificar el resultado
                $retorno = explode(',', trim($resultado[0]->spu_caracteristica_valor, '{}'));
                // Determinar el código de estado
                $statusCode = $retorno[0] === '0' ? 200 : 400;
                return response()->json(['message' => $retorno[1]], $statusCode);
            } else {
                return response()->json(['error' => 'Error en la respuesta de la función de PostgreSQL'], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error al agrupar canciones: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor', 'details' => $e->getMessage()], 500);
        }
    }

    public function getCaracteristicas()
    {
        try {
            $caracteristicas = DB::select('SELECT * FROM sps_caracteristica() ORDER BY nombre');
            // Verifica si se obtuvieron resultados
            if (empty($caracteristicas)) {
                return response()->json(['message' => 'No se encontraron características.'], 404);
            }
            // Retorna la lista de clientes en formato JSON
            return response()->json(['caracteristicas' => $caracteristicas], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener las características: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    public function valorCancionC(Request $request)
    {
        try {
            $p_cancion_id = $request->input('p_cancion_id');
            $p_nombre = $request->input('p_nombre');
            // Validar entrada
            if (!$p_cancion_id || !$p_nombre) {
                return response()->json(['message' => 'Se requiere la caracteristica y id de la canción'], 400);
            }
            $valorCaracteristica = DB::select('SELECT * FROM sps_cancion_caracteristica(?, ?)', [$p_cancion_id, $p_nombre]);
            // Verifica si se obtuvieron resultados
            if (empty($valorCaracteristica)) {
                return response()->json(['message' => 'No se encontro el valor de la característica.'], 404);
            }
            // Retorna la lista de clientes en formato JSON
            return response()->json(['valorCaracteristica' => $valorCaracteristica], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener valor de la característica: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    public function updateNotificado(Request $request) {
        try {
            // Obtener los datos del cliente y del usuario
            $pedido_id = $request->input('pedido_id');
            $estado = $request->input('estado');
            // Validar entrada
            if (!$pedido_id || is_null($estado) ) {
                return response()->json(['message' => 'Se requiere el id del pedido y el estado.'], 400);
            }
            // Llamar a la función de PostgreSQL
            $resultado = DB::select('SELECT * FROM spu_pedido_notificado(?, ?)', [$pedido_id, $estado]);
            // Verificar el resultado
            if (!empty($resultado) && isset($resultado[0]->spu_pedido_notificado)) {
                // Decodificar el resultado
                $retorno = explode(',', trim($resultado[0]->spu_pedido_notificado, '{}'));
                // Determinar el código de estado
                $statusCode = $retorno[0] === '0' ? 200 : 400;
                return response()->json(['message' => $retorno[1]], $statusCode);
            } else {
                return response()->json(['error' => 'Error en la respuesta de la función de PostgreSQL'], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error al actualizar estado: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor', 'details' => $e->getMessage()], 500);
        }
    }

    public function getCancionesFiltro(Request $request)
    {
        try {
            // Obtenemos los parámetros desde el request
            $filtros = [
                $request->input('cliente', ''),
                $request->input('creador', ''),
                $request->input('estado', ''),
                $request->input('caracteristica', ''),
                $request->input('semana', ''),
                $request->input('nombre', ''),
                $request->input('genero', ''),
                $request->input('ritmo', ''),
                $request->input('otro', ''),
                $request->input('limite', ''),
                $request->input('fecha_inicio', ''),
                $request->input('fecha_fin', ''),
                $request->input('ids', '')
            ];
            // Convertimos el arreglo a formato de array PostgreSQL
            $filtros_pg = '{' . implode(',', array_map(function ($item) {
                return '"' . addslashes($item) . '"';
            }, $filtros)) . '}';
            // Parámetros de paginación
            $perPage = $request->input('per_page'); 
            $page = $request->input('page');
            $offset = ($page - 1) * $perPage;
            // Ejecutamos la función SQL con el array de filtros
            $resultado = DB::select("SELECT * FROM sps_cancion_filtro_2(?) LIMIT ? OFFSET ?", [
                $filtros_pg,
                $perPage, 
                $offset   
            ]);
            // Contar el total de canciones sin filtros para la paginación
            $total = DB::selectOne("SELECT COUNT(*) AS count FROM sps_cancion_filtro_2(?)", [$filtros_pg])->count;
            // Validamos si hay resultados
            if (empty($resultado)) {
                return response()->json(['message' => 'No se encontraron canciones con los filtros aplicados.'], 404);
            }
            return response()->json([
                'data' => $resultado,
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => ceil($total / $perPage)
            ]);
        } catch (\Exception $e) {
            // Logueamos el error para depuración
            Log::error('Error al obtener canciones filtradas: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    public function crudCliente(Request $request)
    {
        try {
            // Obtener los datos del cliente y la opción
            $p_adatos = $request->input('p_adatos');
            $p_opc = $request->input('p_opc');
            // Validar entrada
            if (!is_array($p_adatos) || !is_int($p_opc)) {
                return response()->json(['message' => 'Se requieren los datos del cliente como array y la opción como entero.'], 400);
            }
            if (count($p_adatos) < 5) {
                return response()->json(['message' => 'El array de datos del cliente debe contener al menos 5 elementos.'], 400);
            }
            // Convertir el array a formato PostgreSQL
            $p_adatos_pgsql = '{' . implode(',', array_map(function ($item) {
                return $item === null ? 'NULL' : '"' . str_replace('"', '\"', $item) . '"';
            }, $p_adatos)) . '}';
            // Llamar a la función de PostgreSQL
            $resultado = DB::select('SELECT * FROM public.crud_cliente(?, ?)', [$p_adatos_pgsql, $p_opc]);
            // Verificar el resultado
            if (!empty($resultado)) {
                $retorno = (array) $resultado[0]; // Convertir a array asociativo
                $resArray = explode(',', trim(reset($retorno), '{}')); // Obtener y procesar el resultado

                $statusCode = $resArray[0] === '0' ? 200 : 400; // Determinar código HTTP
                return response()->json(['message' => $resArray[1]], $statusCode);
            }
            return response()->json(['message' => 'Error inesperado al ejecutar la función de PostgreSQL.'], 500);
        } catch (\Exception $e) {
            // Registrar el error
            Log::error('Error en crudCliente: ' . $e->getMessage(), [
                'p_adatos' => $p_adatos ?? null,
                'p_opc' => $p_opc ?? null
            ]);
            return response()->json(['error' => 'Error interno del servidor', 'details' => $e->getMessage()], 500);
        }
    }

    public function updateClaveCliente(Request $request) {
        try {
            // Obtener los datos del cliente y del usuario
            $cliente_id = $request->input('cliente_id');
            // Validar entrada
            if (!$cliente_id) {
                return response()->json(['message' => 'Se requiere el id del cliente'], 400);
            }
            // Llamar a la función de PostgreSQL
            $resultado = DB::select('SELECT * FROM spu_clave_cliente(?)', [$cliente_id]);
            // Verificar el resultado
            if (!empty($resultado) && isset($resultado[0]->spu_clave_cliente)) {
                // Decodificar el resultado
                $retorno = explode(',', trim($resultado[0]->spu_clave_cliente, '{}'));
                // Determinar el código de estado
                $statusCode = $retorno[0] === '0' ? 200 : 400;
                return response()->json(['message' => $retorno[1]], $statusCode);
            } else {
                return response()->json(['error' => 'Error en la respuesta de la función de PostgreSQL'], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error al actualizar clave del cliente: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor', 'details' => $e->getMessage()], 500);
        }
    }

    // -----------PAGOS----------
    public function getPagosCreadores(Request $request){
        try {
            $usuario_id = $request->input('usuario_id');
            $estado = $request->input('estado');
            
            if (!$estado) {
                return response()->json(['message' => 'El estado es requerido.'], 400);
            }

            $perPage = $request->input('per_page');
            $page = $request->input('page');
            $offset = ($page - 1) * $perPage;

            $total = DB::selectOne('SELECT COUNT(*) AS count FROM sps_pagos_creadores(?,?) AS c', [$usuario_id, $estado])->count;
            $pagos = DB::select('SELECT * FROM sps_pagos_creadores(?,?) AS c LIMIT ? OFFSET ?', [$usuario_id, $estado, $perPage, $offset]);
            foreach ($pagos as &$pago) {
                $pago->canciones = json_decode($pago->canciones);
            }

            if (empty($pagos)) {
                return response()->json(['message' => 'Pagos no encontrados.'], 404);
            }

            return response()->json([
                'data' => $pagos,
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener los pagos del creador: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    public function updateCancionPago(Request $request) {
        try {
            // Obtener los datos del cliente y del usuario
            $pago_id = $request->input('pago_id');
            $cancion_ids = $request->input('cancion_ids');
            $estado_pago = $request->input('estado_pago');
            // Validar entrada
            if (!$pago_id || empty($cancion_ids) || !is_array($cancion_ids) || is_null($estado_pago)) {
                return response()->json(['message' => 'Se requiere el id del pago, IDs de canciones y estado de pago.'], 400);
            }
            // Convertir a formato de PostgreSQL
            $cancionIdsArray = '{' . implode(',', $cancion_ids) . '}';
            // Llamar a la función de PostgreSQL
            $resultado = DB::select('SELECT * FROM spu_cancion_pago_estado(?, ?, ?)', [$pago_id, $cancionIdsArray, $estado_pago]);
            // Verificar el resultado
            if (!empty($resultado) && isset($resultado[0]->spu_cancion_pago_estado)) {
                // Decodificar el resultado
                $retorno = explode(',', trim($resultado[0]->spu_cancion_pago_estado, '{}'));
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

    public function updateCostoCancionPago(Request $request) {
        try {
            $monto = $request->input('monto');
            $cancion_ids = $request->input('cancion_ids');
            $pago_id = $request->input('pago_id');
            // Validar entrada
            if (is_null($monto) || empty($cancion_ids) || !is_array($cancion_ids ) || !$pago_id) {
                return response()->json(['message' => 'Se requiere el monto, id del pago y un arreglo de IDs de canciones.'], 400);
            }
            // Convertir a formato de PostgreSQL
            $cancionIdsArray = '{' . implode(',', $cancion_ids) . '}';
            // Llamar a la función de PostgreSQL
            $resultado = DB::select('SELECT * FROM spu_costo_cancion_pago(?,?,?)', [$monto, $cancionIdsArray, $pago_id ]);
            // Verificar el resultado
            if (!empty($resultado) && isset($resultado[0]->spu_costo_cancion_pago)) {
                // Decodificar el resultado
                $retorno = explode(',', trim($resultado[0]->spu_costo_cancion_pago, '{}'));
                // Determinar el código de estado
                $statusCode = $retorno[0] === '0' ? 200 : 400;
                return response()->json(['message' => $retorno[1]], $statusCode);
            } else {
                return response()->json(['error' => 'Error en la respuesta de la función de PostgreSQL'], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error al actualizar el costo: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor', 'details' => $e->getMessage()], 500);
        }
    }

    public function sendPago(Request $request){
        try {
            // Recibir el arreglo de IDs de canciones del request
            $usuario_id = $request->input('usuario_id');
            $folio = $request->input('folio');
            // Validar que el parámetro no esté vacío
            if (!$usuario_id || !$folio ) {
                return response()->json(['message' => 'Se requiere el creador y el folio'], 400);
            }
            // Ejecutar la función almacenada en PostgreSQL y obtener los resultados
            $pago = DB::select('SELECT * FROM sps_pago_cadena(?,?)', [$usuario_id, $folio]);
            // Verificar si no hay resultados
            if (empty($pago)) {
                return response()->json(['message' => 'No se pudo obtener el pago.'], 404);
            }
            // Retornar el resultado en formato JSON
            return response()->json(['pago' => $pago], 200);
        } catch (\Exception $e) {
            // Registrar el error en el log y devolver una respuesta de error
            Log::error('Error al obtener pago: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    public function closePago(Request $request){
        try {
            $usuario_id = $request->input('usuario_id');
            // Validar que los parámetros requeridos no sean nulos
            if (!$usuario_id) {
                return response()->json(['message' => 'El id del creador es requerido.'], 400);
            }
            // Llamar a la función de PostgreSQL para insertar el pedido
            $resultado = DB::select('SELECT * FROM spi_cerrar_pago(?)', [$usuario_id]);
            // Asegurarnos de que el resultado no esté vacío antes de acceder a él
            if (!empty($resultado) && isset($resultado[0]->spi_cerrar_pago)) {
                // Decodificar el resultado de PostgreSQL
                $retorno = explode(',', trim($resultado[0]->spi_cerrar_pago, '{}'));
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
            Log::error('Error al cerrar pago: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor', 'details' => $e->getMessage()], 500);
        }
    }

    public function openPago(Request $request){
        try {
            $usuario_id = $request->input('usuario_id');
            $pago_id = $request->input('pago_id');
            // Validar que los parámetros requeridos no sean nulos
            if (!$usuario_id || !$pago_id) {
                return response()->json(['message' => 'El id de usuario y pago es requerido'], 400);
            }
            // Llamar a la función de PostgreSQL para insertar el pedido
            $resultado = DB::select('SELECT * FROM spi_abrir_pago(?,?)', [$usuario_id, $pago_id]);
            // Asegurarnos de que el resultado no esté vacío antes de acceder a él
            if (!empty($resultado) && isset($resultado[0]->spi_abrir_pago)) {
                // Decodificar el resultado de PostgreSQL
                $retorno = explode(',', trim($resultado[0]->spi_abrir_pago, '{}'));
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
            Log::error('Error al abrir pago: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor', 'details' => $e->getMessage()], 500);
        }
    }

    public function eliminarPago(Request $request)
    {
        try {
            // Obtener los datos del cliente y del usuario
            $pago_id = $request->input('pago_id');
            $usuario_id = $request->input('usuario_id');
            // Validar que los parámetros requeridos no sean nulos
            if (is_null($pago_id) || is_null($usuario_id)) {
                return response()->json(['message' => 'El id de pago y usuario son requeridos.'], 400);
            }
            // Crear la cadena del array en el formato que PostgreSQL espera
            $datos = sprintf('{"%s", "%s"}', $pago_id, $usuario_id);
            $operacion = 3;
            $resultado = DB::select('SELECT * FROM crud_pago(?, ?)', [$datos, $operacion]);
            // Asegurarnos de que el resultado no esté vacío antes de acceder a él
            if (!empty($resultado) && isset($resultado[0]->crud_pago)) {
                // Decodificar el resultado de PostgreSQL
                $retorno = explode(',', trim($resultado[0]->crud_pago, '{}'));
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
            Log::error('Error al eliminar pago: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor', 'details' => $e->getMessage()], 500);
        }
    }

    public function deleteCancionPago(Request $request) {
        try {
            $pago_id = $request->input('pago_id');
            $cancion_ids = $request->input('cancion_ids');
            // Validar entrada
            if (!$pago_id || empty($cancion_ids) || !is_array($cancion_ids)) {
                return response()->json(['message' => 'Se requiere el id del pago y un arreglo de IDs de canciones.'], 400);
            }
            // Convertir a formato de PostgreSQL
            $cancionIdsArray = '{' . implode(',', $cancion_ids) . '}';
            // Llamar a la función de PostgreSQL
            $resultado = DB::select('SELECT * FROM spd_cancion_pago(?, ?)', [$pago_id, $cancionIdsArray]);
            // Verificar el resultado
            if (!empty($resultado) && isset($resultado[0]->spd_cancion_pago)) {
                // Decodificar el resultado
                $retorno = explode(',', trim($resultado[0]->spd_cancion_pago, '{}'));
                // Determinar el código de estado
                $statusCode = $retorno[0] === '0' ? 200 : 400;
                return response()->json(['message' => $retorno[1]], $statusCode);
            } else {
                return response()->json(['error' => 'Error en la respuesta de la función de PostgreSQL'], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error al eliminar cancion(es) del pago: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor', 'details' => $e->getMessage()], 500);
        }
    }

}
