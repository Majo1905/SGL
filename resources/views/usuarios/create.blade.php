<x-app-layout>
    <x-slot name="header">
        <span class="text-xs font-bold text-[#006837] bg-emerald-50 px-2.5 py-1 rounded-md border border-emerald-200">Administración</span>
        <h2 class="font-extrabold text-2xl text-slate-900 tracking-tight mt-1">Registrar usuario</h2>
    </x-slot>

    @php
        $campo = 'mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-[#006837] focus:ring-[#006837]';
    @endphp

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-2xl border border-slate-200 p-8">
                @if ($errors->any())
                    <div class="mb-6 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('usuarios.store') }}" class="space-y-5">
                    @csrf
                    <div>
                        <label for="name" class="block text-sm font-semibold text-slate-700">Nombre completo</label>
                        <input type="text" name="name" id="name" class="{{ $campo }}" value="{{ old('name') }}" required>
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-semibold text-slate-700">Correo electrónico</label>
                        <input type="email" name="email" id="email" class="{{ $campo }}" value="{{ old('email') }}" required>
                    </div>
                    <div>
                        <label for="rol" class="block text-sm font-semibold text-slate-700">Rol</label>
                        <select name="rol" id="rol" class="{{ $campo }}" required>
                            @foreach (App\Models\User::ROLES as $valor => $etiqueta)
                                <option value="{{ $valor }}" @selected(old('rol', 'ESTUDIANTE') == $valor)>{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label for="password" class="block text-sm font-semibold text-slate-700">Contraseña temporal</label>
                            <input type="password" name="password" id="password" class="{{ $campo }}" required>
                        </div>
                        <div>
                            <label for="password_confirmation" class="block text-sm font-semibold text-slate-700">Confirmar contraseña</label>
                            <input type="password" name="password_confirmation" id="password_confirmation" class="{{ $campo }}" required>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="submit" class="bg-[#006837] hover:bg-[#00522b] text-white text-sm font-semibold px-5 py-2.5 rounded-xl shadow-sm transition-all">Registrar usuario</button>
                        <a href="{{ route('usuarios.index') }}" class="text-sm text-slate-600 hover:underline">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
