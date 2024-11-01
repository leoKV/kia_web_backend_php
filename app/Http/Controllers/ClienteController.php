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

}
