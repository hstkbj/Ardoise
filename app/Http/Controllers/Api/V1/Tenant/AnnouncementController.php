<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnnouncementResource;
use App\Models\Tenant\Announcement;
use App\Models\Tenant\ParentProfile;
use App\Models\Tenant\Student;
use App\Models\Tenant\User;
use App\Services\Notifier;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    public function __construct(protected Notifier $notifier) {}

    public function index(Request $request)
    {
        $this->authorize('announcements.view');

        return AnnouncementResource::collection(ListQuery::paginate(Announcement::with(['author', 'campus', 'classRoom']), $request,
            search: ['title', 'body'],
            filters: ['status' => 'status'],
            sorts: ['published_at' => 'published_at', 'title' => 'title'],
            default: '-id',
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('announcements.create');
        $announcement = Announcement::create($this->validated($request) + ['author_id' => $request->user()->id]);
        $this->publishIfNeeded($announcement, null);

        return (new AnnouncementResource($announcement->load('author', 'campus', 'classRoom')))->response()->setStatusCode(201);
    }

    public function show(Announcement $announcement): AnnouncementResource
    {
        $this->authorize('announcements.view');

        return new AnnouncementResource($announcement->load('author', 'campus', 'classRoom'));
    }

    public function update(Request $request, Announcement $announcement): AnnouncementResource
    {
        $this->authorize('announcements.update');
        $previous = $announcement->status;
        $announcement->update($this->validated($request));
        $this->publishIfNeeded($announcement, $previous);

        return new AnnouncementResource($announcement->load('author', 'campus', 'classRoom'));
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        $this->authorize('announcements.delete');
        $announcement->delete();

        return response()->json(['message' => 'Annonce supprimée.']);
    }

    /** À la publication : notification des destinataires (parents, enseignants, élèves). */
    protected function publishIfNeeded(Announcement $announcement, ?string $previous): void
    {
        if ($announcement->status !== 'published' || $previous === 'published') {
            return;
        }

        $announcement->update(['published_at' => $announcement->published_at ?? now()]);
        $audiences = $announcement->audiences ?: ['parents'];

        $students = Student::query()
            ->where('status', 'active')
            ->when($announcement->class_room_id, fn ($q) => $q->whereHas('currentEnrollment', fn ($e) => $e->where('class_room_id', $announcement->class_room_id)))
            ->when($announcement->campus_id && ! $announcement->class_room_id, fn ($q) => $q->whereHas('currentEnrollment.classRoom', fn ($c) => $c->where('campus_id', $announcement->campus_id)))
            ->pluck('id');

        $channels = array_values(array_intersect(['in_app', 'email', 'sms'], $announcement->channels ?: ['in_app']));
        $body = mb_substr(strip_tags($announcement->body), 0, 160);
        $parents = collect();

        if (array_intersect($audiences, ['parents', 'class', 'school', 'students'])) {
            $parents = User::where('status', 'active')->whereIn('id', ParentProfile::whereHas('students', fn ($q) => $q->whereIn('students.id', $students))->pluck('user_id'))->get();
            $this->notifier->users($parents, $announcement->title, $body, 'announcement', '/parent/announcements', $channels);
        }

        if (array_intersect($audiences, ['teachers', 'school'])) {
            $teachers = User::where('status', 'active')->whereHas('roles', fn ($q) => $q->where('key', 'teacher'))->whereNotIn('id', $parents->pluck('id'))->get();
            $this->notifier->users($teachers, $announcement->title, $body, 'announcement', null, $channels);
        }
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'audiences' => ['nullable', 'array'],
            'audiences.*' => [Rule::in(['school', 'class', 'teachers', 'parents', 'students'])],
            'channels' => ['nullable', 'array'],
            'channels.*' => [Rule::in(['in_app', 'email', 'sms', 'push'])],
            'school_id' => ['nullable', 'integer', 'exists:tenant.campuses,id'],
            'class_id' => ['nullable', 'integer', 'exists:tenant.class_rooms,id'],
            'status' => ['nullable', Rule::in(['draft', 'published'])],
        ]);

        return [
            'title' => $data['title'],
            'body' => $data['body'],
            'audiences' => $data['audiences'] ?? ['parents'],
            'channels' => array_values(array_unique(array_merge(['in_app'], $data['channels'] ?? []))),
            'campus_id' => $data['school_id'] ?? null,
            'class_room_id' => $data['class_id'] ?? null,
            'status' => $data['status'] ?? 'published',
        ];
    }
}
