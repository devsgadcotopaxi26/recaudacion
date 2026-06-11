<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Eliminar usuarios existentes si existen
        User::where('email', 'admin@recaudacion.gob.ec')->delete();
        User::where('email', 'verificador@recaudacion.gob.ec')->delete();

        // Crear usuario administrador
        $admin = User::create([
            'name' => 'Administrador',
            'email' => 'admin@recaudacion.gob.ec',
            'password' => Hash::make('admin123'),
            'role' => 'admin', // Mantenemos por compatibilidad
        ]);
        $admin->assignRole('admin');

        // Crear usuario verificador de pagos
        $verificador = User::create([
            'name' => 'Verificador de Pagos',
            'email' => 'verificador@recaudacion.gob.ec',
            'password' => Hash::make('verificador123'),
            'role' => 'verificacionpagos', // Mantenemos por compatibilidad
        ]);
        $verificador->assignRole('verificacionpagos');

        $this->command->info('✅ Usuarios creados exitosamente');
        $this->command->info('');
        $this->command->info('👤 Usuario Admin:');
        $this->command->info('   Email: admin@recaudacion.gob.ec');
        $this->command->info('   Password: admin123');
        $this->command->info('   Rol: admin');
        $this->command->info('');
        $this->command->info('👤 Usuario Verificador:');
        $this->command->info('   Email: verificador@recaudacion.gob.ec');
        $this->command->info('   Password: verificador123');
        $this->command->info('   Rol: verificacionpagos');
    }
}
