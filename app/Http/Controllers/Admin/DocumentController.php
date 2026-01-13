<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    /**
     * Listar todos los documentos con filtros
     */
    public function index(Request $request)
    {
        $query = Document::with(['student.studentDetail', 'administrador']);

        // Filtros
        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('tipo_documento')) {
            $query->where('tipo_documento', $request->tipo_documento);
        }

        // Por defecto, excluir documentos finalizados (solo mostrar en historial)
        if (!$request->has('include_finalized') || !$request->include_finalized) {
            $query->where('estado', '!=', 'finalizado');
        }

        $documents = $query->orderBy('created_at', 'desc')->get();

        // Agregar información del estudiante
        $documents->each(function ($document) {
            if ($document->student && $document->student->studentDetail) {
                $document->student->matricula = $document->student->studentDetail->matricula;
                $document->student->grado = $document->student->studentDetail->grado;
                $document->student->grupo = $document->student->studentDetail->grupo;
            }
        });

        return response()->json($documents);
    }

    /**
     * Obtener historial de documentos (incluyendo finalizados)
     */
    public function history(Request $request)
    {
        $query = Document::with(['student.studentDetail', 'administrador']);

        // Filtros
        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('tipo_documento')) {
            $query->where('tipo_documento', $request->tipo_documento);
        }

        $documents = $query->orderBy('created_at', 'desc')->get();

        // Agregar información del estudiante
        $documents->each(function ($document) {
            if ($document->student && $document->student->studentDetail) {
                $document->student->matricula = $document->student->studentDetail->matricula;
                $document->student->grado = $document->student->studentDetail->grado;
                $document->student->grupo = $document->student->studentDetail->grupo;
            }
        });

        return response()->json($documents);
    }

    /**
     * Mostrar un documento específico
     */
    public function show(Document $document)
    {
        $document->load(['student.studentDetail', 'administrador']);
        
        // Agregar información del estudiante
        if ($document->student && $document->student->studentDetail) {
            $document->student->matricula = $document->student->studentDetail->matricula;
            $document->student->grado = $document->student->studentDetail->grado;
            $document->student->grupo = $document->student->studentDetail->grupo;
        }
        
        return response()->json($document);
    }

    /**
     * Actualizar estado de un documento
     */
    public function updateStatus(Request $request, Document $document)
    {
        $validated = $request->validate([
            'estado' => 'required|in:pendiente_revisar,pagar_documentos,en_proceso,listo_recoger,finalizado',
            'observaciones' => 'nullable|string',
        ]);

        $updateData = [
            'estado' => $validated['estado'],
            'administrador_id' => auth()->id(),
        ];

        // Actualizar timestamps según el estado
        switch ($validated['estado']) {
            case 'pagar_documentos':
                // Si aún no tiene solicitado_en, establecerlo
                if (!$document->solicitado_en) {
                    $updateData['solicitado_en'] = now();
                }
                break;
            case 'en_proceso':
                // Si aún no tiene solicitado_en, establecerlo
                if (!$document->solicitado_en) {
                    $updateData['solicitado_en'] = now();
                }
                break;
            case 'listo_recoger':
                $updateData['listo_en'] = now();
                break;
            case 'finalizado':
                $updateData['entregado_en'] = now();
                break;
        }

        if (isset($validated['observaciones'])) {
            $updateData['observaciones'] = $validated['observaciones'];
        }

        $document->update($updateData);

        return response()->json($document->load(['student.studentDetail', 'administrador']));
    }

    /**
     * Cancelar un documento
     */
    public function cancel(Request $request, Document $document)
    {
        $validated = $request->validate([
            'observaciones' => 'required|string',
        ]);

        $document->update([
            'estado' => 'cancelado',
            'observaciones' => $validated['observaciones'],
            'administrador_id' => auth()->id(),
        ]);

        return response()->json($document->load(['student.studentDetail', 'administrador']));
    }
}
