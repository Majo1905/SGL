<x-app-layout>
    <x-slot name="header">
        <h2 class="font-extrabold text-2xl text-slate-900 tracking-tight">Períodos académicos</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('mensaje'))
                <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('mensaje') }}</div>
            @endif

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="font-bold text-slate-900">Crear período</h3>
                @if ($errors->any())
                    <ul class="mt-3 list-inside list-disc text-sm text-red-700">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                @endif
                <form method="POST" action="{{ route('periodos.store') }}" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
                    @csrf
                    <label class="text-sm font-medium text-slate-700">Nombre
                        <input name="nombre" maxlength="20" value="{{ old('nombre') }}" placeholder="2026-2" required class="mt-1 block w-full rounded-xl border-slate-300">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Fecha de inicio
                        <input type="date" name="fecha_inicio" value="{{ old('fecha_inicio') }}" required class="mt-1 block w-full rounded-xl border-slate-300">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Fecha de fin
                        <input type="date" name="fecha_fin" value="{{ old('fecha_fin') }}" required class="mt-1 block w-full rounded-xl border-slate-300">
                    </label>
                    <div class="flex items-end">
                        <button class="rounded-xl bg-[#006837] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#00522b]">Crear período</button>
                    </div>
                </form>
            </section>

            <section class="overflow-x-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead><tr class="text-left text-xs font-bold uppercase tracking-wider text-slate-400">
                        <th class="px-4 py-3">Período</th><th class="px-4 py-3">Inicio</th><th class="px-4 py-3">Fin</th><th class="px-4 py-3">Proyectos</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3">Acción</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($periodos as $periodo)
                            <tr>
                                <td class="px-4 py-3 font-semibold">{{ $periodo->nombre }}</td>
                                <td class="px-4 py-3">{{ $periodo->fecha_inicio->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ $periodo->fecha_fin->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ $periodo->proyectos_count }}</td>
                                <td class="px-4 py-3">{{ App\Models\PeriodoAcademico::ESTADOS[$periodo->estado] }}</td>
                                <td class="px-4 py-3">
                                    <form method="POST" action="{{ route('periodos.update', $periodo) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="estado" value="{{ $periodo->estado === 'ACTIVO' ? 'CERRADO' : 'ACTIVO' }}">
                                        <button class="font-semibold text-[#006837] hover:underline">{{ $periodo->estado === 'ACTIVO' ? 'Cerrar' : 'Activar' }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">Aún no hay períodos académicos registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>
    </div>
</x-app-layout>
