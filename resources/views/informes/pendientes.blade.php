<x-app-layout>
    <x-slot name="header">
        <span class="text-xs font-bold text-[#006837] bg-emerald-50 px-2.5 py-1 rounded-md border border-emerald-200">Informes</span>
        <h2 class="font-extrabold text-2xl text-slate-900 tracking-tight mt-1">Proyectos con compromisos pendientes</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-2xl border border-slate-200 overflow-hidden">
                <div class="p-6 bg-slate-50 border-b border-slate-200">
                    <h3 class="text-lg font-bold text-slate-800">Observaciones sin subsanar</h3>
                    <p class="text-xs text-slate-500">
                        {{ auth()->user()->esAdmin() ? 'Todos los proyectos del sistema.' : 'Solo los proyectos en los que participas.' }}
                        &middot; Proyectos: <strong>{{ $proyectos->count() }}</strong>
                    </p>
                </div>

                @if ($proyectos->isEmpty())
                    <p class="p-6 text-sm text-slate-400">No existen compromisos pendientes.</p>
                @else
                    <div class="p-6 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead>
                                <tr class="text-left text-xs font-bold text-slate-400 uppercase tracking-wider">
                                    <th class="px-4 py-3">Radicado</th>
                                    <th class="px-4 py-3">Título</th>
                                    <th class="px-4 py-3 text-center">Pendientes</th>
                                    <th class="px-4 py-3">Fecha límite más próxima</th>
                                    <th class="px-4 py-3">Plazo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($proyectos as $proyecto)
                                    @php
                                        $proxima = $proyecto->correcciones->first();
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-3 font-mono font-bold text-[#006837]">
                                            <a href="{{ route('proyectos.show', $proyecto) }}" class="hover:underline">{{ $proyecto->codigo_radicado }}</a>
                                        </td>
                                        <td class="px-4 py-3 text-slate-800">{{ $proyecto->titulo }}</td>
                                        <td class="px-4 py-3 text-center font-bold">{{ $proyecto->correcciones->count() }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">{{ $proxima->fecha_limite->format('d/m/Y') }}</td>
                                        <td class="px-4 py-3">
                                            <span class="px-2 py-0.5 text-xs font-bold rounded-md {{ ['rojo' => 'bg-red-100 text-red-700', 'amarillo' => 'bg-yellow-100 text-yellow-800', 'verde' => 'bg-emerald-100 text-emerald-700'][$proxima->semaforo] }}">
                                                {{ $proxima->texto_plazo }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
