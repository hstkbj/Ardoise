<?php

namespace App\Services;

use App\Models\Tenant\Attendance;
use App\Models\Tenant\ClassRoom;
use App\Models\Tenant\Setting;
use App\Models\Tenant\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Appel par séance et justification des absences. */
class AttendanceService
{
    public function __construct(protected Notifier $notifier) {}

    public function roster(ClassRoom $class, string $date, string $slot): array
    {
        $records = Attendance::where('class_room_id', $class->id)->whereDate('date', $date)->where('slot', $slot)->get()->keyBy('student_id');

        return $class->students()->get()->map(fn ($s) => [
            'student_id' => $s->id,
            'full_name' => $s->full_name,
            'matricule' => $s->matricule,
            'status' => $records->get($s->id)?->status ?? 'present',
            'minutes_late' => $records->get($s->id)?->minutes_late ?? '',
            'justified' => (bool) $records->get($s->id)?->is_justified,
            'reason' => $records->get($s->id)?->reason ?? '',
            'recorded' => $records->has($s->id),
        ])->values()->all();
    }

    /** @return array{absent: int, late: int} */
    public function save(ClassRoom $class, string $date, string $slot, array $records, User $by): array
    {
        if ($class->academicYear?->isClosed()) {
            throw ValidationException::withMessages(['class_id' => 'Cette année scolaire est clôturée.']);
        }

        $allowed = $class->students()->pluck('students.id')->map(fn ($id) => (int) $id)->all();
        $newlyMissing = collect();

        DB::connection('tenant')->transaction(function () use ($class, $date, $slot, $records, $by, $allowed, &$newlyMissing) {
            foreach ($records as $row) {
                $studentId = (int) ($row['student_id'] ?? 0);

                if (! in_array($studentId, $allowed, true)) {
                    continue;
                }

                $status = in_array($row['status'] ?? 'present', ['present', 'absent', 'late'], true) ? $row['status'] : 'present';
                $existing = Attendance::where('student_id', $studentId)->whereDate('date', $date)->where('slot', $slot)->first();

                Attendance::updateOrCreate(
                    ['student_id' => $studentId, 'date' => $date, 'slot' => $slot],
                    [
                        'class_room_id' => $class->id,
                        'status' => $status,
                        'minutes_late' => $status === 'late' ? max(1, (int) ($row['minutes_late'] ?? 0)) ?: null : null,
                        'is_justified' => $status !== 'present' && ! empty($row['justified']),
                        'reason' => $status !== 'present' ? (mb_substr((string) ($row['reason'] ?? ''), 0, 255) ?: null) : null,
                        'recorded_by' => $by->id,
                    ],
                );

                if ($status !== 'present' && $existing?->status !== $status) {
                    $newlyMissing->push(['student_id' => $studentId, 'status' => $status, 'minutes' => $row['minutes_late'] ?? null]);
                }
            }
        });

        if (Setting::get('notifications', 'absence_notify', 'immediate') === 'immediate') {
            $label = Carbon::parse($date)->locale('fr')->isoFormat('dddd D MMMM');

            foreach ($newlyMissing as $missing) {
                $student = $class->students()->whereKey($missing['student_id'])->first();
                $text = $missing['status'] === 'absent'
                    ? "{$student->first_name} est absent(e) le {$label} ({$slot})."
                    : "{$student->first_name} est arrivé(e) en retard le {$label} ({$slot}).";
                $this->notifier->event(
                    'attendance.recorded',
                    ($missing['status'] === 'absent' ? 'Absence · ' : 'Retard · ').$student->first_name,
                    $text,
                    $student,
                    ['parent' => '/parent/attendance', 'teacher' => '/teacher/attendance/history', 'staff' => '/admin/attendance/history'],
                );
            }
        }

        return [
            'absent' => collect($records)->where('status', 'absent')->count(),
            'late' => collect($records)->where('status', 'late')->count(),
        ];
    }

    public function justify(Attendance $attendance, ?string $reason, ?string $path, ?User $by): Attendance
    {
        $attendance->update([
            'is_justified' => true,
            'reason' => $reason ?: $attendance->reason,
            'justification_path' => $path ?: $attendance->justification_path,
            'justified_by' => $by?->id,
        ]);

        return $attendance;
    }
}
