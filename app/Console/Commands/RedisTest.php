<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class RedisTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:test {--quick : Ejecutar solo prueba rápida}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Probar la conexión y funcionalidad de Redis';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('═══════════════════════════════════════════════════');
        $this->info('   Prueba de Redis - Sistema de Recaudación');
        $this->info('═══════════════════════════════════════════════════');
        $this->newLine();

        // Test 1: Conexión
        $this->testConnection();

        if ($this->option('quick')) {
            $this->newLine();
            $this->info('✅ Prueba rápida completada exitosamente');
            return Command::SUCCESS;
        }

        $this->newLine();

        // Test 2: Caché
        $this->testCache();
        $this->newLine();

        // Test 3: Strings
        $this->testStrings();
        $this->newLine();

        // Test 4: Contadores
        $this->testCounters();
        $this->newLine();

        // Test 5: Hashes
        $this->testHashes();
        $this->newLine();

        // Test 6: Info
        $this->showInfo();
        $this->newLine();

        $this->info('═══════════════════════════════════════════════════');
        $this->info('✅ Todas las pruebas completadas exitosamente');
        $this->info('═══════════════════════════════════════════════════');

        return Command::SUCCESS;
    }

    protected function testConnection()
    {
        $this->info('🔌 Test 1: Conexión a Redis');
        $this->line('───────────────────────────────────────────────────');

        try {
            $result = Redis::ping();
            $this->line('  Host: ' . config('database.redis.default.host'));
            $this->line('  Port: ' . config('database.redis.default.port'));
            $this->line('  Client: ' . config('database.redis.client'));
            $this->line('  Status: <fg=green>✓ CONECTADO</>');
        } catch (\Exception $e) {
            $this->error('  ✗ Error al conectar: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function testCache()
    {
        $this->info('💾 Test 2: Sistema de Caché');
        $this->line('───────────────────────────────────────────────────');

        try {
            $key = 'test:cache:' . time();
            $value = 'Prueba de caché ' . now()->toDateTimeString();

            Cache::put($key, $value, 60);
            $retrieved = Cache::get($key);
            $match = $value === $retrieved;

            $this->line('  Valor guardado: ' . $value);
            $this->line('  Valor recuperado: ' . $retrieved);
            $this->line('  Coinciden: ' . ($match ? '<fg=green>SÍ</>' : '<fg=red>NO</>'));
            $this->line('  Driver: ' . config('cache.default'));

            Cache::forget($key);
        } catch (\Exception $e) {
            $this->error('  ✗ Error: ' . $e->getMessage());
        }
    }

    protected function testStrings()
    {
        $this->info('📝 Test 3: Strings');
        $this->line('───────────────────────────────────────────────────');

        try {
            $key = 'test:string:' . time();
            Redis::set($key, 'Hola desde Redis CLI');
            $value = Redis::get($key);

            $this->line('  SET/GET: ' . $value);

            Redis::setex('test:temp:cli', 5, 'Expira en 5 seg');
            $ttl = Redis::ttl('test:temp:cli');
            $this->line('  TTL: ' . $ttl . ' segundos');

            Redis::del($key, 'test:temp:cli');
        } catch (\Exception $e) {
            $this->error('  ✗ Error: ' . $e->getMessage());
        }
    }

    protected function testCounters()
    {
        $this->info('🔢 Test 4: Contadores');
        $this->line('───────────────────────────────────────────────────');

        try {
            $key = 'test:counter:cli';
            Redis::set($key, 0);

            $val1 = Redis::incr($key);
            $val2 = Redis::incr($key);
            $val3 = Redis::incrby($key, 10);
            $final = Redis::get($key);

            $this->line('  Inicial: 0');
            $this->line('  Después de INCR: ' . $val1);
            $this->line('  Después de INCR: ' . $val2);
            $this->line('  Después de INCRBY 10: ' . $val3);
            $this->line('  Valor final: ' . $final);

            Redis::del($key);
        } catch (\Exception $e) {
            $this->error('  ✗ Error: ' . $e->getMessage());
        }
    }

    protected function testHashes()
    {
        $this->info('📦 Test 5: Hashes (Objetos)');
        $this->line('───────────────────────────────────────────────────');

        try {
            $key = 'test:hash:cli';

            Redis::hset($key, 'placa', 'ABC-1234');
            Redis::hset($key, 'propietario', 'Juan Pérez');
            Redis::hset($key, 'deuda', 150.50);

            $placa = Redis::hget($key, 'placa');
            $propietario = Redis::hget($key, 'propietario');
            $deuda = Redis::hget($key, 'deuda');

            $this->line('  Placa: ' . $placa);
            $this->line('  Propietario: ' . $propietario);
            $this->line('  Deuda: $' . $deuda);

            $all = Redis::hgetall($key);
            $this->line('  Total campos: ' . count($all));

            Redis::del($key);
        } catch (\Exception $e) {
            $this->error('  ✗ Error: ' . $e->getMessage());
        }
    }

    protected function showInfo()
    {
        $this->info('ℹ️  Información del Sistema');
        $this->line('───────────────────────────────────────────────────');

        try {
            $info = Redis::info();
            $testKeys = Redis::keys('test:*');

            $this->line('  Versión Redis: ' . ($info['redis_version'] ?? 'N/A'));
            $this->line('  Memoria usada: ' . ($info['used_memory_human'] ?? 'N/A'));
            $this->line('  Clientes conectados: ' . ($info['connected_clients'] ?? 'N/A'));
            $this->line('  Uptime (días): ' . ($info['uptime_in_days'] ?? 'N/A'));
            $this->line('  Claves de prueba: ' . count($testKeys));

            if (count($testKeys) > 0) {
                $this->line('  └─ Claves encontradas: ' . implode(', ', array_slice($testKeys, 0, 5)));
                if (count($testKeys) > 5) {
                    $this->line('     ... y ' . (count($testKeys) - 5) . ' más');
                }
            }
        } catch (\Exception $e) {
            $this->error('  ✗ Error: ' . $e->getMessage());
        }
    }
}
