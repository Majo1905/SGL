<x-app-layout>
    <x-slot name="header">
        <span class="text-xs font-bold text-[#006837] bg-emerald-50 px-2.5 py-1 rounded-md border border-emerald-200">Informes</span>
        <h2 class="font-extrabold text-2xl text-slate-900 tracking-tight mt-1">Proyectos radicados por rango de fechas</h2>
    </x-slot>

    @php
        $campo = 'mt-1 block rounded-xl border-slate-300 text-sm focus:border-[#006837] focus:ring-[#006837]';
    @endphp

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-2xl border border-slate-200 p-6">
                @if ($errors->any())
                    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <form method="GET" action="{{ route('informes.rango') }}" class="flex flex-wrap items-end gap-4">
                    <div>
                        <label for="fecha_inicial" class="block text-xs font-bold text-slate-500 uppercase">Fecha inicial</label>
                        <input type="date" name="fecha_inicial" id="fecha_inicial" class="{{ $campo }}" value="{{ request('fecha_inicial') }}" required>
                    </div>
                    <div>
                        <label for="fecha_final" class="block text-xs font-bold text-slate-500 uppercase">Fecha final</label>
                        <input type="date" name="fecha_final" id="fecha_final" class="{{ $campo }}" value="{{ request('fecha_final') }}" required>
                    </div>
                    <button type="submit" class="bg-[#006837] hover:bg-[#00522b] text-white text-xs font-semibold px-4 py-2.5 rounded-lg shadow-sm transition-all">
                        Generar informe
                    </button>
                </form>
            </div>

            @if ($proyectos !== null)
                <div class="bg-white shadow-sm sm:rounded-2xl border border-slate-200 overflow-hidden">
                    <div class="p-6 bg-slate-50 border-b border-slate-200">
                        <h3 class="text-lg font-bold text-slate-800">Resultado del informe</h3>
                        <p class="text-xs text-slate-500">
                            Del {{ \Illuminate\Support\Carbon::parse(request('fecha_inicial'))->format('d/m/Y') }}
                            al {{ \Illuminate\Support\Carbon::parse(request('fecha_final'))->format('d/m/Y') }}
                            &middot; Total de proyectos: <strong>{{ $proyectos->count() }}</strong>
                        </p>
                    </div>

                    @if ($proyectos->isEmpty())
                        <p class="p-6 text-sm text-slate-400">No hay proyectos en el período.</p>
                    @else
                        <div class="p-6 overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead>
                                    <tr class="text-left text-xs font-bold text-slate-400 uppercase tracking-wider">
                                        <th class="px-4 py-3">Radicado</th>
                                        <th class="px-4 py-3">Título</th>
                                        <th class="px-4 py-3">Línea de investigación</th>
                                        <th class="px-4 py-3">Fecha de radicación</th>
                                        <th class="px-4 py-3">Estado</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($proyectos as $proyecto)
                                        <tr>
                                            <td class="px-4 py-3 font-mono font-bold text-[#006837]">
                                                <a href="{{ route('proyectos.show', $proyecto) }}" class="hover:underline">{{ $proyecto->codigo_radicado }}</a>
                                            </td>
                                            <td class="px-4 py-3 text-slate-800">{{ $proyecto->titulo }}</td>
                                            <td class="px-4 py-3 text-slate-500 text-xs">{{ $proyecto->asunto_linea_investigacion }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap">{{ $proyecto->fecha_radicacion->format('d/m/Y') }}</td>
                                            <td class="px-4 py-3">{{ App\Models\Proyecto::ESTADOS[$proyecto->estado] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
