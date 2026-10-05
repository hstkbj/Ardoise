<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Tenant\Document;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public const CATEGORIES = ['eleve', 'enseignant', 'administratif', 'certificat', 'justificatif'];

    public function index(Request $request)
    {
        $this->authorize('documents.view');

        return DocumentResource::collection(ListQuery::paginate(Document::with(['student', 'teacher', 'uploader']), $request,
            search: ['name', 'student.last_name', 'student.first_name'],
            filters: ['category' => 'category', 'student_id' => 'student_id', 'teacher_id' => 'teacher_id'],
            sorts: ['uploaded_at' => 'created_at', 'name' => 'name'],
            default: '-id',
        ));
    }

    /** Fichiers stockés hors du dossier public, servis uniquement après contrôle d'accès. */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('documents.create');
        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'owner_id' => ['nullable', 'integer', 'exists:tenant.students,id'],
            'student_id' => ['nullable', 'integer', 'exists:tenant.students,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:tenant.teachers,id'],
            'name' => ['nullable', 'string', 'max:200'],
        ]);

        $file = $request->file('file');
        $document = Document::create([
            'category' => $data['category'],
            'name' => $data['name'] ?? $file->getClientOriginalName(),
            'disk' => 'local',
            'path' => $file->store('tenants/'.tenant()->code.'/documents', 'local'),
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
            'student_id' => $data['student_id'] ?? $data['owner_id'] ?? null,
            'teacher_id' => $data['teacher_id'] ?? null,
            'uploaded_by' => $request->user()->id,
        ]);

        return (new DocumentResource($document->load('student', 'teacher', 'uploader')))->response()->setStatusCode(201);
    }

    public function download(Request $request, Document $document): StreamedResponse
    {
        $user = $request->user();
        $isOwnChild = $document->student_id && $user->parentProfile?->hasChild($document->student_id);
        abort_unless($user->can('documents.view') || $isOwnChild, 403);

        return Storage::disk($document->disk)->download($document->path, $document->name);
    }

    public function destroy(Document $document): JsonResponse
    {
        $this->authorize('documents.delete');
        Storage::disk($document->disk)->delete($document->path);
        $document->delete();

        return response()->json(['message' => 'Document supprimé.']);
    }
}
