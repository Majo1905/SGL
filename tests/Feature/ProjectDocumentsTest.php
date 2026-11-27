<?php

namespace Tests\Feature;

use App\Models\IntegranteProyecto;
use App\Models\ProjectDocument;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private function createProject(User $student): Proyecto
    {
        $project = Proyecto::create([
            'codigo_radicado' => 'PRJ-2026-001',
            'titulo' => 'Documento de prueba',
            'asunto_linea_investigacion' => 'Ingeniería de Software',
            'resumen' => 'Resumen del proyecto para probar las entregas.',
            'estado' => 'RADICADO',
            'fecha_radicacion' => today(),
            'usuario_creador_id' => $student->id,
        ]);

        IntegranteProyecto::create([
            'proyecto_id' => $project->id,
            'user_id' => $student->id,
            'rol_en_proyecto' => 'AUTOR_ESTUDIANTE',
        ]);

        return $project;
    }

    public function test_author_uploads_a_private_document_that_assigned_tutor_and_admin_can_download(): void
    {
        Storage::fake('local');
        $student = User::factory()->create();
        $tutor = User::factory()->tutor()->create();
        $admin = User::factory()->admin()->create();
        $project = $this->createProject($student);
        IntegranteProyecto::create([
            'proyecto_id' => $project->id,
            'user_id' => $tutor->id,
            'rol_en_proyecto' => 'DIRECTOR_TUTOR',
        ]);

        $this->actingAs($student)->post(route('proyectos.documentos.store', $project), [
            'documento' => UploadedFile::fake()->create('entrega.pdf', 128, 'application/pdf'),
        ])->assertRedirect(route('proyectos.show', $project));

        $document = ProjectDocument::firstOrFail();
        Storage::disk('local')->assertExists($document->path);

        $this->actingAs($student)->get(route('proyectos.show', $project))->assertOk()->assertSee('entrega.pdf');
        $this->actingAs($tutor)->get(route('documentos.descargar', $document))->assertOk();
        $this->actingAs($admin)->get(route('documentos.descargar', $document))->assertOk();
    }

    public function test_non_author_cannot_upload_or_view_project_documents(): void
    {
        Storage::fake('local');
        $student = User::factory()->create();
        $otherStudent = User::factory()->create();
        $otherTutor = User::factory()->tutor()->create();
        $project = $this->createProject($student);

        $this->actingAs($otherStudent)->post(route('proyectos.documentos.store', $project), [
            'documento' => UploadedFile::fake()->create('entrega.pdf', 128, 'application/pdf'),
        ])->assertForbidden();

        $document = $project->documentos()->create([
            'user_id' => $student->id,
            'original_name' => 'privado.pdf',
            'path' => 'proyectos/1/documentos/privado.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'size' => 128,
        ]);

        $this->actingAs($otherTutor)->get(route('documentos.descargar', $document))->assertForbidden();
    }

    public function test_project_creator_without_an_author_membership_can_upload_and_download_documents(): void
    {
        Storage::fake('local');
        $student = User::factory()->create();
        $project = Proyecto::create([
            'codigo_radicado' => 'PRJ-2026-002',
            'titulo' => 'Proyecto sin integrante histórico',
            'asunto_linea_investigacion' => 'Ingeniería de Software',
            'resumen' => 'Prueba de compatibilidad para proyectos anteriores.',
            'estado' => 'RADICADO',
            'fecha_radicacion' => today(),
            'usuario_creador_id' => $student->id,
        ]);

        $this->actingAs($student)->post(route('proyectos.documentos.store', $project), [
            'documento' => UploadedFile::fake()->create('entrega.pdf', 128, 'application/pdf'),
        ])->assertRedirect(route('proyectos.show', $project));

        $document = ProjectDocument::firstOrFail();
        $this->actingAs($student)->get(route('documentos.descargar', $document))->assertOk();
    }

    public function test_document_upload_rejects_unsupported_formats_and_large_files(): void
    {
        Storage::fake('local');
        $student = User::factory()->create();
        $project = $this->createProject($student);

        $this->actingAs($student)->post(route('proyectos.documentos.store', $project), [
            'documento' => UploadedFile::fake()->create('script.php', 20, 'application/x-php'),
        ])->assertSessionHasErrors('documento');

        $this->actingAs($student)->post(route('proyectos.documentos.store', $project), [
            'documento' => UploadedFile::fake()->create('tesis.pdf', 11000, 'application/pdf'),
        ])->assertSessionHasErrors('documento');

        $this->assertSame(0, ProjectDocument::count());
    }
}
