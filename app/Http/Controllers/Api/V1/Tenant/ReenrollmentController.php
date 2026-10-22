<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AcademicYear;
use App\Services\ReenrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Réinscription des élèves de l'établissement dans l'année courante. */
class ReenrollmentController extends Controller
{
    public function __construct(protected ReenrollmentService $service) {}

    /** GET /reenrollment?class_id=&school_id= : élèves à réinscrire, avec la classe suggérée. */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('students.update');
        $year = AcademicYear::assertOpen();

        return response()->json(['data' => [
            'year' => ['id' => $year->id, 'name' => $year->name],
            'fees_defined' => $this->service->hasFees($year),
            'students' => $this->service->pending($year, $request->integer('class_id') ?: null, $request->integer('school_id') ?: null),
        ]]);
    }

    /** POST /reenrollment { items: [{ student_id, class_id }] } */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('students.update');
        $year = AcademicYear::assertOpen();

        $items = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.student_id' => ['required', 'integer'],
            'items.*.class_id' => ['required', 'integer', 'exists:tenant.class_rooms,id'],
        ])['items'];

        $result = $this->service->reenroll($year, $items);

        return response()->json([
            'data' => $result,
            'message' => $result['enrolled'].' élève(s) réinscrit(s) pour '.$year->name.'.'.($result['skipped'] ? ' '.$result['skipped'].' ignoré(s) (déjà inscrits).' : ''),
        ]);
    }
}
