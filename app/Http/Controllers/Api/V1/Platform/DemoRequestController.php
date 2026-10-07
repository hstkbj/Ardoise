<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Models\Central\DemoRequest;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DemoRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = ListQuery::paginate(
            DemoRequest::query(),
            $request,
            search: ['name', 'school', 'city', 'email', 'phone'],
            sorts: ['created_at' => 'created_at', 'school' => 'school'],
            default: '-id',
        );

        $page->getCollection()->transform(fn (DemoRequest $demoRequest) => [
            'id' => $demoRequest->id,
            'name' => $demoRequest->name,
            'role' => $demoRequest->role,
            'school' => $demoRequest->school,
            'city' => $demoRequest->city,
            'email' => $demoRequest->email,
            'phone' => $demoRequest->phone,
            'students' => $demoRequest->students,
            'sites' => $demoRequest->sites,
            'message' => $demoRequest->message,
            'created_at' => $demoRequest->created_at?->toDateTimeString(),
        ]);

        return ListQuery::json($page);
    }
}
