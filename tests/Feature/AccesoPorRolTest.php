<?php

namespace Tests\Feature;

use App\Models\Correccion;
use App\Models\IntegranteProyecto;
use App\Models\PeriodoAcademico;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccesoPorRolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PeriodoAcademico::create([
            'nombre' => '2026-2',
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-12-31',
            'estado' => 'ACTIVO',
        ]);
    }

    private function crearProyecto(User $creador, array $extra = []): Proyecto
    {
        return Proyecto::create(array_merge([
            'codigo_radicado' => 'PRJ-2026-001',
            'titulo' => 'Sistema de prueba',
            'asunto_linea_investigacion' => 'Ingeniería de Software',
            'resumen' => 'Resumen de prueba con más de veinte caracteres.',
            'estado' => 'EN_REVISION',
            'fecha_radicacion' => '2026-02-10',
            'usuario_creador_id' => $creador->id,
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

    public function test_el_registro_publico_crea_usuarios_con_rol_estudiante(): void
    {
        $this->post('/register', [
            'name' => 'Nuevo Usuario',
            'documento_identidad' => '10000001',
            'email' => 'nuevo@unicordoba.edu.co',
            'password' => 'password',
            'password_confirmation' => 'password',
            'rol' => 'ADMIN', // intento de escalar privilegios: se ignora
        ])->assertRedirect(route('dashboard', absolute: false));

        $usuarioRegistrado = User::where('email', 'nuevo@unicordoba.edu.co')->firstOrFail();
        $this->assertSame('ESTUDIANTE', $usuarioRegistrado->rol);
        $this->assertAuthenticatedAs($usuarioRegistrado);
        $this->get(route('usuarios.index'))->assertForbidden();
    }

    public function test_estudiante_radica_proyecto_y_queda_como_autor(): void
    {
        Storage::fake('local');
        $estudiante = User::factory()->create();

        $this->actingAs($estudiante)->post('/proyectos', [
            'documento' => UploadedFile::fake()->create('mi-proyecto.pdf', 200, 'application/pdf'),
            'titulo' => 'Detección de plagas con visión por computador',
            'asunto_linea_investigacion' => 'Machine Learning',
            'resumen' => 'Modelo de clasificación de imágenes para cultivos de maíz.',
            'fecha_radicacion' => '2026-03-01',
            'estado' => 'APROBADO', // un estudiante no puede fijar el estado
        ]);

        $proyecto = Proyecto::firstOrFail();
        $this->assertSame('RADICADO', $proyecto->estado);
        $this->assertNotNull($proyecto->periodo_academico_id);
        $this->assertTrue($proyecto->esAutor($estudiante));
        $this->assertSame('AUTOR_ESTUDIANTE', IntegranteProyecto::firstOrFail()->rol_en_proyecto);
    }

    public function test_tutor_no_puede_radicar_proyectos(): void
    {
        $tutor = User::factory()->tutor()->create();
        $this->assertTrue($tutor->fresh()->activo);

        $this->actingAs($tutor)->get('/proyectos/create')->assertForbidden();
    }

    public function test_estudiante_solo_ve_sus_proyectos(): void
    {
        $propio = User::factory()->create();
        $ajeno = User::factory()->create();
        $mio = $this->crearProyecto($propio, ['codigo_radicado' => 'PRJ-2026-001', 'titulo' => 'Proyecto propio']);
        $otro = $this->crearProyecto($ajeno, ['codigo_radicado' => 'PRJ-2026-002', 'titulo' => 'Proyecto ajeno']);

        $this->actingAs($propio)->get(route('proyectos.show', $mio))->assertOk();
        $this->actingAs($propio)->get(route('proyectos.show', $otro))->assertForbidden();

        $this->actingAs($propio)->get('/dashboard')
            ->assertOk()
            ->assertSee('Proyecto propio')
            ->assertDontSee('Proyecto ajeno');
    }

    public function test_tutor_asignado_ve_el_proyecto_y_registra_observaciones(): void
    {
        $estudiante = User::factory()->create();
        $tutor = User::factory()->tutor()->create();
        $proyecto = $this->crearProyecto($estudiante);
        $this->asignar($proyecto, $tutor, 'DIRECTOR_TUTOR');

        $this->actingAs($tutor)->post(route('proyectos.correcciones.store', $proyecto), [
            'descripcion_observacion' => 'Falta el marco teórico',
            'fecha_limite' => now()->addWeek()->format('Y-m-d'),
        ])->assertRedirect(route('proyectos.show', $proyecto));

        $this->assertSame(1, $proyecto->correcciones()->count());
    }

    public function test_tutor_aprueba_la_respuesta_del_estudiante_y_registra_la_fecha(): void
    {
        $estudiante = User::factory()->create();
        $tutor = User::factory()->tutor()->create();
        $proyecto = $this->crearProyecto($estudiante);
        $this->asignar($proyecto, $tutor, 'DIRECTOR_TUTOR');
        $correccion = Correccion::create([
            'proyecto_id' => $proyecto->id,
            'descripcion_observacion' => 'Ampliar la descripción metodológica.',
            'fecha_limite' => today()->addWeek(),
            'usuario_autor_id' => $tutor->id,
        ]);

        $this->actingAs($estudiante)->patchJson(route('correcciones.alternar', $correccion), [
            'estado' => 'RESUELTO',
            'respuesta_estudiante' => 'Se agregó el detalle solicitado.',
        ])->assertOk()->assertJson(['estado' => 'RESUELTO']);

        $this->actingAs($tutor)->patchJson(route('correcciones.alternar', $correccion), [
            'estado' => 'APROBADO',
        ])->assertOk()->assertJson(['estado' => 'APROBADO']);

        $this->assertNotNull($correccion->fresh()->fecha_resolucion);
    }

    public function test_tutor_no_puede_editar_observacion_creada_por_otro_tutor(): void
    {
        $estudiante = User::factory()->create();
        $autor = User::factory()->tutor()->create();
        $otroTutor = User::factory()->tutor()->create();
        $proyecto = $this->crearProyecto($estudiante);
        $this->asignar($proyecto, $autor, 'DIRECTOR_TUTOR');
        $this->asignar($proyecto, $otroTutor, 'DIRECTOR_TUTOR');
        $correccion = Correccion::create([
            'proyecto_id' => $proyecto->id,
            'descripcion_observacion' => 'Ampliar la descripción metodológica.',
            'fecha_limite' => today()->addWeek(),
            'usuario_autor_id' => $autor->id,
        ]);

        $this->actingAs($otroTutor)->get(route('correcciones.edit', $correccion))->assertForbidden();
    }

    public function test_tutor_no_asignado_no_puede_observar_el_proyecto(): void
    {
        $estudiante = User::factory()->create();
        $tutor = User::factory()->tutor()->create();
        $proyecto = $this->crearProyecto($estudiante);

        $this->actingAs($tutor)->post(route('proyectos.correcciones.store', $proyecto), [
            'descripcion_observacion' => 'Falta el marco teórico',
            'fecha_limite' => now()->addWeek()->format('Y-m-d'),
        ])->assertForbidden();

        $this->assertSame(0, Correccion::count());
    }

    public function test_estudiante_no_puede_registrar_observaciones(): void
    {
        $estudiante = User::factory()->create();
        $proyecto = $this->crearProyecto($estudiante);

        $this->actingAs($estudiante)->post(route('proyectos.correcciones.store', $proyecto), [
            'descripcion_observacion' => 'Falta el marco teórico',
            'fecha_limite' => now()->addWeek()->format('Y-m-d'),
        ])->assertForbidden();
    }

    public function test_estudiante_resuelve_su_observacion_pero_no_una_aprobada(): void
    {
        $estudiante = User::factory()->create();
        $proyecto = $this->crearProyecto($estudiante, ['estado' => 'CON_OBSERVACIONES']);
        $pendiente = Correccion::create([
            'proyecto_id' => $proyecto->id,
            'descripcion_observacion' => 'Observación pendiente',
            'fecha_limite' => '2026-03-15',
        ]);
        $aprobada = Correccion::create([
            'proyecto_id' => $proyecto->id,
            'descripcion_observacion' => 'Observación aprobada',
            'fecha_limite' => '2026-03-16',
            'estado' => 'APROBADO',
        ]);

        $this->actingAs($estudiante)->patchJson(route('correcciones.alternar', $pendiente), [
            'estado' => 'RESUELTO',
            'respuesta_estudiante' => 'Se agregó el marco teórico solicitado.',
        ])
            ->assertOk()
            ->assertJson(['estado' => 'RESUELTO', 'resueltas' => 2, 'total' => 2, 'porcentaje' => 90]);
        $this->assertSame('Se agregó el marco teórico solicitado.', $pendiente->fresh()->respuesta_estudiante);

        $this->actingAs($estudiante)->patchJson(route('correcciones.alternar', $aprobada))
            ->assertStatus(422);
    }

    public function test_estudiante_no_puede_marcar_compromiso_resuelto_sin_evidencia(): void
    {
        $estudiante = User::factory()->create();
        $proyecto = $this->crearProyecto($estudiante);
        $observacion = Correccion::create([
            'proyecto_id' => $proyecto->id,
            'descripcion_observacion' => 'Observación pendiente',
            'fecha_limite' => today()->addWeek(),
        ]);

        $this->actingAs($estudiante)
            ->patchJson(route('correcciones.alternar', $observacion), ['estado' => 'RESUELTO'])
            ->assertUnprocessable();
        $this->assertSame('PENDIENTE', $observacion->fresh()->estado);
    }

    public function test_solo_el_administrador_elimina_proyectos(): void
    {
        $estudiante = User::factory()->create();
        $proyecto = $this->crearProyecto($estudiante);
        $this->asignar($proyecto, $estudiante, 'AUTOR_ESTUDIANTE');

        $this->actingAs($estudiante)->delete(route('proyectos.destroy', $proyecto))->assertForbidden();
        $this->assertSame(1, Proyecto::count());

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->delete(route('proyectos.destroy', $proyecto))->assertRedirect(route('dashboard'));
        $this->assertSame(0, Proyecto::count());
    }

    public function test_estudiante_no_edita_un_proyecto_aprobado(): void
    {
        $estudiante = User::factory()->create();
        $proyecto = $this->crearProyecto($estudiante, ['estado' => 'APROBADO']);
        $this->asignar($proyecto, $estudiante, 'AUTOR_ESTUDIANTE');

        $this->actingAs($estudiante)->get(route('proyectos.edit', $proyecto))->assertForbidden();
    }

    public function test_estudiante_edita_su_proyecto_sin_cambiar_el_estado(): void
    {
        $estudiante = User::factory()->create();
        $proyecto = $this->crearProyecto($estudiante);
        $this->asignar($proyecto, $estudiante, 'AUTOR_ESTUDIANTE');

        $this->actingAs($estudiante)->put(route('proyectos.update', $proyecto), [
            'titulo' => 'Título corregido del proyecto',
            'asunto_linea_investigacion' => 'Machine Learning',
            'resumen' => 'Resumen actualizado con más de veinte caracteres.',
            'fecha_radicacion' => '2026-02-10',
            'estado' => 'APROBADO',
        ])->assertRedirect(route('proyectos.show', $proyecto));

        $proyecto->refresh();
        $this->assertSame('Título corregido del proyecto', $proyecto->titulo);
        $this->assertSame('EN_REVISION', $proyecto->estado);
    }

    public function test_solo_el_administrador_asigna_integrantes(): void
    {
        $estudiante = User::factory()->create();
        $proyecto = $this->crearProyecto($estudiante);
        $this->asignar($proyecto, $estudiante, 'AUTOR_ESTUDIANTE');
        $tutor = User::factory()->tutor()->create();

        $this->actingAs($estudiante)->post(route('integrantes.store', $proyecto), [
            'user_id' => $tutor->id,
            'rol_en_proyecto' => 'DIRECTOR_TUTOR',
        ])->assertForbidden();
    }

    public function test_el_rol_del_proyecto_debe_ser_compatible_con_el_rol_del_usuario(): void
    {
        $admin = User::factory()->admin()->create();
        $estudiante = User::factory()->create();
        $proyecto = $this->crearProyecto($admin);

        $this->actingAs($admin)->post(route('integrantes.store', $proyecto), [
            'user_id' => $estudiante->id,
            'rol_en_proyecto' => 'JURADO_EVALUADOR',
        ])->assertSessionHasErrorsIn('integrante', 'rol_en_proyecto');

        $this->assertSame(0, IntegranteProyecto::count());
    }

    public function test_no_se_puede_asignar_un_usuario_inactivo_a_un_proyecto(): void
    {
        $admin = User::factory()->admin()->create();
        $proyecto = $this->crearProyecto($admin);
        $tutorInactivo = User::factory()->tutor()->create(['activo' => false]);

        $this->actingAs($admin)->post(route('integrantes.store', $proyecto), [
            'user_id' => $tutorInactivo->id,
            'rol_en_proyecto' => 'DIRECTOR_TUTOR',
        ])->assertSessionHasErrorsIn('integrante', 'user_id');

        $this->assertSame(0, IntegranteProyecto::count());
    }

    public function test_observacion_aprobada_cuenta_en_el_avance(): void
    {
        $admin = User::factory()->admin()->create();
        $proyecto = $this->crearProyecto($admin, ['estado' => 'CON_OBSERVACIONES']);
        foreach (['2026-03-15', '2026-03-16'] as $fecha) {
            Correccion::create([
                'proyecto_id' => $proyecto->id,
                'descripcion_observacion' => 'Observación de prueba',
                'fecha_limite' => $fecha,
            ]);
        }

        Correccion::query()->limit(1)->update(['estado' => 'APROBADO']);
        $this->assertSame(60, $proyecto->fresh()->porcentaje_avance);

        $aprobada = Correccion::where('estado', 'APROBADO')->firstOrFail();
        $this->assertSame('verde', $aprobada->semaforo);
    }

    public function test_administrador_registra_usuarios_con_rol(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('usuarios.store'), [
            'name' => 'Dra. Ana Torres',
            'documento_identidad' => '10000002',
            'email' => 'ana@unicordoba.edu.co',
            'rol' => 'JURADO',
            'password' => 'clave-segura-123',
            'password_confirmation' => 'clave-segura-123',
        ])->assertRedirect(route('usuarios.index'));

        $this->assertSame('JURADO', User::where('email', 'ana@unicordoba.edu.co')->firstOrFail()->rol);
    }

    public function test_no_se_registra_un_usuario_con_correo_repetido(): void
    {
        $admin = User::factory()->admin()->create();
        $existente = User::factory()->create();

        $this->actingAs($admin)->post(route('usuarios.store'), [
            'name' => 'Otra persona',
            'documento_identidad' => '10000003',
            'email' => $existente->email,
            'rol' => 'ESTUDIANTE',
            'password' => 'clave-segura-123',
            'password_confirmation' => 'clave-segura-123',
        ])->assertSessionHasErrors('email');
    }

    public function test_estudiante_no_entra_a_la_gestion_de_usuarios(): void
    {
        $estudiante = User::factory()->create();

        $this->actingAs($estudiante)->get(route('usuarios.index'))->assertForbidden();
        $this->actingAs($estudiante)->get(route('usuarios.create'))->assertForbidden();
    }
}
