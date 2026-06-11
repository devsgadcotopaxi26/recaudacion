<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Listar todos los usuarios
     */
    public function index()
    {
        $users = User::with('roles')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($user) {
                return [
                    'id'         => $user->id,
                    'name'       => $user->name,
                    'email'      => $user->email,
                    'role'       => $user->roles->first()?->name ?? 'sin rol',
                    'created_at' => $user->created_at->format('d/m/Y H:i'),
                ];
            });

        $roles = Role::all()->map(fn($r) => ['id' => $r->id, 'name' => $r->name]);

        return Inertia::render('Admin/Usuarios', [
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    /**
     * Crear nuevo usuario
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => ['required', Password::min(8)->letters()->numbers()],
            'role'     => 'required|string|exists:roles,name',
        ], [
            'name.required'     => 'El nombre es obligatorio.',
            'email.required'    => 'El correo es obligatorio.',
            'email.unique'      => 'Ya existe un usuario con ese correo.',
            'password.required' => 'La contraseña es obligatoria.',
            'role.required'     => 'Debe seleccionar un rol.',
            'role.exists'       => 'El rol seleccionado no es válido.',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => $request->role,
        ]);

        $user->assignRole($request->role);

        return redirect()->route('admin.usuarios.index')
            ->with('success', "Usuario '{$user->name}' creado exitosamente.");
    }

    /**
     * Actualizar usuario existente
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email,' . $user->id,
            'password' => ['nullable', Password::min(8)->letters()->numbers()],
            'role'     => 'required|string|exists:roles,name',
        ], [
            'name.required'  => 'El nombre es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.unique'   => 'Ya existe un usuario con ese correo.',
            'role.required'  => 'Debe seleccionar un rol.',
        ]);

        $user->update([
            'name'  => $request->name,
            'email' => $request->email,
            'role'  => $request->role,
        ]);

        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        // Sincronizar rol
        $user->syncRoles([$request->role]);

        return redirect()->route('admin.usuarios.index')
            ->with('success', "Usuario '{$user->name}' actualizado correctamente.");
    }

    /**
     * Eliminar usuario
     */
    public function destroy(User $user)
    {
        // Evitar que el admin se elimine a sí mismo
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.usuarios.index')
                ->with('error', 'No puedes eliminar tu propio usuario.');
        }

        $nombre = $user->name;
        $user->delete();

        return redirect()->route('admin.usuarios.index')
            ->with('success', "Usuario '{$nombre}' eliminado correctamente.");
    }
}
