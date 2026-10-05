<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ClassRoom;
use App\Models\Tenant\ReportCard;
use App\Models\Tenant\Student;
use App\Models\Tenant\Term;
use App\Models\Tenant\User;
use App\Services\ReportCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportCardController extends Controller
{
    public function __construct(protected ReportCardService $cards) {}

    /** GET /report-cards?class_id=&term_id= : bulletins de la classe (ou aperçu s'ils ne sont pas générés). */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('report_cards.view');
        $data = $request->validate(['class_id' => ['required', 'integer'], 'term_id' => ['nullable', 'integer']]);

        $class = ClassRoom::findOrFail($data['class_id']);
        $this->ensureClassVisible($class);
        $term = $this->term($data['term_id'] ?? null);
        $cards = ReportCard::where('class_room_id', $class->id)->where('term_id', $term->id)->get()->keyBy('student_id');

        if ($cards->isNotEmpty()) {
            $rows = $class->students()->get()->map(fn (Student $s) => [
                'id' => $s->id,
                'student_name' => $s->full_name,
                'matricule' => $s->matricule,
                'class_name' => $class->name,
                'general_average' => $cards->get($s->id)?->general_average,
                'rank' => $cards->get($s->id)?->rank,
                'status' => $cards->get($s->id)?->status ?? 'draft',
                'generated' => $cards->has($s->id),
            ]);
        } else {
            $computed = $this->cards->compute($class, $term);
            $rows = collect($computed['students'])->map(fn ($row) => [
                'id' => $row['student']->id,
                'student_name' => $row['student']->full_name,
                'matricule' => $row['student']->matricule,
                'class_name' => $class->name,
                'general_average' => $row['general_average'],
                'rank' => $row['rank'],
                'status' => 'draft',
                'generated' => false,
            ])->values();
        }

        return response()->json([
            'data' => $rows->sortBy(fn ($r) => $r['rank'] ?? PHP_INT_MAX)->values(),
            'meta' => ['term' => ['id' => $term->id, 'name' => $term->name], 'generated' => $cards->isNotEmpty()],
        ]);
    }

    /** GET /report-cards/{student}?term_id= */
    public function show(Request $request, Student $student): JsonResponse
    {
        $term = $this->term($request->integer('term_id') ?: null);
        $this->ensureCanSee($request->user(), $student, $term);

        return response()->json(['data' => $this->cards->present($student, $term)]);
    }

    /** PUT /report-cards/{student} { term_id, council_decision, head_teacher_comment } */
    public function update(Request $request, Student $student): JsonResponse
    {
        $this->authorize('report_cards.create');
        $data = $request->validate([
            'term_id' => ['required', 'integer'],
            'council_decision' => ['nullable', 'string', 'max:100'],
            'head_teacher_comment' => ['nullable', 'string', 'max:255'],
        ]);

        $card = ReportCard::where('student_id', $student->id)->where('term_id', $data['term_id'])->firstOrFail();
        $card->update(collect($data)->only(['council_decision', 'head_teacher_comment'])->all());

        return response()->json(['data' => $this->cards->present($student, $card->term)]);
    }

    /** POST /report-cards/generate { class_id, term_id } */
    public function generate(Request $request): JsonResponse
    {
        $this->authorize('report_cards.create');
        $data = $request->validate(['class_id' => ['required', 'integer', 'exists:tenant.class_rooms,id'], 'term_id' => ['nullable', 'integer']]);

        $count = $this->cards->generate(ClassRoom::findOrFail($data['class_id']), $this->term($data['term_id'] ?? null));

        return response()->json(['message' => "{$count} bulletin(s) générés.", 'count' => $count]);
    }

    /** POST /report-cards/publish { ids: [student_id], term_id } */
    public function publish(Request $request): JsonResponse
    {
        $this->authorize('report_cards.publish');
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer'], 'term_id' => ['nullable', 'integer']]);

        $term = $this->term($data['term_id'] ?? null);
        $missing = collect($data['ids'])->diff(ReportCard::where('term_id', $term->id)->whereIn('student_id', $data['ids'])->pluck('student_id'));

        abort_if($missing->isNotEmpty(), 422, 'Générez d’abord les bulletins avant de les publier.');

        $count = $this->cards->publish($data['ids'], $term);

        return response()->json(['message' => "{$count} bulletin(s) publiés aux parents.", 'count' => $count]);
    }

    /** GET /report-cards/{student}/pdf : PDF si dompdf est installé, sinon HTML imprimable. */
    public function pdf(Request $request, Student $student): Response
    {
        $term = $this->term($request->integer('term_id') ?: null);
        $this->ensureCanSee($request->user(), $student, $term);
        $html = view('pdf.report-card', ['report' => $this->cards->present($student, $term)])->render();
        $filename = 'bulletin-'.str($student->full_name)->slug().'-'.str($term->name)->slug().'.pdf';

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4')->download($filename);
        }

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    protected function term(?int $id): Term
    {
        $term = $id ? Term::find($id) : Term::current();
        abort_unless($term, 422, 'Aucune période définie.');

        return $term->load('academicYear');
    }

    protected function ensureClassVisible(ClassRoom $class): void
    {
        $ids = $this->teacherClassIds();
        abort_if($ids !== null && ! in_array((int) $class->id, $ids, true), 403);
    }

    /** Personnel : permission ; enseignant : sa classe ; parent : son enfant, bulletin publié. */
    protected function ensureCanSee(User $user, Student $student, Term $term): void
    {
        if ($user->hasRole('parent') && ! $user->isStaff() && ! $user->hasRole('teacher')) {
            abort_unless($user->parentProfile?->hasChild($student->id), 403);
            abort_unless(ReportCard::where('student_id', $student->id)->where('term_id', $term->id)->where('status', 'published')->exists(), 404, 'Ce bulletin n’est pas encore publié.');

            return;
        }

        abort_unless($user->can('report_cards.view'), 403);
        $ids = $this->teacherClassIds();
        abort_if($ids !== null && ! in_array((int) $student->currentClass()?->id, $ids, true), 403);
    }
}
