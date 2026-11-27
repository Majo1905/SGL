<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <span class="text-xs font-bold text-[#006837] bg-emerald-50 px-2.5 py-1 rounded-md border border-emerald-200">Trazabilidad de Expediente</span>
                <h2 class="font-extrabold text-2xl text-slate-900 tracking-tight mt-1">
                    {{ $proyecto->codigo_radicado }}
                </h2>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-bold py-2 px-4 rounded-xl transition-all shadow-sm">
                    &larr; Volver al Panel
                </a>
                @if (in_array(auth()->user()->rol, ['ADMIN', 'ESTUDIANTE']))
<a href="{{ route('proyectos.edit', $proyecto) }}" class="inline-flex items-center bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold py-2 px-4 rounded-xl border border-slate-200 transition-all shadow-sm">
                    Editar
                </a>
@endif
                @if (auth()->user()->esAdmin())
<form method="POST" action="{{ route('proyectos.destroy', $proyecto) }}"
                    onsubmit="return confirm('¿Eliminar el proyecto {{ $proyecto->codigo_radicado }}? Esta acción no se puede deshacer.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center bg-white hover:bg-red-50 text-red-600 text-xs font-bold py-2 px-4 rounded-xl border border-red-200 transition-all shadow-sm">
                        Eliminar
                    </button>
                </form>
@endif
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6"
            x-data="expediente({
                porcentaje: {{ $proyecto->porcentaje_avance }},
                estado: '{{ $proyecto->estado }}',
                total: {{ $proyecto->correcciones->count() }},
                resueltas: {{ $proyecto->correcciones->whereIn('estado', ['RESUELTO', 'APROBADO'])->count() }},
                correcciones: @js($proyecto->correcciones->pluck('estado', 'id')),
                respuestas: @js($proyecto->correcciones->pluck('respuesta_estudiante', 'id')),
                urlEstado: '{{ route('proyectos.estado', $proyecto) }}',
            })">

            <!-- 0. Porcentaje de avance (se actualiza en vivo) -->
            @php
                $colorBarra = ['APROBADO' => 'bg-emerald-500', 'RECHAZADO' => 'bg-red-500', 'CON_OBSERVACIONES' => 'bg-amber-500', 'EN_REVISION' => 'bg-sky-500', 'RADICADO' => 'bg-sky-500'][$proyecto->estado];
            @endphp
            <div class="bg-white shadow-sm sm:rounded-2xl border border-slate-200 p-6">
                <div class="flex items-end justify-between mb-3">
                    <div>
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Avance del proyecto</h3>
                        <p class="text-xs text-slate-500 mt-1">
                            <template x-if="total > 0"><span><span x-text="resueltas"></span> de <span x-text="total"></span> observaciones resueltas</span></template>
                            <template x-if="total == 0"><span>Sin observaciones registradas</span></template>
                        </p>
                    </div>
                    <p class="text-4xl font-black text-slate-800"><span x-text="porcentaje"></span>%</p>
                </div>
                <div class="h-4 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full {{ $colorBarra }} transition-all duration-700 ease-out"
                        :style="'width: ' + porcentaje + '%'"></div>
                </div>

                <!-- Sugerencia cuando ya no queda nada pendiente -->
                <div x-show="{{ in_array(auth()->user()->rol, ['ADMIN', 'TUTOR', 'JURADO']) ? 'true' : 'false' }} && estado === 'CON_OBSERVACIONES' && total > 0 && resueltas === total" x-cloak
                    class="mt-4 flex flex-wrap items-center justify-between gap-3 bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3">
                    <p class="text-sm text-emerald-800 font-medium">Todas las observaciones fueron resueltas. ¿Quieres aprobar el proyecto?</p>
                    <button type="button" @click="aprobar()" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-4 py-2 rounded-lg shadow-sm transition-all">
                        Aprobar proyecto
                    </button>
                </div>
            </div>
            
            <!-- 1. Barra Visual de Progreso de Etapas Académicas -->
            <div class="bg-white shadow-sm sm:rounded-2xl border border-slate-200 p-6">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-5">Flujo de Aprobación Institucional</h3>
                @php
                    $paso = match ($proyecto->estado) {
                        'RADICADO' => 1,
                        'APROBADO' => 4,
                        'CON_OBSERVACIONES', 'RECHAZADO' => 3,
                        default => 2,
                    };
                    $colorPaso3 = $proyecto->estado == 'RECHAZADO' ? 'red' : ($proyecto->estado == 'CON_OBSERVACIONES' ? 'amber' : 'green');
                @endphp
                <div class="grid grid-cols-4 gap-2 text-center text-xs font-bold">
                    <div class="space-y-2">
                        <div class="h-2.5 rounded-full bg-[#006837]"></div>
                        <span class="text-[#006837] block">1. Radicado</span>
                    </div>
                    <div class="space-y-2">
                        <div class="h-2.5 rounded-full {{ $paso >= 2 ? 'bg-[#006837]' : 'bg-slate-200' }}"></div>
                        <span class="{{ $paso >= 2 ? 'text-[#006837]' : 'text-slate-400' }} block">2. En Revisión</span>
                    </div>
                    <div class="space-y-2">
                        <div class="h-2.5 rounded-full {{ $paso >= 3 ? ['red' => 'bg-red-500', 'amber' => 'bg-amber-500', 'green' => 'bg-[#006837]'][$colorPaso3] : 'bg-slate-200' }}"></div>
                        <span class="{{ $paso >= 3 ? ['red' => 'text-red-600', 'amber' => 'text-amber-600', 'green' => 'text-[#006837]'][$colorPaso3] : 'text-slate-400' }} block">3. Evaluación / Ajustes</span>
                    </div>
                    <div class="space-y-2">
                        <div class="h-2.5 rounded-full {{ $paso == 4 ? 'bg-emerald-600' : 'bg-slate-200' }}"></div>
                        <span class="{{ $paso == 4 ? 'text-emerald-600' : 'text-slate-400' }} block">4. Avalado</span>
                    </div>
                </div>
            </div>

            <!-- 2. Ficha Técnica del Proyecto -->
            <div class="bg-white shadow-sm sm:rounded-2xl border border-slate-200 p-8">
                <div class="border-b border-slate-100 pb-6 mb-6">
                    <div class="flex flex-wrap items-center justify-between gap-4 mb-3">
                        <span class="text-xs font-semibold text-slate-600 uppercase tracking-wider bg-slate-100 px-3 py-1 rounded-full">
                            {{ $proyecto->asunto_linea_investigacion }}
                        </span>
                        <span class="px-3.5 py-1 text-xs font-extrabold rounded-full 
                            {{ $proyecto->estado == 'APROBADO' ? 'bg-emerald-100 text-emerald-700 border border-emerald-200' : ($proyecto->estado == 'CON_OBSERVACIONES' ? 'bg-amber-100 text-amber-700 border border-amber-200' : ($proyecto->estado == 'RECHAZADO' ? 'bg-red-100 text-red-700 border border-red-200' : 'bg-sky-100 text-sky-700 border border-sky-200')) }}">
                            {{ str_replace('_', ' ', $proyecto->estado) }}
                        </span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 leading-snug">{{ $proyecto->titulo }}</h1>
                </div>

                <div class="bg-slate-50 p-5 rounded-xl border border-slate-100 text-sm">
                    <p class="text-slate-400 font-bold text-xs uppercase mb-1">Resumen Ejecutivo</p>
                    <p class="text-slate-700 leading-relaxed">{{ $proyecto->resumen }}</p>
                </div>
                @if ($proyecto->periodoAcademico)
                    <p class="mt-4 text-sm text-slate-600">Período académico: <strong>{{ $proyecto->periodoAcademico->nombre }}</strong></p>
                @endif
            </div>

            <!-- 3. Línea de Tiempo Cronológica (Timeline) -->
            <div class="bg-white shadow-sm sm:rounded-2xl border border-slate-200 p-8">
                <div class="mb-8">
                    <h3 class="text-xl font-bold text-slate-900">Línea de Tiempo y Trazabilidad del Comité</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Historial cronológico de radicación, observaciones y cumplimiento de compromisos.</p>
                </div>

                <!-- Contenedor del Timeline Vertical -->
                <div class="relative border-l-2 border-emerald-200 ml-4 space-y-8 pb-2">
                    
                    <!-- Hito 1: Radicación Inicial (Siempre presente) -->
                    <div class="relative pl-8">
                        <span class="absolute -left-[11px] top-1 h-5 w-5 rounded-full bg-[#006837] ring-4 ring-emerald-50 flex items-center justify-center">
                            <span class="h-2 w-2 rounded-full bg-white"></span>
                        </span>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <div class="flex flex-wrap justify-between items-center gap-2 mb-1">
                                <span class="text-xs font-extrabold text-[#006837] uppercase tracking-wider">Radicación Oficial de Anteproyecto</span>
                                <span class="text-xs font-mono font-semibold text-slate-500 bg-white px-2.5 py-0.5 rounded border border-slate-200">{{ $proyecto->fecha_radicacion->format('d/m/Y') }}</span>
                            </div>
                            <p class="text-sm text-slate-700">El documento fue registrado exitosamente en el sistema bajo el código <strong>{{ $proyecto->codigo_radicado }}</strong> y asignado a evaluación.</p>
                        </div>
                    </div>

                    <!-- Hitos Dinámicos: Correcciones y Observaciones -->
                    <div class="mb-4 pl-8">
                        <label for="filtro-correcciones" class="mr-2 text-xs font-bold uppercase text-slate-500">Filtrar observaciones</label>
                        <select id="filtro-correcciones" x-model="estadoFiltro" class="rounded-lg border-slate-300 py-1 text-xs">
                            <option value="TODOS">Todas</option>
                            @foreach (App\Models\Correccion::ESTADOS as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>
                    @foreach($proyecto->correcciones as $correccion)
                        <div class="relative pl-8" x-show="estadoFiltro === 'TODOS' || correcciones[{{ $correccion->id }}] === estadoFiltro">
                            <span class="absolute -left-[11px] top-1 h-5 w-5 rounded-full ring-4 flex items-center justify-center transition-colors"
                                :class="resuelta({{ $correccion->id }}) ? 'bg-emerald-500 ring-emerald-50' : 'bg-amber-500 ring-amber-50'">
                                <span class="h-2 w-2 rounded-full bg-white"></span>
                            </span>
                            <div class="bg-white p-5 rounded-xl border shadow-sm transition-colors"
                                :class="resuelta({{ $correccion->id }}) ? 'border-emerald-200' : 'border-amber-200'">
                                <div class="flex flex-wrap justify-between items-center gap-2 mb-2">
                                    <span class="px-2.5 py-0.5 text-xs font-extrabold rounded-md"
                                        :class="{
                                            'bg-amber-100 text-amber-800': correcciones[{{ $correccion->id }}] === 'PENDIENTE',
                                            'bg-sky-100 text-sky-800': correcciones[{{ $correccion->id }}] === 'RESUELTO',
                                            'bg-emerald-100 text-emerald-800': correcciones[{{ $correccion->id }}] === 'APROBADO'
                                        }"
                                        x-text="{
                                            PENDIENTE: 'PENDIENTE',
                                            RESUELTO: 'RESPUESTA EN REVISIÓN',
                                            APROBADO: 'CORRECCIÓN APROBADA'
                                        }[correcciones[{{ $correccion->id }}]]"></span>
                                    <div class="flex items-center gap-2">
                                        @php
                                            $colorPlazo = ['rojo' => 'bg-red-100 text-red-700', 'amarillo' => 'bg-yellow-100 text-yellow-800', 'verde' => 'bg-emerald-100 text-emerald-700'][$correccion->semaforo];
                                        @endphp
                                        <span x-show="!resuelta({{ $correccion->id }})" class="px-2 py-0.5 text-xs font-bold rounded-md {{ $colorPlazo }}">
                                            {{ $correccion->texto_plazo }}
                                        </span>
                                        <span class="text-xs font-semibold text-slate-500">Fecha límite: <strong class="text-slate-700">{{ $correccion->fecha_limite->format('d/m/Y') }}</strong></span>
                                    </div>
                                </div>
                                <p class="text-sm text-slate-800 leading-relaxed">{{ $correccion->descripcion_observacion }}</p>
                                <p class="mt-2 text-xs text-slate-500">
                                    Registrada por: <strong>{{ $correccion->usuarioAutor?->name ?? 'Usuario anterior' }}</strong>
                                </p>
                                <div x-show="respuestas[{{ $correccion->id }}]" x-cloak class="mt-3 rounded-lg border border-sky-100 bg-sky-50 p-3">
                                    <p class="text-xs font-bold uppercase text-sky-800">Respuesta / evidencia del estudiante</p>
                                    <p class="mt-1 whitespace-pre-line text-sm text-sky-900" x-text="respuestas[{{ $correccion->id }}]"></p>
                                </div>
                                <div class="flex items-center gap-3 mt-3 pt-3 border-t border-slate-100 text-xs font-semibold">
                                    @if (auth()->user()->esEstudiante() && $proyecto->esAutor(auth()->user()) && $correccion->estado === 'PENDIENTE')
                                        <button type="button" @click="cambiarEstado({{ $correccion->id }}, '{{ route('correcciones.alternar', $correccion) }}', 'RESUELTO', true)"
                                            :disabled="cargando === {{ $correccion->id }}"
                                            class="px-3 py-1 rounded-lg border border-emerald-300 bg-emerald-50 text-emerald-700 transition-all hover:bg-emerald-100 disabled:opacity-50">
                                            Registrar cumplimiento
                                        </button>
                                    @elseif (in_array(auth()->user()->rol, ['ADMIN', 'TUTOR', 'JURADO']) && $correccion->estado === 'RESUELTO')
                                        <button type="button" @click="cambiarEstado({{ $correccion->id }}, '{{ route('correcciones.alternar', $correccion) }}', 'APROBADO')"
                                            :disabled="cargando === {{ $correccion->id }}"
                                            class="px-3 py-1 rounded-lg border border-emerald-300 bg-emerald-50 text-emerald-700 transition-all hover:bg-emerald-100 disabled:opacity-50">
                                            Aprobar respuesta
                                        </button>
                                        <button type="button" @click="cambiarEstado({{ $correccion->id }}, '{{ route('correcciones.alternar', $correccion) }}', 'PENDIENTE')"
                                            :disabled="cargando === {{ $correccion->id }}"
                                            class="px-3 py-1 rounded-lg border border-amber-300 bg-amber-50 text-amber-800 transition-all hover:bg-amber-100 disabled:opacity-50">
                                            Devolver a pendiente
                                        </button>
                                    @endif
                                    @if (in_array(auth()->user()->rol, ['ADMIN', 'TUTOR', 'JURADO']) && $correccion->estado === 'PENDIENTE' && (auth()->user()->esAdmin() || $correccion->usuario_autor_id === auth()->id()))
                                        <a href="{{ route('correcciones.edit', $correccion) }}" class="text-[#006837] hover:underline">Editar</a>
                                    <form method="POST" action="{{ route('correcciones.destroy', $correccion) }}"
                                        onsubmit="return confirm('¿Eliminar esta observación?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                                    </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <!-- Hito Final: Estado Actual -->
                    <div class="relative pl-8">
                        <span class="absolute -left-[11px] top-1 h-5 w-5 rounded-full {{ $proyecto->estado == 'APROBADO' ? 'bg-emerald-600 ring-emerald-100' : ($proyecto->estado == 'RECHAZADO' ? 'bg-red-600 ring-red-100' : 'bg-sky-600 ring-sky-100') }} ring-4 flex items-center justify-center">
                            <span class="h-2 w-2 rounded-full bg-white"></span>
                        </span>
                        <div class="p-4 rounded-xl border border-dashed {{ $proyecto->estado == 'APROBADO' ? 'border-emerald-300 bg-emerald-50/50' : ($proyecto->estado == 'RECHAZADO' ? 'border-red-300 bg-red-50/50' : 'border-sky-300 bg-sky-50/50') }}">
                            <p class="text-xs font-bold uppercase tracking-wider {{ $proyecto->estado == 'APROBADO' ? 'text-emerald-800' : ($proyecto->estado == 'RECHAZADO' ? 'text-red-800' : 'text-sky-800') }}">
                                Estado Actual del Expediente: {{ str_replace('_', ' ', $proyecto->estado) }}
                            </p>
                            <p class="text-xs text-slate-600 mt-1">
                                @if ($proyecto->estado == 'APROBADO')
                                    El anteproyecto cuenta con aval completo para iniciar etapa de desarrollo.
                                @elseif ($proyecto->estado == 'RECHAZADO')
                                    El comité no avaló el anteproyecto en esta radicación.
                                @else
                                    En espera de validación final por parte del comité de trabajos de grado.
                                @endif
                            </p>
                        </div>
                    </div>

                </div>
            </div>

            <!-- 4. Integrantes y registro de observaciones -->
            @php
                $campo = 'mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-[#006837] focus:ring-[#006837]';
            @endphp
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- Integrantes -->
                <div class="bg-white shadow-sm sm:rounded-2xl border border-slate-200 p-8">
                    <h3 class="text-lg font-bold text-slate-900">Integrantes del proyecto</h3>
                    <p class="text-xs text-slate-500 mt-0.5 mb-5">Autores, director y jurados asignados al expediente.</p>

                    <ul class="divide-y divide-slate-100 mb-6">
                        @forelse ($proyecto->integrantes as $integrante)
                            <li class="flex items-center justify-between py-3 text-sm">
                                <div>
                                    <p class="font-semibold text-slate-800">{{ $integrante->user->name }}</p>
                                    <p class="text-xs text-slate-500">{{ App\Models\IntegranteProyecto::ROLES[$integrante->rol_en_proyecto] }}</p>
                                </div>
                                @if (auth()->user()->esAdmin())
<form method="POST" action="{{ route('integrantes.destroy', $integrante) }}"
                                    onsubmit="return confirm('¿Retirar a {{ $integrante->user->name }} del proyecto?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">Quitar</button>
                                </form>
@endif
                            </li>
                        @empty
                            <li class="py-3 text-sm text-slate-400">Este proyecto todavía no tiene integrantes.</li>
                        @endforelse
                    </ul>

                    @if (auth()->user()->esAdmin() && $usuarios->isNotEmpty())
                        @if ($errors->integrante->any())
                            <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl px-3 py-2">
                                @foreach ($errors->integrante->all() as $error)
                                    <p>{{ $error }}</p>
                                @endforeach
                            </div>
                        @endif

                        <form method="POST" action="{{ route('integrantes.store', $proyecto) }}" class="space-y-3 bg-slate-50 p-4 rounded-xl border border-slate-100">
                            @csrf
                            <div>
                                <label for="user_id" class="block text-xs font-bold text-slate-500 uppercase">Usuario</label>
                                <select name="user_id" id="user_id" class="{{ $campo }}" required>
                                    <option value="">Seleccionar...</option>
                                    @foreach ($usuarios as $usuario)
                                        <option value="{{ $usuario->id }}" @selected(old('user_id') == $usuario->id)>{{ $usuario->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="rol_en_proyecto" class="block text-xs font-bold text-slate-500 uppercase">Rol</label>
                                <select name="rol_en_proyecto" id="rol_en_proyecto" class="{{ $campo }}" required>
                                    @foreach (App\Models\IntegranteProyecto::ROLES as $valor => $etiqueta)
                                        <option value="{{ $valor }}" @selected(old('rol_en_proyecto') == $valor)>{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="bg-[#006837] hover:bg-[#00522b] text-white text-xs font-semibold px-4 py-2 rounded-lg shadow-sm transition-all">
                                Agregar integrante
                            </button>
                        </form>
                    @endif
                </div>

                <!-- Nueva observación -->
                @if (in_array(auth()->user()->rol, ['ADMIN', 'TUTOR', 'JURADO']))
                <div class="bg-white shadow-sm sm:rounded-2xl border border-slate-200 p-8">
                    <h3 class="text-lg font-bold text-slate-900">Registrar observación</h3>
                    <p class="text-xs text-slate-500 mt-0.5 mb-5">La observación aparecerá en la línea de tiempo del expediente.</p>

                    @if ($errors->correccion->any())
                        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl px-3 py-2">
                            @foreach ($errors->correccion->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('proyectos.correcciones.store', $proyecto) }}" class="space-y-4">
                        @csrf
                        <div>
                            <label for="descripcion_observacion" class="block text-xs font-bold text-slate-500 uppercase">Observación</label>
                            <textarea name="descripcion_observacion" id="descripcion_observacion" rows="4" class="{{ $campo }}" required>{{ old('descripcion_observacion') }}</textarea>
                        </div>
                        <div>
                            <label for="fecha_limite" class="block text-xs font-bold text-slate-500 uppercase">Fecha límite</label>
                            <input type="date" name="fecha_limite" id="fecha_limite" min="{{ now()->format('Y-m-d') }}" class="{{ $campo }}" value="{{ old('fecha_limite') }}" required>
                        </div>
                        <button type="submit" class="bg-[#006837] hover:bg-[#00522b] text-white text-xs font-semibold px-4 py-2 rounded-lg shadow-sm transition-all">
                            Guardar observación
                        </button>
                    </form>
                </div>
                @endif
            </div>

        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('expediente', (datos) => ({
                ...datos,
                cargando: null,
                estadoFiltro: 'TODOS',

                resuelta(id) {
                    return ['RESUELTO', 'APROBADO'].includes(this.correcciones[id]);
                },

                async cambiarEstado(id, url, estado, solicitarRespuesta = false) {
                    let respuesta_estudiante;
                    if (solicitarRespuesta) {
                        respuesta_estudiante = prompt('Describe la respuesta o evidencia con la que cumpliste el compromiso:');
                        if (respuesta_estudiante === null) return;
                        if (!respuesta_estudiante.trim()) {
                            alert('Debes describir la respuesta o evidencia antes de continuar.');
                            return;
                        }
                    }
                    this.cargando = id;
                    try {
                        const respuesta = await this.enviar(url, { estado, respuesta_estudiante });
                        this.correcciones[id] = respuesta.estado;
                        if (respuesta_estudiante !== undefined) this.respuestas[id] = respuesta_estudiante.trim();
                        this.porcentaje = respuesta.porcentaje;
                        this.resueltas = respuesta.resueltas;
                        this.total = respuesta.total;
                    } catch (e) {
                        alert(e.message || 'No se pudo actualizar la observación. Intenta de nuevo.');
                    }
                    this.cargando = null;
                },

                async aprobar() {
                    try {
                        await this.enviar(this.urlEstado, { estado: 'APROBADO' });
                        window.location.reload();
                    } catch (e) {
                        alert('No se pudo aprobar el proyecto.');
                    }
                },

                async enviar(url, cuerpo = {}) {
                    const r = await fetch(url, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        },
                        body: JSON.stringify(cuerpo),
                    });
                    if (!r.ok) {
                        const error = await r.json().catch(() => ({}));
                        throw new Error(error.message || `No se pudo guardar el cambio (${r.status}).`);
                    }
                    return r.json();
                },
            }));
        });
    </script>
</x-app-layout>
