<?php

namespace App\Http\Controllers\Api\V1\Parent;

use App\Http\Controllers\Api\V1\Tenant\AttendanceController;
use App\Http\Controllers\Api\V1\Tenant\TimetableController;
use App\Http\Controllers\Controller;
use App\Http\Resources\AnnouncementResource;
use App\Http\Resources\DocumentResource;
use App\Models\Tenant\Announcement;
use App\Models\Tenant\Attendance;
use App\Models\Tenant\Document;
use App\Models\Tenant\FeeAssignment;
use App\Models\Tenant\Grade;
use App\Models\Tenant\Homework;
use App\Models\Tenant\HomeworkSubmission;
use App\Models\Tenant\ParentProfile;
use App\Models\Tenant\Payment;
use App\Models\Tenant\ReportCard;
use App\Models\Tenant\Setting;
use App\Models\Tenant\Student;
use App\Models\Tenant\Term;
use App\Models\Tenant\TimetableEntry;
use App\Services\AttendanceService;
use App\Services\Notifier;
use App\Services\Payments\FedaPay\FedaPayException;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\SchoolFedaPay;
use App\Services\PaymentService;
use App\Services\ReportCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Espace parent (web et application mobile).
 * Chaque requête est limitée aux enfants rattachés au parent connecté.
 */
class ParentPortalController extends Controller
{
    public function __construct(protected ReportCardService $cards) {}

    /** GET /parent/children : enfants avec leur résumé complet (accueil de l'application). */
    public function children(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->students($request)->map(fn (Student $s) => $this->withoutDisabledModules($this->summary($s)))->values()]);
    }

    public function child(Request $request, int $student): JsonResponse
    {
        return response()->json(['data' => $this->withoutDisabledModules($this->summary($this->ownStudent($request, $student)))]);
    }

    /** POST /parent/attendance/{attendance}/justify { reason, file? } */
    public function justify(Request $request, Attendance $attendance, AttendanceService $service, Notifier $notifier): JsonResponse
    {
        $this->ownStudent($request, $attendance->student_id);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255'], 'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120']]);
        $path = $request->file('file')?->store('tenants/'.tenant()->code.'/justifications');

        $service->justify($attendance, $data['reason'], $path, $request->user());
        $notifier->event(
            'attendance.justified',
            'Absence justifiée par un parent',
            $attendance->student->full_name.' · '.$attendance->date->format('d/m').' : '.$data['reason'],
            $attendance->student,
            ['teacher' => '/teacher/attendance/history', 'staff' => '/admin/attendance/history'],
        );

        return response()->json(['message' => 'Justificatif envoyé.', 'data' => AttendanceController::present($attendance->load('student', 'classRoom'))]);
    }

    /** POST /parent/homework/{homework}/done { student_id, done } */
    public function homeworkDone(Request $request, Homework $homework): JsonResponse
    {
        $data = $request->validate(['student_id' => ['required', 'integer'], 'done' => ['boolean']]);
        $student = $this->ownStudent($request, $data['student_id']);
        abort_unless((int) $student->currentClass()?->id === (int) $homework->class_room_id, 403);

        if ($data['done'] ?? true) {
            HomeworkSubmission::updateOrCreate(['homework_id' => $homework->id, 'student_id' => $student->id], ['submitted_at' => now()]);
        } else {
            HomeworkSubmission::where('homework_id', $homework->id)->where('student_id', $student->id)->delete();
        }

        return response()->json(['message' => 'Enregistré.']);
    }

    /**
     * POST /parent/payments/{assignment}/checkout { method, phone, amount }
     *
     * Avec FedaPay activé par l'école : renvoie l'URL de la page de paiement
     * (Mobile Money / carte) ; sinon la demande est transmise à la comptabilité.
     */
    public function checkout(Request $request, FeeAssignment $assignment, PaymentService $payments, Notifier $notifier, SchoolFedaPay $fedapay): JsonResponse
    {
        $this->ownStudent($request, $assignment->student_id);
        abort_if($assignment->status === 'paid', 422, 'Cette échéance est déjà réglée.');
        $data = $request->validate(['method' => ['nullable', 'string', 'max:50'], 'phone' => ['nullable', 'string', 'max:30'], 'amount' => ['nullable', 'integer', 'min:1']]);
        $amount = min($data['amount'] ?? $assignment->remaining(), $assignment->remaining());

        if ($fedapay->isEnabled()) {
            ['payment' => $payment, 'url' => $url] = $fedapay->startCheckout($assignment, $amount, $request->user(), $data['phone'] ?? null);

            return response()->json(['data' => [
                'status' => 'redirect',
                'provider' => 'fedapay',
                'redirect_url' => $url,
                'payment_id' => $payment->id,
                'reference' => $payment->reference,
            ]], 201);
        }

        $method = $data['method'] ?? 'Mobile money';
        $result = app(PaymentGateway::class)->initiate($assignment, $amount, $method, $data['phone'] ?? null);
        $payment = $payments->createPending($assignment, $amount, $method, $result['transaction_ref']);

        $notifier->event(
            'payment.online_pending',
            'Paiement à confirmer',
            $assignment->student->full_name.' · '.number_format($amount, 0, ',', ' ').' FCFA ('.$method.')',
            $assignment->student,
            ['staff' => '/admin/payments/'.$assignment->id],
        );

        return response()->json(['data' => $result + ['payment_id' => $payment->id, 'reference' => $payment->reference]], 201);
    }

    /** POST /parent/payments/verify { reference } : au retour de FedaPay, vérifie le paiement auprès de l'API. */
    public function verifyPayment(Request $request, SchoolFedaPay $fedapay): JsonResponse
    {
        $reference = $request->validate(['reference' => ['required', 'string', 'max:50']])['reference'];
        $payment = Payment::where('reference', $reference)->firstOrFail();
        $this->ownStudent($request, $payment->student_id);

        if ($fedapay->isEnabled()) {
            try {
                $payment = $fedapay->sync($payment);
            } catch (FedaPayException) {
                // le webhook confirmera le paiement plus tard
            }
        }

        return response()->json(['data' => ['reference' => $payment->reference, 'status' => $payment->status, 'amount' => $payment->amount]]);
    }

    /** GET /parent/announcements : annonces publiées pour les parents, les classes ou les sites des enfants. */
    public function announcements(Request $request)
    {
        $students = $this->students($request);
        $classIds = $students->map(fn ($s) => $s->currentEnrollment?->class_room_id)->filter()->unique();
        $campusIds = $students->map(fn ($s) => $s->currentEnrollment?->classRoom?->campus_id)->filter()->unique();

        $items = Announcement::with('author')->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('class_room_id')->orWhereIn('class_room_id', $classIds))
            ->where(fn ($q) => $q->whereNull('campus_id')->orWhereIn('campus_id', $campusIds))
            ->latest('published_at')->limit(50)->get()
            ->filter(fn ($a) => array_intersect($a->audiences ?? ['parents'], ['parents', 'school', 'class', 'students']));

        return AnnouncementResource::collection($items->values());
    }

    /** GET /parent/documents?student_id= */
    public function documents(Request $request)
    {
        $ids = $this->students($request)->pluck('id');
        $studentId = $request->integer('student_id');
        abort_if($studentId && ! $ids->contains($studentId), 403);

        return DocumentResource::collection(Document::with('student', 'uploader')
            ->where(fn ($q) => $q->whereIn('student_id', $studentId ? [$studentId] : $ids)->orWhere('category', 'administratif'))
            ->latest()->limit(100)->get());
    }

    /** POST /parent/document-requests { student_id, type } : demande transmise au secrétariat. */
    public function requestDocument(Request $request, Notifier $notifier): JsonResponse
    {
        $data = $request->validate(['student_id' => ['required', 'integer'], 'type' => ['nullable', 'string', 'max:100']]);
        $student = $this->ownStudent($request, $data['student_id']);
        $notifier->event(
            'document.requested',
            'Demande de document',
            ($data['type'] ?? 'Certificat de scolarité').' pour '.$student->full_name.' (demandé par '.$request->user()->name.')',
            $student,
            ['staff' => '/admin/documents'],
        );

        return response()->json(['message' => 'Demande transmise au secrétariat.']);
    }

    /** GET /parent/timetable?student_id= */
    public function timetable(Request $request): JsonResponse
    {
        $student = $this->ownStudent($request, $request->integer('student_id') ?: $this->students($request)->first()?->id);

        return response()->json(['data' => TimetableEntry::with('subject', 'teacher', 'classRoom')
            ->where('class_room_id', $student->currentEnrollment?->class_room_id)
            ->orderBy('day_of_week')->orderBy('starts_at')->get()
            ->map(fn ($e) => TimetableController::present($e))]);
    }

    /**
     * Retire de la synthèse les modules absents du plan de l'école.
     *
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    protected function withoutDisabledModules(array $summary): array
    {
        $empty = [
            'grades' => ['grades' => [], 'subjects' => [], 'evolution' => [], 'report_cards' => [], 'general_average' => null, 'rank' => null],
            'attendance' => ['attendance' => [], 'absences' => 0, 'late' => 0],
            'homework' => ['homework' => []],
            'finance' => ['payments' => []],
        ];

        foreach ($empty as $feature => $values) {
            if (! tenant()->hasFeature($feature)) {
                $summary = array_merge($summary, $values);
            }
        }

        return $summary;
    }

    /** Synthèse d'un enfant, au format de l'application parent. */
    protected function summary(Student $student): array
    {
        $class = $student->currentEnrollment?->classRoom;
        $term = Term::current();
        $computed = $class && $term ? $this->cards->compute($class, $term) : null;
        $row = $computed['students'][$student->id] ?? null;
        $showRank = Setting::get('reports', 'show_rank', 'yes') === 'yes';

        $grades = Grade::with('assessment.classSubject.subject')
            ->where('student_id', $student->id)
            ->whereHas('assessment', fn ($q) => $q->where('status', 'validated'))
            ->whereNotNull('score')
            ->get()
            ->sortByDesc(fn ($g) => $g->assessment->date)
            ->values();

        $classAverages = $this->classAverages($grades);

        return [
            'id' => $student->id,
            'first_name' => $student->first_name,
            'full_name' => $student->full_name,
            'matricule' => $student->matricule,
            'class_id' => $class?->id,
            'class_name' => $class?->name,
            'school_name' => $class?->campus?->name,
            'head_teacher' => $class?->headTeacher?->full_name,
            'general_average' => $row['general_average'] ?? null,
            'rank' => $showRank ? ($row['rank'] ?? null) : null,
            'class_size' => $computed ? count($computed['students']) : 0,
            'term' => $term?->name,
            'absences' => Attendance::where('student_id', $student->id)->where('status', 'absent')->when($term?->starts_on, fn ($q) => $q->whereDate('date', '>=', $term->starts_on))->count(),
            'late' => Attendance::where('student_id', $student->id)->where('status', 'late')->when($term?->starts_on, fn ($q) => $q->whereDate('date', '>=', $term->starts_on))->count(),
            'evolution' => $grades->take(6)->reverse()->values()->map(fn ($g) => [
                'label' => $g->assessment->date->format('d/m'),
                'value' => round($g->score * 20 / $g->assessment->max_score, 1),
            ]),
            'grades' => $grades->take(30)->map(fn ($g) => [
                'id' => $g->id,
                'subject' => $g->assessment->classSubject?->subject?->name,
                'title' => $g->assessment->title,
                'date' => $g->assessment->date->toDateString(),
                'score' => $g->score,
                'max' => $g->assessment->max_score,
                'coefficient' => $g->assessment->coefficient,
                'class_average' => $classAverages[$g->assessment_id] ?? null,
                'comment' => $g->comment,
            ]),
            'subjects' => $computed ? collect($computed['subjects'])->map(fn ($info, $csId) => [
                'name' => $info['name'],
                'average' => $row['subjects'][$csId]['average'] ?? null,
            ])->filter(fn ($s) => $s['average'] !== null)->values() : [],
            'attendance' => Attendance::where('student_id', $student->id)->where('status', '!=', 'present')->latest('date')->limit(30)->get()
                ->map(fn ($a) => ['id' => $a->id, 'date' => $a->date->toDateString(), 'slot' => $a->slot, 'type' => $a->status, 'minutes' => $a->minutes_late, 'justified' => $a->is_justified, 'reason' => $a->reason]),
            'homework' => $class ? $this->homework($student, $class->id) : [],
            'payments' => FeeAssignment::with(['fee', 'lastPayment'])->where('student_id', $student->id)
                ->whereHas('fee', fn ($q) => $q->where('academic_year_id', $class?->academic_year_id))
                ->orderBy('due_date')->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'reference' => $a->lastPayment?->reference ?? '—',
                    'fee_name' => $a->fee->name.($a->fee->installments > 1 ? ', échéance '.$a->installment_no : ''),
                    'amount' => $a->amount,
                    'paid_amount' => $a->paid_amount,
                    'remaining' => $a->remaining(),
                    'method' => $a->lastPayment?->method ?? '—',
                    'paid_at' => $a->status === 'paid' ? $a->lastPayment?->paid_at?->toDateString() : null,
                    'due_date' => $a->due_date?->toDateString(),
                    'status' => $a->status === 'paid' ? 'paid' : ($a->displayStatus() === 'overdue' ? 'overdue' : 'pending'),
                ]),
            'report_cards' => $this->reportCards($student, $class?->academic_year_id),
        ];
    }

    protected function homework(Student $student, int $classId): Collection
    {
        $done = HomeworkSubmission::where('student_id', $student->id)->pluck('homework_id')->all();

        return Homework::with('subject')->where('class_room_id', $classId)->where('status', 'published')
            ->whereDate('due_date', '>=', now()->subDays(14)->toDateString())
            ->orderBy('due_date')->get()
            ->map(fn ($h) => ['id' => $h->id, 'subject' => $h->subject?->name, 'title' => $h->title, 'instructions' => $h->instructions, 'due' => $h->due_date->toDateString(), 'done' => in_array($h->id, $done)]);
    }

    protected function reportCards(Student $student, ?int $yearId): Collection
    {
        $published = ReportCard::with('term.academicYear')->where('student_id', $student->id)->where('status', 'published')->get()->keyBy('term_id');
        $terms = $yearId ? Term::where('academic_year_id', $yearId)->orderBy('position')->get() : collect();

        return $terms->map(fn ($t) => [
            'id' => $t->id,
            'term_id' => $t->id,
            'term' => $t->name,
            'status' => $published->has($t->id) ? 'published' : 'upcoming',
            'published_at' => $published->get($t->id)?->published_at,
        ])->concat($published->reject(fn ($c) => $terms->contains('id', $c->term_id))->map(fn ($c) => [
            'id' => $c->term_id,
            'term_id' => $c->term_id,
            'term' => $c->term->academicYear->name.' — '.$c->term->name,
            'status' => 'published',
            'published_at' => $c->published_at,
        ]))->values();
    }

    /** Moyenne de la classe par évaluation (sur l'échelle de l'évaluation). */
    protected function classAverages(Collection $grades): array
    {
        $ids = $grades->pluck('assessment_id')->unique();

        return Grade::whereIn('assessment_id', $ids)->whereNotNull('score')
            ->selectRaw('assessment_id, avg(score) as average')->groupBy('assessment_id')
            ->pluck('average', 'assessment_id')->map(fn ($v) => round((float) $v, 1))->all();
    }

    /** @return Collection<int, Student> */
    protected function students(Request $request): Collection
    {
        $parent = $request->user()->parentProfile;
        abort_unless($parent instanceof ParentProfile, 403, 'Espace réservé aux parents.');

        return $parent->students()
            ->with(['currentEnrollment.classRoom.campus', 'currentEnrollment.classRoom.headTeacher'])
            ->where('students.status', '!=', 'archived')
            ->orderBy('first_name')
            ->get();
    }

    protected function ownStudent(Request $request, ?int $studentId): Student
    {
        $student = $this->students($request)->firstWhere('id', $studentId);
        abort_unless($student, 403, 'Cet élève n’est pas rattaché à votre compte.');

        return $student;
    }
}
