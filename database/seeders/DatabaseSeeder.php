<?php

namespace Database\Seeders;

use App\Models\Vehiculo;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Crear vehículos de prueba
        $vehiculos = [
            [
                'placa' => 'ABC1234',
                'cedula_propietario' => '1234567890',
                'propietario' => 'Juan Pérez González',
                'marca' => 'Toyota',
                'modelo' => 'Corolla',
                'anio' => 2020,
                'avaluo' => 15000.00,
                'tipo_vehiculo' => 'automovil',
            ],
            [
                'placa' => 'XYZ5678',
                'cedula_propietario' => '0987654321',
                'propietario' => 'María Rodríguez López',
                'marca' => 'Chevrolet',
                'modelo' => 'Aveo',
                'anio' => 2018,
                'avaluo' => 8500.00,
                'tipo_vehiculo' => 'automovil',
            ],
            [
                'placa' => 'DEF9012',
                'cedula_propietario' => '1122334455',
                'propietario' => 'Carlos Sánchez Mora',
                'marca' => 'Mazda',
                'modelo' => 'CX-5',
                'anio' => 2022,
                'avaluo' => 28000.00,
                'tipo_vehiculo' => 'camioneta',
            ],
            [
                'placa' => 'GHI3456',
                'cedula_propietario' => '5544332211',
                'propietario' => 'Ana Martínez Flores',
                'marca' => 'Honda',
                'modelo' => 'Civic',
                'anio' => 2019,
                'avaluo' => 12000.00,
                'tipo_vehiculo' => 'automovil',
            ],
            [
                'placa' => 'JKL7890',
                'cedula_propietario' => '9988776655',
                'propietario' => 'Luis Gómez Vargas',
                'marca' => 'Suzuki',
                'modelo' => 'Swift',
                'anio' => 2021,
                'avaluo' => 11500.00,
                'tipo_vehiculo' => 'automovil',
            ],
        ];

        foreach ($vehiculos as $vehiculo) {
            Vehiculo::create($vehiculo);
        }

        $this->command->info('✅ Vehículos de prueba creados exitosamente');
        $this->command->info('');
        $this->command->info('Datos de prueba para consultar:');
        $this->command->info('Placa: ABC1234 | Cédula: 1234567890');
        $this->command->info('Placa: XYZ5678 | Cédula: 0987654321');
        $this->command->info('Placa: DEF9012 | Cédula: 1122334455');
    }
}
