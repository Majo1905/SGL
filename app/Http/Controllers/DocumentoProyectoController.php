<?php

namespace App\Http\Controllers;

use App\Models\ProjectDocument;
use App\Models\Proyecto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentoProyectoController extends Controller
{
    public function store(Request $request, Proyecto $proyecto): RedirectResponse
    {
        $this->autorizarCarga($proyecto, $request);

        $datos = $request->validate([
            'documento' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ], [
            'documento.required' => 'Selecciona un documento para cargar.',
            'documento.mimes' => 'El documento debe estar en formato PDF, DOC o DOCX.',
            'documento.max' => 'El documento no puede superar los 10 MB.',
        ]);

        $archivo = $datos['documento'];
        $disco = config('filesystems.project_documents_disk');
        $ruta = $archivo->store("proyectos/{$proyecto->id}/documentos", $disco);

        $proyecto->documentos()->create([
            'user_id' => $request->user()->id,
            'original_name' => mb_substr($archivo->getClientOriginalName(), 0, 255),
            'path' => $ruta,
            'disk' => $disco,
            'mime_type' => $archivo->getMimeType() ?: 'application/octet-stream',
            'size' => $archivo->getSize(),
        ]);

        return redirect()->route('proyectos.show', $proyecto)
            ->with('mensaje', 'Documento cargado de forma privada.');
    }

    public function download(Request $request, ProjectDocument $documento): StreamedResponse
    {
        $proyecto = $documento->proyecto;
        $this->autorizarLectura($proyecto, $request);

        $disco = $documento->disk;
        abort_unless(Storage::disk($disco)->exists($documento->path), 404, 'El documento ya no está disponible.');

        return Storage::disk($disco)->download($documento->path, $documento->original_name);
    }

    private function autorizarCarga(Proyecto $proyecto, Request $request): void
    {
        $usuario = $request->user();
        $esAutorEstudiante = $usuario->esEstudiante() && $proyecto->esAutor($usuario);

        abort_unless($esAutorEstudiante, 403, 'Solo un estudiante autor asignado puede cargar documentos.');
    }

    private function autorizarLectura(Proyecto $proyecto, Request $request): void
    {
        $usuario = $request->user();
        $esTutorAsignado = $usuario->rol === 'TUTOR'
            && $proyecto->integrantes()
                ->where('user_id', $usuario->id)
                ->where('rol_en_proyecto', 'DIRECTOR_TUTOR')
                ->exists();
        $esAutorEstudiante = $usuario->esEstudiante() && $proyecto->esAutor($usuario);

        abort_unless($usuario->esAdmin() || $esTutorAsignado || $esAutorEstudiante, 403, 'No tienes permiso para consultar este documento.');
    }
}
