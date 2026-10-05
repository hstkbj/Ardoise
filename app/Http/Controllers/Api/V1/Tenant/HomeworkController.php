<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\HomeworkResource;
use App\Models\Tenant\ClassRoom;
use App\Models\Tenant\Homework;
use App\Services\Notifier;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HomeworkController extends Controller
{
    public function __construct(protected Notifier $notifier) {}

    public function index(Request $request)
    {
        $this->authorize('homework.view');

        $query = Homework::with(['subject', 'classRoom', 'teacher'])
            ->when($this->teacherId(), fn ($q, $id) => $q->where('teacher_id', $id));

        return HomeworkResource::collection(ListQuery::paginate($query, $request,
            search: ['title', 'subject.name', 'classRoom.name'],
            filters: ['status' => 'status', 'class_id' => 'class_room_id', 'subject_id' => 'subject_id'],
            sorts: ['due_date' => 'due_date', 'title' => 'title'],
            default: '-due_date',
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('homework.create');
        $homework = Homework::create($this->validated($request));
        $this->storeAttachments($request, $homework);
        $this->notifyIfPublished($homework, null);

        return (new HomeworkResource($homework->load('subject', 'classRoom', 'teacher')))->response()->setStatusCode(201);
    }

    public function show(Homework $homework): HomeworkResource
    {
        $this->authorize('homework.view');
        $this->ensureOwn($homework);

        return new HomeworkResource($homework->load('subject', 'classRoom', 'teacher'));
    }

    public function update(Request $request, Homework $homework): HomeworkResource
    {
        $this->authorize('homework.update');
        $this->ensureOwn($homework);
        $previous = $homework->status;
        $homework->update($this->validated($request, $homework));
        $this->storeAttachments($request, $homework);
        $this->notifyIfPublished($homework, $previous);

        return new HomeworkResource($homework->load('subject', 'classRoom', 'teacher'));
    }

    public function destroy(Homework $homework): JsonResponse
    {
        $this->authorize('homework.delete');
        $this->ensureOwn($homework);
        $homework->delete();

        return response()->json(['message' => 'Devoir supprimé.']);
    }

    protected function notifyIfPublished(Homework $homework, ?string $previous): void
    {
        if ($homework->status === 'published' && $previous !== 'published') {
            $homework->update(['published_at' => $homework->published_at ?? now()]);
            $students = $homework->classRoom->students()->get();
            $this->notifier->parentsOf($students, 'Devoir · '.$homework->subject->name, $homework->title.' — pour le '.$homework->due_date->format('d/m'), '/parent/homework');
        }
    }

    protected function storeAttachments(Request $request, Homework $homework): void
    {
        if (! $request->hasFile('attachments')) {
            return;
        }

        $request->validate(['attachments.*' => ['file', 'max:10240']]);
        $files = $homework->attachments ?? [];

        foreach ((array) $request->file('attachments') as $file) {
            $files[] = ['name' => $file->getClientOriginalName(), 'path' => $file->store('tenants/'.tenant()->code.'/homework')];
        }

        $homework->update(['attachments' => $files]);
    }

    protected function ensureOwn(Homework $homework): void
    {
        $id = $this->teacherId();
        abort_if($id !== null && (int) $homework->teacher_id !== $id, 403);
    }

    protected function validated(Request $request, ?Homework $homework = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'subject_id' => ['required', 'integer', 'exists:tenant.subjects,id'],
            'class_id' => ['required', 'integer', 'exists:tenant.class_rooms,id'],
            'due_date' => ['required', 'date'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'closed'])],
            'instructions' => ['nullable', 'string', 'max:5000'],
        ]);

        $ids = $this->teacherClassIds();
        abort_if($ids !== null && ! in_array((int) $data['class_id'], $ids, true), 403, 'Vous n’intervenez pas dans cette classe.');

        return [
            'title' => $data['title'],
            'subject_id' => $data['subject_id'],
            'class_room_id' => $data['class_id'],
            'teacher_id' => $this->teacherId() ?? $homework?->teacher_id
                ?? \App\Models\Tenant\ClassSubject::where('class_room_id', $data['class_id'])->where('subject_id', $data['subject_id'])->value('teacher_id'),
            'due_date' => $data['due_date'],
            'status' => $data['status'] ?? 'draft',
            'instructions' => $data['instructions'] ?? null,
        ];
    }
}
