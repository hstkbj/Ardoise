<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ClassRoom;
use App\Models\Tenant\TimetableEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TimetableController extends Controller
{
    protected const TINTS = ['mint', 'sky', 'cream', 'lavender', 'blush'];

    /** GET /timetables?class_id= | teacher_id= */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('timetable.view');

        $teacherId = $this->teacherId() ?? $request->integer('teacher_id') ?: null;
        $classId = $request->integer('class_id') ?: null;

        $query = TimetableEntry::with(['subject', 'teacher', 'classRoom'])
            ->whereHas('classRoom', fn ($q) => $q->where('academic_year_id', \App\Models\Tenant\AcademicYear::current()?->id));

        if ($this->teacherId() || ($teacherId && ! $classId)) {
            $query->where('teacher_id', $teacherId);
        } elseif ($classId) {
            $query->where('class_room_id', $classId);
        }

        return response()->json(['data' => $query->orderBy('day_of_week')->orderBy('starts_at')->get()->map(fn ($e) => self::present($e))]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('timetable.create');
        $entry = TimetableEntry::create($this->validated($request));

        return response()->json(['data' => self::present($entry->load('subject', 'teacher', 'classRoom'))], 201);
    }

    public function update(Request $request, TimetableEntry $entry): JsonResponse
    {
        $this->authorize('timetable.update');
        $entry->update($this->validated($request, $entry));

        return response()->json(['data' => self::present($entry->load('subject', 'teacher', 'classRoom'))]);
    }

    public function destroy(TimetableEntry $entry): JsonResponse
    {
        $this->authorize('timetable.delete');
        $entry->delete();

        return response()->json(['message' => 'Créneau supprimé.']);
    }

    public static function present(TimetableEntry $e): array
    {
        return [
            'id' => $e->id,
            'day' => $e->day_of_week,
            'start' => $e->starts_at,
            'end' => $e->ends_at,
            'subject_id' => $e->subject_id,
            'subject' => $e->subject?->name,
            'teacher_id' => $e->teacher_id,
            'teacher' => $e->teacher ? trim(mb_substr($e->teacher->first_name, 0, 1).'. '.$e->teacher->last_name) : '—',
            'room' => $e->room,
            'class_id' => $e->class_room_id,
            'class_name' => $e->classRoom?->name,
            'tint' => self::TINTS[$e->subject_id % count(self::TINTS)],
        ];
    }

    /** Refuse les chevauchements pour la classe, l'enseignant et la salle. */
    protected function validated(Request $request, ?TimetableEntry $entry = null): array
    {
        $data = $request->validate([
            'class_id' => ['required', 'integer', 'exists:tenant.class_rooms,id'],
            'subject_id' => ['required', 'integer', 'exists:tenant.subjects,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:tenant.teachers,id'],
            'day' => ['required', 'integer', 'between:0,5'],
            'start' => ['required', 'date_format:H:i'],
            'end' => ['required', 'date_format:H:i', 'after:start'],
            'room' => ['nullable', 'string', 'max:50'],
        ]);

        $overlap = fn ($q) => $q->where('day_of_week', $data['day'])
            ->where('starts_at', '<', $data['end'])
            ->where('ends_at', '>', $data['start'])
            ->when($entry, fn ($e) => $e->whereKeyNot($entry->id));

        $yearId = ClassRoom::find($data['class_id'])?->academic_year_id;

        if (TimetableEntry::where('class_room_id', $data['class_id'])->where($overlap)->exists()) {
            throw ValidationException::withMessages(['start' => 'La classe a déjà un cours sur ce créneau.']);
        }

        if (! empty($data['teacher_id']) && TimetableEntry::where('teacher_id', $data['teacher_id'])->whereHas('classRoom', fn ($c) => $c->where('academic_year_id', $yearId))->where($overlap)->exists()) {
            throw ValidationException::withMessages(['teacher_id' => 'Cet enseignant a déjà un cours sur ce créneau.']);
        }

        if (! empty($data['room']) && TimetableEntry::where('room', $data['room'])->whereHas('classRoom', fn ($c) => $c->where('academic_year_id', $yearId))->where($overlap)->exists()) {
            throw ValidationException::withMessages(['room' => 'Cette salle est déjà occupée sur ce créneau.']);
        }

        return [
            'class_room_id' => $data['class_id'],
            'subject_id' => $data['subject_id'],
            'teacher_id' => $data['teacher_id'] ?? null,
            'day_of_week' => $data['day'],
            'starts_at' => $data['start'],
            'ends_at' => $data['end'],
            'room' => $data['room'] ?? null,
        ];
    }
}
