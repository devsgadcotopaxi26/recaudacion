<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class RedisTestController extends Controller
{
    /**
     * Panel de pruebas de Redis
     */
    public function index()
    {
        return response()->json([
            'message' => 'Redis Test Panel',
            'endpoints' => [
                'GET /redis-test/ping' => 'Verificar conexión básica',
                'GET /redis-test/cache' => 'Probar sistema de caché',
                'GET /redis-test/strings' => 'Probar strings',
                'GET /redis-test/counters' => 'Probar contadores',
                'GET /redis-test/lists' => 'Probar listas',
                'GET /redis-test/sets' => 'Probar sets',
                'GET /redis-test/hashes' => 'Probar hashes',
                'GET /redis-test/ttl' => 'Probar expiración (TTL)',
                'GET /redis-test/info' => 'Información de Redis',
                'GET /redis-test/clear' => 'Limpiar datos de prueba',
            ]
        ]);
    }

    /**
     * Test 1: Ping básico
     */
    public function ping()
    {
        try {
            $result = Redis::ping();
            return response()->json([
                'status' => 'success',
                'message' => 'Redis está funcionando correctamente',
                'ping_response' => $result,
                'timestamp' => now()->toDateTimeString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con Redis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test 2: Sistema de Caché
     */
    public function testCache()
    {
        try {
            $key = 'test:cache:' . time();
            $value = 'Este es un valor de prueba en caché';

            // Guardar en caché por 60 segundos
            Cache::put($key, $value, 60);

            // Recuperar del caché
            $retrieved = Cache::get($key);

            // Verificar si existe
            $exists = Cache::has($key);

            // Obtener TTL
            $ttl = Redis::ttl(config('cache.prefix') . ':' . $key);

            return response()->json([
                'status' => 'success',
                'test' => 'Cache',
                'actions' => [
                    'stored' => $value,
                    'retrieved' => $retrieved,
                    'exists' => $exists,
                    'ttl_seconds' => $ttl,
                    'match' => $value === $retrieved
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test 3: Strings
     */
    public function testStrings()
    {
        try {
            $key = 'test:string:' . time();

            // SET y GET
            Redis::set($key, 'Hola desde Redis');
            $value = Redis::get($key);

            // SETEX (con expiración)
            Redis::setex('test:temp', 5, 'Este valor expira en 5 segundos');

            // Verificar expiración
            $tempValue = Redis::get('test:temp');
            $ttl = Redis::ttl('test:temp');

            return response()->json([
                'status' => 'success',
                'test' => 'Strings',
                'results' => [
                    'basic_value' => $value,
                    'temp_value' => $tempValue,
                    'temp_ttl' => $ttl . ' segundos'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test 4: Contadores
     */
    public function testCounters()
    {
        try {
            $key = 'test:counter:visits';

            // Resetear contador
            Redis::set($key, 0);

            // Incrementar
            $val1 = Redis::incr($key);
            $val2 = Redis::incr($key);
            $val3 = Redis::incrby($key, 5);

            // Decrementar
            $val4 = Redis::decr($key);

            $final = Redis::get($key);

            return response()->json([
                'status' => 'success',
                'test' => 'Contadores',
                'operations' => [
                    'initial' => 0,
                    'after_incr_1' => $val1,
                    'after_incr_2' => $val2,
                    'after_incrby_5' => $val3,
                    'after_decr_1' => $val4,
                    'final_value' => (int) $final
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test 5: Listas
     */
    public function testLists()
    {
        try {
            $key = 'test:list:notifications';

            // Limpiar lista anterior
            Redis::del($key);

            // Agregar elementos (push)
            Redis::lpush($key, 'Notificación 1');
            Redis::lpush($key, 'Notificación 2');
            Redis::rpush($key, 'Notificación 3');

            // Obtener longitud
            $length = Redis::llen($key);

            // Obtener todos los elementos
            $all = Redis::lrange($key, 0, -1);

            // Pop element
            $popped = Redis::rpop($key);

            $remaining = Redis::lrange($key, 0, -1);

            return response()->json([
                'status' => 'success',
                'test' => 'Listas',
                'results' => [
                    'length' => $length,
                    'all_items' => $all,
                    'popped_item' => $popped,
                    'remaining_items' => $remaining
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test 6: Sets
     */
    public function testSets()
    {
        try {
            $key = 'test:set:tags';

            // Limpiar set anterior
            Redis::del($key);

            // Agregar elementos
            Redis::sadd($key, 'php', 'laravel', 'redis', 'vue');
            Redis::sadd($key, 'php'); // Duplicado, no se agregará

            // Obtener todos los miembros
            $members = Redis::smembers($key);

            // Verificar si existe
            $hasLaravel = Redis::sismember($key, 'laravel');
            $hasJava = Redis::sismember($key, 'java');

            // Cantidad de elementos
            $count = Redis::scard($key);

            return response()->json([
                'status' => 'success',
                'test' => 'Sets',
                'results' => [
                    'members' => $members,
                    'count' => $count,
                    'has_laravel' => (bool) $hasLaravel,
                    'has_java' => (bool) $hasJava
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test 7: Hashes (ideal para objetos)
     */
    public function testHashes()
    {
        try {
            $key = 'test:hash:user:1000';

            // Limpiar hash anterior
            Redis::del($key);

            // Establecer múltiples campos
            Redis::hset($key, 'nombre', 'Juan Pérez');
            Redis::hset($key, 'email', 'juan@example.com');
            Redis::hset($key, 'edad', 30);
            Redis::hset($key, 'ciudad', 'Quito');

            // Obtener un campo
            $nombre = Redis::hget($key, 'nombre');

            // Obtener todos los campos
            $user = Redis::hgetall($key);

            // Incrementar campo numérico
            Redis::hincrby($key, 'edad', 1);
            $newAge = Redis::hget($key, 'edad');

            return response()->json([
                'status' => 'success',
                'test' => 'Hashes',
                'results' => [
                    'user_name' => $nombre,
                    'full_user' => $user,
                    'age_after_increment' => (int) $newAge
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test 8: TTL (Time To Live)
     */
    public function testTTL()
    {
        try {
            $key = 'test:ttl:temporal';

            // Establecer con 10 segundos de vida
            Redis::setex($key, 10, 'Este mensaje se autodestruirá en 10 segundos');

            // Verificar TTL
            $ttl = Redis::ttl($key);

            // Establecer TTL en clave existente
            Redis::set('test:ttl:permanent', 'Valor permanente');
            Redis::expire('test:ttl:permanent', 30);
            $ttlPermanent = Redis::ttl('test:ttl:permanent');

            return response()->json([
                'status' => 'success',
                'test' => 'TTL (Expiración)',
                'results' => [
                    'temporal_key' => [
                        'value' => Redis::get($key),
                        'ttl_seconds' => $ttl,
                        'message' => 'Esta clave expirará en ' . $ttl . ' segundos'
                    ],
                    'permanent_key' => [
                        'value' => Redis::get('test:ttl:permanent'),
                        'ttl_seconds' => $ttlPermanent,
                        'message' => 'Esta clave expirará en ' . $ttlPermanent . ' segundos'
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Información de Redis
     */
    public function info()
    {
        try {
            // Obtener información del servidor
            $info = Redis::info();

            // Contar claves de prueba
            $testKeys = Redis::keys('test:*');

            return response()->json([
                'status' => 'success',
                'redis_info' => [
                    'version' => $info['redis_version'] ?? 'N/A',
                    'uptime_days' => isset($info['uptime_in_days']) ? $info['uptime_in_days'] : 'N/A',
                    'connected_clients' => $info['connected_clients'] ?? 'N/A',
                    'used_memory_human' => $info['used_memory_human'] ?? 'N/A',
                    'total_keys' => count($testKeys),
                ],
                'test_keys' => $testKeys,
                'cache_driver' => config('cache.default'),
                'redis_client' => config('database.redis.client')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Limpiar datos de prueba
     */
    public function clear()
    {
        try {
            $keys = Redis::keys('test:*');
            $count = count($keys);

            if ($count > 0) {
                Redis::del(...$keys);
            }

            return response()->json([
                'status' => 'success',
                'message' => "Se eliminaron $count claves de prueba",
                'deleted_keys' => $keys
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
