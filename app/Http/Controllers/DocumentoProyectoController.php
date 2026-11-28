<?php

namespace App\Http\Controllers;

use App\Models\ProjectDocument;
use App\Models\Proyecto;
use App\Support\Avisos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentoProyectoController extends Controller
{
    // el estudiante sube su proyecto (o una nueva versión) para que lo revise el tutor
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

        $usuario = $request->user();
        $documento = $proyecto->adjuntarDocumento($datos['documento'], $usuario);

        // se avisa al tutor (y a los jurados si el proyecto ya fue validado)
        $destinatarios = $proyecto->estaValidado()
            ? $proyecto->usuariosConRol('DIRECTOR_TUTOR', 'JURADO_EVALUADOR')
            : $proyecto->usuariosConRol('DIRECTOR_TUTOR');
        Avisos::enviar(
            $destinatarios,
            'Nuevo documento para revisar',
            "{$usuario->name} cargó «{$documento->original_name}» en el proyecto {$proyecto->codigo_radicado}.",
            route('proyectos.show', $proyecto),
            $usuario,
        );

        return redirect()->route('proyectos.show', $proyecto)
            ->with('mensaje', 'Documento cargado de forma privada.');
    }

    public function download(Request $request, ProjectDocument $documento): StreamedResponse
    {
        $proyecto = $documento->proyecto;
        abort_unless(
            $proyecto->puedeConsultarDocumentos($request->user()),
            403,
            'No tienes permiso para consultar este documento.'
        );

        $disco = $documento->disk;
        abort_unless(Storage::disk($disco)->exists($documento->path), 404, 'El documento ya no está disponible.');

        return Storage::disk($disco)->download($documento->path, $documento->original_name);
    }

    private function autorizarCarga(Proyecto $proyecto, Request $request): void
    {
        $usuario = $request->user();
        $esAutorEstudiante = $usuario->esEstudiante() && $proyecto->esAutor($usuario);

        abort_unless($esAutorEstudiante, 403, 'Solo un estudiante autor asignado puede cargar documentos.');
        abort_if($proyecto->estaCerrado(), 409, 'El proyecto ya está cerrado y no admite nuevos documentos.');
    }
}
