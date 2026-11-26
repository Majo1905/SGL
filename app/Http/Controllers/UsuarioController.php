<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

// gestión de usuarios (CU-02): solo el administrador
class UsuarioController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('rol:ADMIN')];
    }

    public function index()
    {
        return view('usuarios.index', ['usuarios' => User::orderBy('name')->get()]);
    }

    public function create()
    {
        return view('usuarios.create');
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'rol' => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'email.unique' => 'Ya existe un usuario con ese correo.',
            'rol.in' => 'El rol seleccionado no es válido.',
        ]);

        $usuario = User::create([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'rol' => $datos['rol'],
            'password' => Hash::make($datos['password']),
        ]);

        return redirect()->route('usuarios.index')
            ->with('mensaje', "Usuario {$usuario->name} registrado como {$usuario->nombre_rol}.");
    }
}
