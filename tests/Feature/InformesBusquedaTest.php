<?php

namespace Tests\Feature;

use App\Models\Correccion;
use App\Models\IntegranteProyecto;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InformesBusquedaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function crearProyecto(array $extra = []): Proyecto
    {
        return Proyecto::create(array_merge([
            'codigo_radicado' => 'PRJ-2026-001',
            'titulo' => 'Sistema de prueba',
            'asunto_linea_investigacion' => 'Ingeniería de Software',
            'resumen' => 'Resumen de prueba con más de veinte caracteres.',
            'estado' => 'EN_REVISION',
            'fecha_radicacion' => '2026-02-10',
            'usuario_creador_id' => $this->admin->id,
        ], $extra));
    }

    private function asignar(Proyecto $proyecto, User $usuario, string $rol): void
    {
        IntegranteProyecto::create([
            'proyecto_id' => $proyecto->id,
            'user_id' => $usuario->id,
            'rol_en_proyecto' => $rol,
        ]);
    }

    // ---------- RF6: informe por rango de fechas ----------

    public function test_informe_por_rango_lista_solo_los_proyectos_del_periodo(): void
    {
        $this->crearProyecto(['codigo_radicado' => 'PRJ-2026-001', 'titulo' => 'Dentro del rango', 'fecha_radicacion' => '2026-02-10']);
        $this->crearProyecto(['codigo_radicado' => 'PRJ-2026-002', 'titulo' => 'Límite final incluido', 'fecha_radicacion' => '2026-02-28']);
        $this->crearProyecto(['codigo_radicado' => 'PRJ-2026-003', 'titulo' => 'Fuera del rango', 'fecha_radicacion' => '2026-05-01']);

        $this->actingAs($this->admin)
            ->get(route('informes.rango', ['fecha_inicial' => '2026-02-01', 'fecha_final' => '2026-02-28']))
            ->assertOk()
            ->assertSee('Dentro del rango')
            ->assertSee('Límite final incluido')
            ->assertDontSee('Fuera del rango')
            ->assertSee('Total de proyectos');
    }

    public function test_informe_por_rango_sin_resultados(): void
    {
        $this->actingAs($this->admin)
            ->get(route('informes.rango', ['fecha_inicial' => '2020-01-01', 'fecha_final' => '2020-12-31']))
            ->assertOk()
            ->assertSee('No hay proyectos en el período.');
    }

    public function test_informe_por_rango_rechaza_fecha_inicial_posterior_a_la_final(): void
    {
        $this->actingAs($this->admin)
            ->get(route('informes.rango', ['fecha_inicial' => '2026-03-01', 'fecha_final' => '2026-02-01']))
            ->assertSessionHasErrors('fecha_final');
    }

    public function test_informe_por_rango_exige_las_dos_fechas(): void
    {
        $this->actingAs($this->admin)
            ->get(route('informes.rango', ['fecha_inicial' => '', 'fecha_final' => '']))
            ->assertSessionHasErrors(['fecha_inicial', 'fecha_final']);
    }

    public function test_informe_por_rango_es_solo_del_administrador(): void
    {
        $this->actingAs(User::factory()->tutor()->create())->get(route('informes.rango'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('informes.rango'))->assertForbidden();
    }

    // ---------- RF7: compromisos pendientes ----------

    public function test_informe_de_pendientes_solo_muestra_proyectos_con_observaciones_pendientes(): void
    {
        $conPendiente = $this->crearProyecto(['codigo_radicado' => 'PRJ-2026-001', 'titulo' => 'Proyecto con pendiente']);
        $resuelto = $this->crearProyecto(['codigo_radicado' => 'PRJ-2026-002', 'titulo' => 'Proyecto sin pendientes']);

        Correccion::create(['proyecto_id' => $conPendiente->id, 'descripcion_observacion' => 'Observación vencida', 'fecha_limite' => today()->subDays(2)]);
        Correccion::create(['proyecto_id' => $conPendiente->id, 'descripcion_observacion' => 'Otra observación', 'fecha_limite' => today()->addDays(20)]);
        Correccion::create(['proyecto_id' => $resuelto->id, 'descripcion_observacion' => 'Observación cerrada', 'fecha_limite' => '2026-03-01', 'estado' => 'RESUELTO']);

        $this->actingAs($this->admin)->get(route('informes.pendientes'))
            ->assertOk()
            ->assertSee('Proyecto con pendiente')
            ->assertDontSee('Proyecto sin pendientes')
            ->assertSee('Vencida hace 2 días');
    }

    public function test_informe_de_pendientes_sin_compromisos(): void
    {
        $this->crearProyecto();

        $this->actingAs($this->admin)->get(route('informes.pendientes'))
            ->assertOk()
            ->assertSee('No existen compromisos pendientes.');
    }

    public function test_el_docente_solo_ve_pendientes_de_sus_proyectos(): void
    {
        $tutor = User::factory()->tutor()->create();
        $mio = $this->crearProyecto(['codigo_radicado' => 'PRJ-2026-001', 'titulo' => 'Proyecto del tutor']);
        $ajeno = $this->crearProyecto(['codigo_radicado' => 'PRJ-2026-002', 'titulo' => 'Proyecto de otro tutor']);
        $this->asignar($mio, $tutor, 'DIRECTOR_TUTOR');

        foreach ([$mio, $ajeno] as $proyecto) {
            Correccion::create(['proyecto_id' => $proyecto->id, 'descripcion_observacion' => 'Observación pendiente', 'fecha_limite' => today()->addDays(10)]);
        }

        $this->actingAs($tutor)->get(route('informes.pendientes'))
            ->assertOk()
            ->assertSee('Proyecto del tutor')
            ->assertDontSee('Proyecto de otro tutor');
    }

    public function test_el_estudiante_no_ve_el_informe_de_pendientes(): void
    {
        $this->actingAs(User::factory()->create())->get(route('informes.pendientes'))->assertForbidden();
    }

    // ---------- RF8: proyectos por usuario ----------

    public function test_informe_por_usuario_lista_sus_proyectos_con_el_rol(): void
    {
        $tutor = User::factory()->tutor()->create(['name' => 'Roberto Gómez']);
        $proyecto = $this->crearProyecto(['titulo' => 'Proyecto dirigido por Roberto']);
        $otro = $this->crearProyecto(['codigo_radicado' => 'PRJ-2026-002', 'titulo' => 'Proyecto sin Roberto']);
        $this->asignar($proyecto, $tutor, 'DIRECTOR_TUTOR');

        $this->actingAs($this->admin)->get(route('informes.usuario', ['user_id' => $tutor->id]))
            ->assertOk()
            ->assertSee('Proyecto dirigido por Roberto')
            ->assertSee('Director / Tutor')
            ->assertDontSee('Proyecto sin Roberto');
    }

    public function test_informe_por_usuario_sin_proyectos(): void
    {
        $estudiante = User::factory()->create();

        $this->actingAs($this->admin)->get(route('informes.usuario', ['user_id' => $estudiante->id]))
            ->assertOk()
            ->assertSee('El usuario no tiene proyectos asociados.');
    }

    public function test_informe_por_usuario_exige_seleccionar_a_alguien(): void
    {
        $this->actingAs($this->admin)->get(route('informes.usuario', ['user_id' => '']))
            ->assertSessionHasErrors('user_id');
    }

    public function test_la_lista_desplegable_incluye_docentes_y_estudiantes(): void
    {
        $tutor = User::factory()->tutor()->create(['name' => 'Tutor de la lista']);
        $estudiante = User::factory()->create(['name' => 'Estudiante de la lista']);

        $this->actingAs($this->admin)->get(route('informes.usuario'))
            ->assertOk()
            ->assertSee('Tutor de la lista')
            ->assertSee('Estudiante de la lista');
    }

    // ---------- RF9: búsqueda exacta por código ----------

    public function test_busqueda_por_codigo_abre_la_ficha_del_proyecto(): void
    {
        $proyecto = $this->crearProyecto(['codigo_radicado' => 'PRJ-2026-001']);

        $this->actingAs($this->admin)->get(route('buscar', ['codigo' => 'PRJ-2026-001']))
            ->assertRedirect(route('proyectos.show', $proyecto));
    }

    public function test_busqueda_por_codigo_normaliza_mayusculas_y_espacios(): void
    {
        $proyecto = $this->crearProyecto(['codigo_radicado' => 'PRJ-2026-001']);

        $this->actingAs($this->admin)->get(route('buscar', ['codigo' => ' prj-2026-001 ']))
            ->assertRedirect(route('proyectos.show', $proyecto));
    }

    public function test_busqueda_por_codigo_inexistente(): void
    {
        $this->crearProyecto();

        $this->actingAs($this->admin)->get(route('buscar', ['codigo' => 'PRJ-2026-999']))
            ->assertOk()
            ->assertSee('No se encontró ningún proyecto con ese código.');
    }

    public function test_busqueda_por_codigo_es_exacta_no_parcial(): void
    {
        $this->crearProyecto(['codigo_radicado' => 'PRJ-2026-001']);

        $this->actingAs($this->admin)->get(route('buscar', ['codigo' => 'PRJ-2026']))
            ->assertOk()
            ->assertSee('No se encontró ningún proyecto con ese código.');
    }

    public function test_busqueda_por_codigo_vacio(): void
    {
        $this->actingAs($this->admin)->get(route('buscar', ['codigo' => '']))
            ->assertSessionHasErrorsIn('codigo', 'codigo');
    }

    public function test_busqueda_por_codigo_de_un_proyecto_ajeno_niega_el_acceso(): void
    {
        $this->crearProyecto(['codigo_radicado' => 'PRJ-2026-001']);
        $estudiante = User::factory()->create();

        $this->actingAs($estudiante)->get(route('buscar', ['codigo' => 'PRJ-2026-001']))
            ->assertOk()
            ->assertSee('No tienes acceso a ese proyecto.');
    }

    // ---------- RF10: búsqueda parcial por título o asunto ----------

    public function test_busqueda_parcial_por_titulo(): void
    {
        $this->crearProyecto(['codigo_radicado' => 'PRJ-2026-001', 'titulo' => 'Detección de plagas en maíz']);
        $this->crearProyecto(['codigo_radicado' => 'PRJ-2026-002', 'titulo' => 'Redes de sensores inalámbricos']);

        $this->actingAs($this->admin)->get(route('buscar', ['q' => 'plagas']))
            ->assertOk()
            ->assertSee('Detección de plagas en maíz')
            ->assertDontSee('Redes de sensores inalámbricos');
    }

    public function test_busqueda_parcial_tambien_mira_la_linea_de_investigacion(): void
    {
        $this->crearProyecto(['titulo' => 'Proyecto A', 'asunto_linea_investigacion' => 'Telemática y Redes']);

        $this->actingAs($this->admin)->get(route('buscar', ['q' => 'telem']))
            ->assertOk()
            ->assertSee('Proyecto A');
    }

    public function test_busqueda_parcial_sin_coincidencias(): void
    {
        $this->crearProyecto();

        $this->actingAs($this->admin)->get(route('buscar', ['q' => 'zzzzz']))
            ->assertOk()
            ->assertSee('No se encontraron proyectos.');
    }

    public function test_busqueda_parcial_exige_minimo_tres_caracteres(): void
    {
        $this->actingAs($this->admin)->get(route('buscar', ['q' => 'ab']))
            ->assertSessionHasErrorsIn('titulo', 'q');
    }

    public function test_busqueda_parcial_respeta_los_proyectos_del_usuario(): void
    {
        $estudiante = User::factory()->create();
        $propio = $this->crearProyecto(['codigo_radicado' => 'PRJ-2026-001', 'titulo' => 'Plagas en maíz propio', 'usuario_creador_id' => $estudiante->id]);
        $this->crearProyecto(['codigo_radicado' => 'PRJ-2026-002', 'titulo' => 'Plagas en arroz ajeno']);

        $this->actingAs($estudiante)->get(route('buscar', ['q' => 'plagas']))
            ->assertOk()
            ->assertSee('Plagas en maíz propio')
            ->assertDontSee('Plagas en arroz ajeno');
    }

    public function test_la_pantalla_de_busqueda_carga(): void
    {
        $this->actingAs(User::factory()->create())->get(route('buscar'))->assertOk();
    }
}
