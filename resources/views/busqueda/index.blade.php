<x-app-layout>
    <x-slot name="header">
        <span class="text-xs font-bold text-[#006837] bg-emerald-50 px-2.5 py-1 rounded-md border border-emerald-200">Consulta</span>
        <h2 class="font-extrabold text-2xl text-slate-900 tracking-tight mt-1">Buscar proyectos</h2>
    </x-slot>

    @php
        $campo = 'mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-[#006837] focus:ring-[#006837]';
    @endphp

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- RF9: búsqueda exacta por código -->
                <div class="bg-white shadow-sm sm:rounded-2xl border border-slate-200 p-6">
                    <h3 class="text-lg font-bold text-slate-900">Por código de radicado</h3>
                    <p class="text-xs text-slate-500 mt-0.5 mb-4">Búsqueda exacta. Ejemplo: PRJ-2026-001</p>

                    @if ($errors->codigo->any())
                        <div class="mb-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl px-3 py-2">
                            @foreach ($errors->codigo->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif
                    @if ($aviso)
                        <div class="mb-3 bg-amber-50 border border-amber-200 text-amber-800 text-xs rounded-xl px-3 py-2">{{ $aviso }}</div>
                    @endif

                    <form method="GET" action="{{ route('buscar') }}" class="flex gap-3">
                        <input type="text" name="codigo" class="{{ $campo }}" placeholder="PRJ-2026-001" value="{{ request('codigo') }}" required>
                        <button type="submit" class="mt-1 bg-[#006837] hover:bg-[#00522b] text-white text-xs font-semibold px-4 rounded-lg shadow-sm transition-all">Buscar</button>
                    </form>
                </div>

                <!-- RF10: búsqueda parcial por título o línea -->
                <div class="bg-white shadow-sm sm:rounded-2xl border border-slate-200 p-6">
                    <h3 class="text-lg font-bold text-slate-900">Por título o línea de investigación</h3>
                    <p class="text-xs text-slate-500 mt-0.5 mb-4">Búsqueda parcial: escribe al menos 3 caracteres.</p>

                    @if ($errors->titulo->any())
                        <div class="mb-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl px-3 py-2">
                            @foreach ($errors->titulo->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <form method="GET" action="{{ route('buscar') }}" class="flex gap-3">
                        <input type="search" name="q" class="{{ $campo }}" placeholder="Ej: plagas, redes, web..." value="{{ request('q') }}" required>
                        <button type="submit" class="mt-1 bg-[#006837] hover:bg-[#00522b] text-white text-xs font-semibold px-4 rounded-lg shadow-sm transition-all">Buscar</button>
                    </form>
                </div>
            </div>

            @if ($resultados !== null)
                <div class="bg-white shadow-sm sm:rounded-2xl border border-slate-200 overflow-hidden">
                    <div class="p-6 bg-slate-50 border-b border-slate-200">
                        <h3 class="text-lg font-bold text-slate-800">Resultados</h3>
                        <p class="text-xs text-slate-500">Coincidencias para «{{ request('q') }}»: <strong>{{ $resultados->count() }}</strong></p>
                    </div>

                    @if ($resultados->isEmpty())
                        <p class="p-6 text-sm text-slate-400">No se encontraron proyectos.</p>
                    @else
                        <ul class="divide-y divide-slate-100 text-sm">
                            @foreach ($resultados as $proyecto)
                                <li class="px-6 py-4 flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <a href="{{ route('proyectos.show', $proyecto) }}" class="font-mono font-bold text-[#006837] hover:underline">{{ $proyecto->codigo_radicado }}</a>
                                        <span class="ml-2 text-slate-800">{{ $proyecto->titulo }}</span>
                                        <p class="text-xs text-slate-500">{{ $proyecto->asunto_linea_investigacion }}</p>
                                    </div>
                                    <span class="text-xs font-semibold text-slate-600">{{ App\Models\Proyecto::ESTADOS[$proyecto->estado] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
