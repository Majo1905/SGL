<?php

namespace Tests\Feature;

use App\Models\Correccion;
use App\Models\IntegranteProyecto;
use App\Models\PeriodoAcademico;
use App\Models\ProjectDocument;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// Flujo del documento: el estudiante sube su proyecto, lo revisa el tutor, lo valida y pasa a los jurados.
class FlujoRevisionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $estudiante;

    private User $tutor;

    private User $jurado;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        PeriodoAcademico::create([
            'nombre' => '2026-2',
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-12-31',
            'estado' => 'ACTIVO',
        ]);

        $this->admin = User::factory()->admin()->create();
        $this->estudiante = User::factory()->create();
        $this->tutor = User::factory()->tutor()->create();
        $this->jurado = User::factory()->jurado()->create();
    }

    private function datosRadicacion(array $extra = []): array
    {
        return array_merge([
            'titulo' => 'Detección de plagas con visión por computador',
            'asunto_linea_investigacion' => 'Machine Learning',
            'resumen' => 'Modelo de clasificación de imágenes para cultivos de maíz.',
        ], $extra);
    }

    private function proyecto(array $extra = [], bool $conDocumento = true): Proyecto
    {
        $proyecto = Proyecto::create(array_merge([
            'codigo_radicado' => 'PRJ-2026-001',
            'titulo' => 'Sistema de prueba',
            'asunto_linea_investigacion' => 'Ingeniería de Software',
            'resumen' => 'Resumen de prueba con más de veinte caracteres.',
            'estado' => 'RADICADO',
            'fecha_radicacion' => today(),
            'usuario_creador_id' => $this->estudiante->id,
        ], $extra));

        foreach ([[$this->estudiante, 'AUTOR_ESTUDIANTE'], [$this->tutor, 'DIRECTOR_TUTOR'], [$this->jurado, 'JURADO_EVALUADOR']] as [$u, $rol]) {
            IntegranteProyecto::create(['proyecto_id' => $proyecto->id, 'user_id' => $u->id, 'rol_en_proyecto' => $rol]);
        }

        if ($conDocumento) {
            $this->actingAs($this->estudiante)->post(route('proyectos.documentos.store', $proyecto), [
                'documento' => UploadedFile::fake()->create('proyecto.pdf', 150, 'application/pdf'),
            ])->assertRedirect();
        }

        return $proyecto->fresh();
    }

    private function observacion(Proyecto $proyecto, User $autor, array $extra = []): Correccion
    {
        return Correccion::create(array_merge([
            'proyecto_id' => $proyecto->id,
            'usuario_autor_id' => $autor->id,
            'descripcion_observacion' => 'Ajustar la metodología propuesta',
            'fecha_limite' => today()->addWeek(),
        ], $extra));
    }

    // ---------- el estudiante sube su proyecto en un documento ----------

    public function test_el_estudiante_debe_subir_el_documento_al_radicar(): void
    {
        $this->actingAs($this->estudiante)->get(route('proyectos.create'))
            ->assertOk()->assertSee('name="documento"', false);

        $this->actingAs($this->estudiante)->post(route('proyectos.store'), $this->datosRadicacion())
            ->assertSessionHasErrors('documento');

        $this->assertSame(0, Proyecto::count());
    }

    public function test_radicar_con_documento_lo_deja_disponible_para_el_tutor(): void
    {
        $this->actingAs($this->estudiante)->post(route('proyectos.store'), $this->datosRadicacion([
            'documento' => UploadedFile::fake()->create('mi-proyecto.docx', 300, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ]))->assertRedirect();

        $proyecto = Proyecto::firstOrFail();
        $documento = ProjectDocument::firstOrFail();
        $this->assertSame($proyecto->id, $documento->proyecto_id);
        $this->assertSame($this->estudiante->id, $documento->user_id);
        Storage::disk('local')->assertExists($documento->path);

        IntegranteProyecto::create(['proyecto_id' => $proyecto->id, 'user_id' => $this->tutor->id, 'rol_en_proyecto' => 'DIRECTOR_TUTOR']);
        $this->actingAs($this->tutor)->get(route('documentos.descargar', $documento))->assertOk();
        $this->actingAs($this->tutor)->get(route('proyectos.show', $proyecto))->assertOk()->assertSee('mi-proyecto.docx');
    }

    public function test_el_documento_al_radicar_solo_acepta_pdf_doc_o_docx(): void
    {
        $this->actingAs($this->estudiante)->post(route('proyectos.store'), $this->datosRadicacion([
            'documento' => UploadedFile::fake()->create('proyecto.zip', 100, 'application/zip'),
        ]))->assertSessionHasErrors('documento');

        $this->actingAs($this->estudiante)->post(route('proyectos.store'), $this->datosRadicacion([
            'documento' => UploadedFile::fake()->create('proyecto.pdf', 11000, 'application/pdf'),
        ]))->assertSessionHasErrors('documento');

        $this->assertSame(0, Proyecto::count());
    }

    public function test_el_administrador_puede_radicar_sin_documento(): void
    {
        $this->actingAs($this->admin)->post(route('proyectos.store'), $this->datosRadicacion())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Proyecto::count());
        $this->assertSame(0, ProjectDocument::count());
    }

    public function test_un_proyecto_cerrado_no_admite_nuevas_versiones(): void
    {
        $proyecto = $this->proyecto(['estado' => 'APROBADO'], false);

        $this->actingAs($this->estudiante)->post(route('proyectos.documentos.store', $proyecto), [
            'documento' => UploadedFile::fake()->create('v2.pdf', 100, 'application/pdf'),
        ])->assertStatus(409);
    }

    // ---------- el tutor revisa y valida ----------

    public function test_solo_el_tutor_asignado_descarga_el_documento_antes_de_la_validacion(): void
    {
        $proyecto = $this->proyecto();
        $documento = $proyecto->documentos()->firstOrFail();
        $otroTutor = User::factory()->tutor()->create();

        $this->actingAs($this->tutor)->get(route('documentos.descargar', $documento))->assertOk();
        $this->actingAs($this->estudiante)->get(route('documentos.descargar', $documento))->assertOk();
        $this->actingAs($otroTutor)->get(route('documentos.descargar', $documento))->assertForbidden();
        // el jurado todavía no puede ver el documento
        $this->actingAs($this->jurado)->get(route('documentos.descargar', $documento))->assertForbidden();
    }

    public function test_el_tutor_valida_y_el_proyecto_pasa_a_los_jurados(): void
    {
        $proyecto = $this->proyecto();

        $this->actingAs($this->tutor)->get(route('proyectos.show', $proyecto))
            ->assertOk()->assertSee('Validar y enviar a jurados');

        $this->actingAs($this->tutor)->post(route('proyectos.validar', $proyecto))
            ->assertRedirect(route('proyectos.show', $proyecto));

        $proyecto->refresh();
        $this->assertTrue($proyecto->estaValidado());
        $this->assertSame($this->tutor->id, $proyecto->validado_por_tutor_id);
        $this->assertSame('EN_REVISION', $proyecto->estado);

        // los jurados y el estudiante reciben aviso
        $this->assertSame(1, $this->jurado->notifications()->count());
        $this->assertStringContainsString('PRJ-2026-001', $this->jurado->notifications()->first()->data['mensaje']);
        $this->assertGreaterThanOrEqual(1, $this->estudiante->notifications()->count());

        // y desde ese momento el jurado consulta el documento
        $this->actingAs($this->jurado)
            ->get(route('documentos.descargar', $proyecto->documentos()->firstOrFail()))->assertOk();
    }

    public function test_no_se_valida_sin_documento_ni_con_observaciones_pendientes(): void
    {
        $sinDocumento = $this->proyecto(['codigo_radicado' => 'PRJ-2026-002'], false);
        $this->actingAs($this->tutor)->post(route('proyectos.validar', $sinDocumento))
            ->assertSessionHas('error');
        $this->assertFalse($sinDocumento->fresh()->estaValidado());

        $conPendiente = $this->proyecto(['codigo_radicado' => 'PRJ-2026-003']);
        $this->observacion($conPendiente, $this->tutor);
        $this->actingAs($this->tutor)->post(route('proyectos.validar', $conPendiente))
            ->assertSessionHas('error');
        $this->assertFalse($conPendiente->fresh()->estaValidado());
    }

    public function test_solo_el_tutor_asignado_puede_validar(): void
    {
        $proyecto = $this->proyecto();
        $otroTutor = User::factory()->tutor()->create();

        $this->actingAs($otroTutor)->post(route('proyectos.validar', $proyecto))->assertForbidden();
        $this->actingAs($this->jurado)->post(route('proyectos.validar', $proyecto))->assertForbidden();
        $this->actingAs($this->estudiante)->post(route('proyectos.validar', $proyecto))->assertForbidden();
        $this->actingAs($this->admin)->post(route('proyectos.validar', $proyecto))->assertForbidden();

        $this->assertFalse($proyecto->fresh()->estaValidado());
    }

    // ---------- observaciones: tutor y jurado, nunca el administrador ----------

    public function test_el_administrador_no_registra_observaciones(): void
    {
        $proyecto = $this->proyecto();

        $this->actingAs($this->admin)->post(route('proyectos.correcciones.store', $proyecto), [
            'descripcion_observacion' => 'Una observación del administrador',
            'fecha_limite' => today()->addWeek()->format('Y-m-d'),
        ])->assertForbidden();

        $this->assertSame(0, Correccion::count());
    }

    public function test_el_jurado_solo_observa_despues_de_la_validacion_del_tutor(): void
    {
        $proyecto = $this->proyecto();
        $datos = [
            'descripcion_observacion' => 'Falta justificar la metodología',
            'fecha_limite' => today()->addWeek()->format('Y-m-d'),
        ];

        $this->actingAs($this->jurado)->post(route('proyectos.correcciones.store', $proyecto), $datos)->assertStatus(409);
        $this->assertSame(0, Correccion::count());

        $this->actingAs($this->tutor)->post(route('proyectos.validar', $proyecto));
        $this->actingAs($this->jurado)->post(route('proyectos.correcciones.store', $proyecto), $datos)
            ->assertRedirect(route('proyectos.show', $proyecto));
        $this->assertSame(1, Correccion::count());
    }

    public function test_el_tutor_si_observa_antes_de_validar_y_el_estudiante_recibe_aviso(): void
    {
        $proyecto = $this->proyecto();

        $this->actingAs($this->tutor)->post(route('proyectos.correcciones.store', $proyecto), [
            'descripcion_observacion' => 'Mejorar el planteamiento del problema',
            'fecha_limite' => today()->addWeek()->format('Y-m-d'),
        ])->assertRedirect();

        $this->assertSame('CON_OBSERVACIONES', $proyecto->fresh()->estado);
        $aviso = $this->estudiante->notifications()->latest()->first();
        $this->assertSame('Nueva observación en tu proyecto', $aviso->data['titulo']);
    }

    public function test_el_administrador_no_cambia_el_estado_de_una_observacion(): void
    {
        $proyecto = $this->proyecto();
        $correccion = $this->observacion($proyecto, $this->tutor, ['estado' => 'RESUELTO', 'respuesta_estudiante' => 'Listo']);

        $this->actingAs($this->admin)->patchJson(route('correcciones.alternar', $correccion), ['estado' => 'APROBADO'])
            ->assertStatus(422);
        $this->assertSame('RESUELTO', $correccion->fresh()->estado);

        $this->actingAs($this->tutor)->patchJson(route('correcciones.alternar', $correccion), ['estado' => 'APROBADO'])
            ->assertOk();
        $this->assertSame('APROBADO', $correccion->fresh()->estado);
    }

    // ---------- concepto del jurado ----------

    public function test_el_jurado_emite_el_concepto_cuando_el_tutor_ya_valido(): void
    {
        $proyecto = $this->proyecto();
        $this->actingAs($this->tutor)->post(route('proyectos.validar', $proyecto));

        $this->actingAs($this->jurado)->patchJson(route('proyectos.estado', $proyecto), ['estado' => 'RECHAZADO'])
            ->assertOk()->assertJson(['estado' => 'RECHAZADO']);
        $this->assertSame('RECHAZADO', $proyecto->fresh()->estado);

        $this->actingAs($this->jurado)->patchJson(route('proyectos.estado', $proyecto), ['estado' => 'APROBADO'])
            ->assertOk();
        $this->assertSame('APROBADO', $proyecto->fresh()->estado);
    }

    public function test_concepto_no_permitido_para_otros_roles_ni_antes_de_la_validacion(): void
    {
        $proyecto = $this->proyecto();

        // el tutor no emite conceptos
        $this->actingAs($this->tutor)->patchJson(route('proyectos.estado', $proyecto), ['estado' => 'APROBADO'])->assertForbidden();
        $this->actingAs($this->estudiante)->patchJson(route('proyectos.estado', $proyecto), ['estado' => 'APROBADO'])->assertForbidden();
        // el jurado debe esperar la validación del tutor
        $this->actingAs($this->jurado)->patchJson(route('proyectos.estado', $proyecto), ['estado' => 'APROBADO'])->assertStatus(409);

        $this->assertSame('RADICADO', $proyecto->fresh()->estado);
    }

    public function test_no_se_aprueba_con_observaciones_pendientes_ni_con_estados_ajenos_al_concepto(): void
    {
        $proyecto = $this->proyecto();
        $this->actingAs($this->tutor)->post(route('proyectos.validar', $proyecto));
        $this->observacion($proyecto, $this->jurado);

        $this->actingAs($this->jurado)->patchJson(route('proyectos.estado', $proyecto), ['estado' => 'APROBADO'])->assertStatus(422);
        $this->actingAs($this->jurado)->patchJson(route('proyectos.estado', $proyecto), ['estado' => 'RADICADO'])->assertStatus(422);
        $this->actingAs($this->jurado)->patchJson(route('proyectos.estado', $proyecto), ['estado' => 'CON_OBSERVACIONES'])->assertOk();
    }

    // ---------- notificaciones ----------

    public function test_subir_una_version_avisa_al_tutor(): void
    {
        $proyecto = $this->proyecto();
        $tutor = $this->tutor->fresh();

        $this->assertSame(1, $tutor->notifications()->count());
        $this->assertSame('Nuevo documento para revisar', $tutor->notifications()->first()->data['titulo']);
    }

    public function test_las_notificaciones_se_listan_se_cuentan_y_se_marcan_como_leidas(): void
    {
        $proyecto = $this->proyecto();

        $this->actingAs($this->tutor)->getJson(route('notificaciones.contador'))
            ->assertOk()->assertJson(['no_leidas' => 1, 'ultima' => 'Nuevo documento para revisar']);

        $this->actingAs($this->tutor)->get(route('notificaciones.index'))
            ->assertOk()->assertSee('Nuevo documento para revisar');

        $notificacion = $this->tutor->notifications()->firstOrFail();
        $this->actingAs($this->tutor)->get(route('notificaciones.abrir', $notificacion->id))
            ->assertRedirect(route('proyectos.show', $proyecto));
        $this->assertNotNull($notificacion->fresh()->read_at);

        $this->actingAs($this->tutor)->getJson(route('notificaciones.contador'))->assertJson(['no_leidas' => 0]);
    }

    public function test_marcar_todas_como_leidas_y_privacidad_entre_usuarios(): void
    {
        $proyecto = $this->proyecto();
        $this->actingAs($this->tutor)->post(route('proyectos.validar', $proyecto));

        $this->actingAs($this->jurado)->post(route('notificaciones.leer-todas'))->assertRedirect(route('notificaciones.index'));
        $this->assertSame(0, $this->jurado->unreadNotifications()->count());

        // nadie puede abrir la notificación de otra persona
        $ajena = $this->tutor->notifications()->firstOrFail();
        $this->actingAs($this->jurado)->get(route('notificaciones.abrir', $ajena->id))->assertNotFound();
    }

    public function test_asignar_un_integrante_le_avisa_al_usuario(): void
    {
        $proyecto = Proyecto::create([
            'codigo_radicado' => 'PRJ-2026-009',
            'titulo' => 'Otro proyecto',
            'asunto_linea_investigacion' => 'Redes',
            'resumen' => 'Resumen de prueba con más de veinte caracteres.',
            'estado' => 'RADICADO',
            'fecha_radicacion' => today(),
            'usuario_creador_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->post(route('integrantes.store', $proyecto), [
            'user_id' => $this->tutor->id,
            'rol_en_proyecto' => 'DIRECTOR_TUTOR',
        ]);

        $this->assertSame('Te asignaron a un proyecto', $this->tutor->notifications()->firstOrFail()->data['titulo']);
    }

    public function test_el_administrador_recibe_aviso_cuando_un_estudiante_radica(): void
    {
        $this->actingAs($this->estudiante)->post(route('proyectos.store'), $this->datosRadicacion([
            'documento' => UploadedFile::fake()->create('mi-proyecto.pdf', 200, 'application/pdf'),
        ]));

        $this->assertSame('Nuevo proyecto radicado', $this->admin->notifications()->firstOrFail()->data['titulo']);
    }

    // ---------- integridad ----------

    public function test_la_asignacion_es_unica_por_proyecto_usuario_y_rol(): void
    {
        $proyecto = $this->proyecto();

        $this->expectException(QueryException::class);
        IntegranteProyecto::create(['proyecto_id' => $proyecto->id, 'user_id' => $this->tutor->id, 'rol_en_proyecto' => 'DIRECTOR_TUTOR']);
    }
}
