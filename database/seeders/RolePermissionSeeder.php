<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Resetear cache de roles y permisos
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Crear permisos
        Permission::create(['name' => 'ver-dashboard']);
        Permission::create(['name' => 'ver-logs']);
        Permission::create(['name' => 'verificar-pagos']);

        // Crear rol Admin y asignar todos los permisos
        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo([
            'ver-dashboard',
            'ver-logs',
            'verificar-pagos'
        ]);

        // Crear rol VerificacionPagos y asignar solo permiso de verificar pagos
        $verificadorRole = Role::create(['name' => 'verificacionpagos']);
        $verificadorRole->givePermissionTo('verificar-pagos');

        $this->command->info('✅ Roles y permisos creados exitosamente');
        $this->command->info('');
        $this->command->info('Roles creados:');
        $this->command->info('  - admin (permisos: ver-dashboard, ver-logs, verificar-pagos)');
        $this->command->info('  - verificacionpagos (permisos: verificar-pagos)');
    }
}
