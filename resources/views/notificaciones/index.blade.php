<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <span class="text-xs font-bold text-[#006837] bg-emerald-50 px-2.5 py-1 rounded-md border border-emerald-200">Avisos del sistema</span>
                <h2 class="font-extrabold text-2xl text-slate-900 tracking-tight mt-1">Notificaciones</h2>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-bold py-2 px-4 rounded-xl transition-all shadow-sm">&larr; Volver al Panel</a>
                @if ($notificaciones->whereNull('read_at')->isNotEmpty())
                    <form method="POST" action="{{ route('notificaciones.leer-todas') }}">
                        @csrf
                        <button type="submit" class="bg-[#006837] hover:bg-[#00522b] text-white text-xs font-bold py-2 px-4 rounded-xl shadow-sm">Marcar todas como leídas</button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-2xl border border-slate-200 divide-y divide-slate-100">
                @forelse ($notificaciones as $notificacion)
                    <a href="{{ route('notificaciones.abrir', $notificacion->id) }}"
                        class="flex items-start gap-3 px-6 py-4 transition-colors hover:bg-slate-50 {{ $notificacion->read_at ? '' : 'bg-emerald-50/40' }}">
                        <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $notificacion->read_at ? 'bg-slate-200' : 'bg-[#006837]' }}"></span>
                        <div class="flex-1">
                            <p class="text-sm font-bold text-slate-900">{{ $notificacion->data['titulo'] }}</p>
                            <p class="text-sm text-slate-600">{{ $notificacion->data['mensaje'] }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ $notificacion->created_at->diffForHumans() }}</p>
                        </div>
                    </a>
                @empty
                    <p class="px-6 py-10 text-center text-sm text-slate-500">No tienes notificaciones por ahora.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
