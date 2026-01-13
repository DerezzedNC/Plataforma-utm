<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    /**
     * Listar documentos del estudiante autenticado
     */
    public function index(Request $request)
    {
        $query = Document::where('student_id', auth()->id())
            ->with(['administrador']);

        // Filtros
        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('tipo_documento')) {
            $query->where('tipo_documento', $request->tipo_documento);
        }

        $documents = $query->orderBy('created_at', 'desc')->get();

        return response()->json($documents);
    }

    /**
     * Crear una nueva solicitud de documento
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tipo_documento' => 'required|string|max:255',
            'motivo' => 'nullable|string|max:1000',
        ]);

        $document = Document::create([
            'student_id' => auth()->id(),
            'tipo_documento' => $validated['tipo_documento'],
            'motivo' => $validated['motivo'] ?? null,
            'estado' => 'pendiente_revisar',
            'solicitado_en' => now(),
        ]);

        return response()->json($document, 201);
    }

    /**
     * Mostrar un documento específico del estudiante
     */
    public function show(Document $document)
    {
        // Verificar que el documento pertenece al estudiante autenticado
        if ($document->student_id !== auth()->id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $document->load(['administrador']);

        return response()->json($document);
    }
}

