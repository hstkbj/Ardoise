<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentLineResource;
use App\Http\Resources\StudentBalanceResource;
use App\Models\Tenant\AcademicYear;
use App\Models\Tenant\Fee;
use App\Models\Tenant\FeeAssignment;
use App\Models\Tenant\Payment;
use App\Models\Tenant\Setting;
use App\Models\Tenant\Student;
use App\Services\PaymentService;
use App\Support\ListQuery;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Écran « Paiements » : une ligne par échéance (payée, partielle, en attente, en retard).
 * Les encaissements eux-mêmes sont des Payment rattachés à l'échéance.
 */
class PaymentController extends Controller
{
    public function __construct(protected PaymentService $payments) {}

    public function index(Request $request)
    {
        $this->authorize('payments.view');

        $query = FeeAssignment::with(['fee', 'student.currentEnrollment.classRoom', 'lastPayment'])
            ->whereHas('fee', fn ($q) => $q->where('academic_year_id', $request->input('filter.academic_year_id') ?: AcademicYear::current()?->id))
            ->addSelect(['last_paid_at' => Payment::select('paid_at')->whereColumn('payments.fee_assignment_id', 'fee_assignments.id')->where('status', 'paid')->latest('paid_at')->limit(1)]);

        return PaymentLineResource::collection(ListQuery::paginate($query, $request,
            search: ['student.first_name', 'student.last_name', 'student.matricule', 'fee.name', fn ($q, $t) => $q->whereHas('payments', fn ($p) => $p->where('reference', 'like', "%{$t}%"))],
            filters: [
                'status' => fn ($q, $v) => $q->withDisplayStatus($v),
                'student_id' => 'student_id',
                'fee_id' => 'fee_id',
                'method' => fn ($q, $v) => $q->whereHas('payments', fn ($p) => $p->where('method', $v)),
                'class_id' => fn ($q, $v) => $q->whereHas('student.currentEnrollment', fn ($e) => $e->where('class_room_id', $v)),
                'academic_year_id' => fn ($q) => $q,
                'has_pending' => fn ($q, $v) => $q->whereHas('payments', fn ($p) => $p->where('status', 'pending')),
                'due_period' => fn ($q, $v) => match ($v) {
                    'upcoming' => $q->where('status', '!=', 'paid')->whereDate('due_date', '>=', now()->toDateString())->whereDate('due_date', '<=', now()->addDays(30)->toDateString()),
                    'today' => $q->where('status', '!=', 'paid')->whereDate('due_date', now()->toDateString()),
                    'overdue' => $q->where('status', '!=', 'paid')->whereDate('due_date', '<', now()->toDateString()),
                    default => $q,
                },
            ],
            sorts: ['paid_at' => 'last_paid_at', 'amount' => 'amount', 'due_date' => 'due_date', 'reference' => 'last_paid_at'],
            default: '-paid_at',
        ));
    }

    /**
     * GET /payments/students?class_id=&search=&status= : les élèves d'une classe avec leur solde de frais.
     * status : late (en retard) | remaining (reste à payer) | settled (soldé) | none (aucun frais)
     */
    public function students(Request $request)
    {
        $this->authorize('payments.view');

        $feeIds = Fee::where('academic_year_id', AcademicYear::current()?->id)->pluck('id');
        $today = now()->toDateString();
        $of = fn ($query) => $query->from('fee_assignments')->whereColumn('fee_assignments.student_id', 'students.id')->whereIn('fee_assignments.fee_id', $feeIds);
        $sub = fn (string $expression) => $of(DB::connection('tenant')->query())->selectRaw($expression);

        $query = Student::query()
            ->select('students.*')
            ->addSelect([
                'expected' => $sub('coalesce(sum(fee_assignments.amount), 0)'),
                'paid' => $sub('coalesce(sum(fee_assignments.paid_amount), 0)'),
                'overdue_count' => $sub('count(*)')->where('fee_assignments.status', '!=', 'paid')->whereDate('fee_assignments.due_date', '<', $today),
            ])
            ->with('currentEnrollment.classRoom')
            ->where('students.status', 'active');

        return StudentBalanceResource::collection(ListQuery::paginate($query, $request,
            search: ['first_name', 'last_name', 'matricule', fn ($q, $t) => $q->whereRaw('('.ListQuery::concat('tenant', 'last_name', 'first_name').') like ?', ["%{$t}%"])],
            filters: [
                'class_id' => fn ($q, $v) => $q->whereHas('currentEnrollment', fn ($e) => $e->where('class_room_id', $v)),
                'status' => fn ($q, $v) => match ($v) {
                    'late' => $q->whereExists(fn ($e) => $of($e)->where('fee_assignments.status', '!=', 'paid')->whereDate('fee_assignments.due_date', '<', $today)),
                    'remaining' => $q->whereExists(fn ($e) => $of($e)->where('fee_assignments.status', '!=', 'paid')),
                    'settled' => $q->whereExists(fn ($e) => $of($e))->whereNotExists(fn ($e) => $of($e)->where('fee_assignments.status', '!=', 'paid')),
                    'none' => $q->whereNotExists(fn ($e) => $of($e)),
                    default => $q,
                },
            ],
            sorts: ['name' => fn ($q, $dir) => $q->orderBy('students.last_name', $dir)->orderBy('students.first_name', $dir)],
            default: 'name',
        ));
    }

    /** GET /payments/students/{student} : frais de l'année d'un élève, ce qui est payé et ce qui reste. */
    public function student(Student $student): JsonResponse
    {
        $this->authorize('payments.view');

        $yearId = AcademicYear::current()?->id;
        $lines = FeeAssignment::with(['fee', 'student.currentEnrollment.classRoom', 'lastPayment', 'payments.receiver'])
            ->where('student_id', $student->id)
            ->whereHas('fee', fn ($q) => $q->where('academic_year_id', $yearId))
            ->orderBy('due_date')->get();

        $expected = (int) $lines->sum('amount');
        $paid = (int) $lines->sum('paid_amount');
        $overdue = $lines->filter(fn (FeeAssignment $l) => $l->displayStatus() === 'overdue');
        $class = $student->currentEnrollment?->classRoom;

        return response()->json(['data' => [
            'student' => ['id' => $student->id, 'full_name' => $student->full_name, 'matricule' => $student->matricule, 'class_id' => $class?->id, 'class_name' => $class?->name],
            'totals' => [
                'expected' => $expected,
                'paid' => $paid,
                'remaining' => max(0, $expected - $paid),
                'overdue_amount' => (int) $overdue->sum(fn (FeeAssignment $l) => $l->remaining()),
                'status' => StudentBalanceResource::statusFor($expected, $paid, $overdue->count()),
            ],
            'fees' => PaymentLineResource::collection($lines)->resolve(),
        ]]);
    }

    public function show(FeeAssignment $payment): PaymentLineResource
    {
        $this->authorize('payments.view');

        return new PaymentLineResource($payment->load(['fee', 'student.currentEnrollment.classRoom', 'lastPayment', 'payments.receiver']));
    }

    /** POST /payments { student_id, fee_id, paid_amount, method, paid_at, transaction_ref } */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('payments.create');
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:tenant.students,id'],
            'fee_id' => ['required_without:fee_assignment_id', 'nullable', 'integer', 'exists:tenant.fees,id'],
            'fee_assignment_id' => ['nullable', 'integer'],
            'paid_amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'string', 'max:50'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
            'transaction_ref' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $feeId = $data['fee_id'] ?? FeeAssignment::findOrFail($data['fee_assignment_id'])->fee_id;
        $created = $this->payments->record(
            Student::findOrFail($data['student_id']),
            Fee::findOrFail($feeId),
            $data['paid_amount'],
            $data['method'],
            $data['paid_at'] ?? null,
            $data['transaction_ref'] ?? null,
            $data['note'] ?? null,
        );

        return response()->json([
            'message' => 'Paiement enregistré.',
            'data' => $created->map(fn (Payment $p) => ['id' => $p->id, 'reference' => $p->reference, 'amount' => $p->amount, 'fee_assignment_id' => $p->fee_assignment_id]),
        ], 201);
    }

    /** POST /payments/records/{record}/confirm : paiement en ligne reçu */
    public function confirm(Payment $record): JsonResponse
    {
        $this->authorize('payments.update');

        return response()->json(['message' => 'Paiement confirmé.', 'data' => $this->payments->confirm($record)]);
    }

    public function cancel(Payment $record): JsonResponse
    {
        $this->authorize('payments.update');
        $this->payments->cancel($record);

        return response()->json(['message' => 'Paiement annulé.']);
    }

    /** GET /payments/summary : tableau de bord financier de l'année. */
    public function summary(Request $request): JsonResponse
    {
        $this->authorize('payments.view');
        $yearId = $request->integer('academic_year_id') ?: AcademicYear::current()?->id;
        $base = FeeAssignment::whereHas('fee', fn ($q) => $q->where('academic_year_id', $yearId));
        $lateDays = (int) Setting::get('finance', 'late_after_days', 30);

        $expected = (int) (clone $base)->sum('amount');
        $collected = (int) (clone $base)->sum('paid_amount');
        $overdue = (clone $base)->where('status', '!=', 'paid')->whereDate('due_date', '<', now()->subDays($lateDays)->toDateString());
        $dueSoon = (clone $base)->where('status', '!=', 'paid')->whereDate('due_date', '>=', now()->toDateString())->whereDate('due_date', '<=', now()->addDays(30)->toDateString());
        $overdueAll = (clone $base)->where('status', '!=', 'paid')->whereDate('due_date', '<', now()->toDateString());

        return response()->json(['data' => [
            'expected' => $expected,
            'collected' => $collected,
            'remaining' => $expected - $collected,
            'due_soon_amount' => (int) (clone $dueSoon)->sum(DB::raw('amount - paid_amount')),
            'due_soon_count' => (int) (clone $dueSoon)->count(),
            'overdue_amount' => (int) (clone $overdueAll)->sum(DB::raw('amount - paid_amount')),
            'overdue_count' => (int) (clone $overdueAll)->count(),
            'pending' => (int) (clone $base)->where('status', '!=', 'paid')->sum(DB::raw('amount - paid_amount')),
            'late_families' => (clone $overdue)->distinct()->count('student_id'),
            'late_amount' => (int) (clone $overdue)->sum(DB::raw('amount - paid_amount')),
            'pending_online' => Payment::where('status', 'pending')->count(),
        ]]);
    }

    /** GET /payments/{line}/receipt : reçu du dernier encaissement de l'échéance (PDF/HTML). */
    public function receipt(Request $request, FeeAssignment $payment): Response
    {
        $user = $request->user();
        abort_unless($user->can('payments.view') || $user->parentProfile?->hasChild($payment->student_id), 403);

        $record = $request->integer('record')
            ? $payment->payments()->whereKey($request->integer('record'))->firstOrFail()
            : $payment->payments()->where('status', 'paid')->firstOrFail();

        $html = view('pdf.receipt', [
            'payment' => $record,
            'line' => $payment->load('fee', 'student.currentEnrollment.classRoom'),
            'school' => Setting::section('identity')['name'] ?? tenant()->name,
            'contact' => Setting::section('contact'),
        ])->render();

        if (class_exists(Pdf::class)) {
            return Pdf::loadHTML($html)->setPaper('a5')->download('recu-'.$record->reference.'.pdf');
        }

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
