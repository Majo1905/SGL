<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <span class="text-xs font-bold text-[#006837] bg-emerald-50 px-2.5 py-1 rounded-md border border-emerald-200">Administración</span>
                <h2 class="font-extrabold text-2xl text-slate-900 tracking-tight mt-1">Usuarios del sistema</h2>
            </div>
            <a href="{{ route('usuarios.create') }}" class="text-xs font-semibold bg-[#006837] hover:bg-[#00522b] text-white px-3.5 py-2 rounded-xl shadow-sm transition-all">
                + Nuevo usuario
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('mensaje'))
                <div role="status" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('mensaje') }}</div>
            @endif
            <div class="bg-white shadow-sm sm:rounded-2xl border border-slate-200 p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-bold text-slate-400 uppercase tracking-wider">
                            <th class="px-4 py-3">Nombre</th>
                            <th class="px-4 py-3">Documento</th>
                            <th class="px-4 py-3">Correo</th>
                            <th class="px-4 py-3">Rol</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($usuarios as $usuario)
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-800">{{ $usuario->name }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $usuario->documento_identidad ?: '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $usuario->email }}</td>
                                <td class="px-4 py-3">{{ $usuario->nombre_rol }}</td>
                                <td class="px-4 py-3">{{ $usuario->activo ? 'Activo' : 'Inactivo' }}</td>
                                <td class="px-4 py-3">
                                    @if (!auth()->user()->is($usuario))
                                        <form method="POST" action="{{ route('usuarios.estado', $usuario) }}">
                                            @csrf @method('PATCH')
                                            <button class="font-semibold {{ $usuario->activo ? 'text-red-600' : 'text-[#006837]' }} hover:underline">
                                                {{ $usuario->activo ? 'Desactivar' : 'Activar' }}
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-slate-400">Tu cuenta</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
