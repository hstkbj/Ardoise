<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\ParentAuthController;
use App\Http\Controllers\Api\V1\Central\PublicController;
use App\Http\Controllers\Api\V1\Parent\ParentPortalController;
use App\Http\Controllers\Api\V1\Platform\BillingController;
use App\Http\Controllers\Api\V1\Platform\PlanController;
use App\Http\Controllers\Api\V1\Platform\PlatformAuthController;
use App\Http\Controllers\Api\V1\Platform\PlatformController;
use App\Http\Controllers\Api\V1\Platform\TenantController;
use App\Http\Controllers\Api\V1\Platform\TicketController;
use App\Http\Controllers\Api\V1\Tenant;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Ardoise — préfixe /api/v1
|--------------------------------------------------------------------------
| L'école courante est déterminée par le serveur (sous-domaine, jeton mobile
| ou session) : le client n'envoie jamais d'identifiant d'école.
*/

// ── Site public ───────────────────────────────────────────────────────────
Route::prefix('public')->group(function () {
    Route::get('plans', [PublicController::class, 'plans']);
    Route::post('demo-requests', [PublicController::class, 'demoRequest'])->middleware('throttle:10,1');
});
Route::post('newsletter', [PublicController::class, 'newsletter'])->middleware('throttle:10,1');

// ── Connexion (école déterminée par le domaine, ou par le code saisi) ──────
Route::middleware('tenant:optional')->group(function () {
    Route::post('parent/login', [ParentAuthController::class, 'login'])->middleware('throttle:parent-code');

    Route::prefix('auth')->middleware('throttle:login')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
    });
});

// ── Console superadmin (domaine central uniquement) ───────────────────────
Route::prefix('platform')->middleware('central')->group(function () {
    Route::post('auth/login', [PlatformAuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:platform')->group(function () {
        Route::get('auth/me', [PlatformAuthController::class, 'me']);
        Route::post('auth/logout', [PlatformAuthController::class, 'logout']);

        Route::get('dashboard', [PlatformController::class, 'dashboard']);
        Route::get('analytics', [PlatformController::class, 'dashboard']);
        Route::get('usage', [PlatformController::class, 'usage']);
        Route::get('schools', [PlatformController::class, 'schools']);
        Route::get('users', [PlatformController::class, 'users']);

        Route::post('tenants/{tenant}/suspend', [TenantController::class, 'suspend']);
        Route::post('tenants/{tenant}/activate', [TenantController::class, 'activate']);
        Route::get('tenants/{tenant}/stats', [TenantController::class, 'stats']);
        Route::apiResource('tenants', TenantController::class)->except('destroy');

        Route::apiResource('plans', PlanController::class);

        Route::get('subscriptions', [BillingController::class, 'subscriptions']);
        Route::put('subscriptions/{subscription}', [BillingController::class, 'updateSubscription']);
        Route::get('payments', [BillingController::class, 'payments']);
        Route::post('payments', [BillingController::class, 'storePayment']);

        Route::get('tickets', [TicketController::class, 'index']);
        Route::get('tickets/{ticket}', [TicketController::class, 'show']);
        Route::put('tickets/{ticket}', [TicketController::class, 'update']);
        Route::get('tickets/{ticket}/messages', [TicketController::class, 'messages']);
        Route::post('tickets/{ticket}/messages', [TicketController::class, 'reply']);

        Route::get('announcements', [PlatformController::class, 'announcements']);
        Route::post('announcements', [PlatformController::class, 'storeAnnouncement']);
        Route::get('announcements/{announcement}', [PlatformController::class, 'showAnnouncement']);
        Route::put('announcements/{announcement}', [PlatformController::class, 'updateAnnouncement']);
        Route::delete('announcements/{announcement}', [PlatformController::class, 'destroyAnnouncement']);

        Route::get('settings/{section}', [PlatformController::class, 'settings']);
        Route::put('settings/{section}', [PlatformController::class, 'updateSettings']);
    });
});

// ── Espace école (base de l'établissement) ────────────────────────────────
Route::middleware(['tenant', 'tenant.active', 'auth:sanctum', 'tenant.user', 'throttle:api'])->group(function () {

    // Tous les profils
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::put('profile', [Tenant\ProfileController::class, 'update']);
    Route::put('profile/password', [Tenant\ProfileController::class, 'password']);

    Route::get('notifications', [Tenant\NotificationController::class, 'index']);
    Route::post('notifications/read-all', [Tenant\NotificationController::class, 'markAllAsRead']);
    Route::post('notifications/{id}/read', [Tenant\NotificationController::class, 'markAsRead']);

    // Accès contrôlé dans le contrôleur (personnel, enseignant de la classe, parent de l'enfant)
    Route::get('report-cards/{student}', [Tenant\ReportCardController::class, 'show']);
    Route::get('report-cards/{student}/pdf', [Tenant\ReportCardController::class, 'pdf']);
    Route::get('documents/{document}/download', [Tenant\DocumentController::class, 'download']);
    Route::get('payments/{payment}/receipt', [Tenant\PaymentController::class, 'receipt']);

    // Personnel et enseignants
    Route::middleware('role:school_admin,director,academic_manager,accountant,secretary,teacher')->group(function () {
        Route::get('options', Tenant\OptionsController::class);
        Route::get('dashboard/school', [Tenant\DashboardController::class, 'school']);
        Route::get('teacher/dashboard', [Tenant\TeacherSpaceController::class, 'dashboard']);
        Route::get('teacher/classes', [Tenant\TeacherSpaceController::class, 'classes']);

        Route::post('schools/{campus}/toggle', [Tenant\CampusController::class, 'toggle']);
        Route::apiResource('schools', Tenant\CampusController::class)->parameters(['schools' => 'campus']);

        Route::post('academic-years/{academicYear}/set_active', [Tenant\AcademicYearController::class, 'setActive']);
        Route::post('academic-years/{academicYear}/close', [Tenant\AcademicYearController::class, 'close']);
        Route::apiResource('academic-years', Tenant\AcademicYearController::class)->parameters(['academic-years' => 'academicYear']);

        Route::put('classes/{class}/subjects', [Tenant\ClassRoomController::class, 'syncSubjects']);
        Route::apiResource('classes', Tenant\ClassRoomController::class);

        Route::post('students/bulk/{action}', [Tenant\StudentController::class, 'bulk']);
        Route::post('students/{student}/{action}', [Tenant\StudentController::class, 'action'])->where('action', 'archive|transfer|change-class|restore');
        Route::apiResource('students', Tenant\StudentController::class);

        Route::post('parents/{parent}/regenerate-code', [Tenant\ParentController::class, 'regenerateCode']);
        Route::post('parents/{parent}/toggle', [Tenant\ParentController::class, 'toggle']);
        Route::apiResource('parents', Tenant\ParentController::class);

        Route::apiResource('teachers', Tenant\TeacherController::class);
        Route::apiResource('subjects', Tenant\SubjectController::class);

        Route::get('assessments/{assessment}/grades', [Tenant\AssessmentController::class, 'sheet']);
        Route::put('assessments/{assessment}/grades', [Tenant\AssessmentController::class, 'saveGrades']);
        Route::post('assessments/{assessment}/validate', [Tenant\AssessmentController::class, 'validateGrades']);
        Route::post('assessments/{assessment}/unlock', [Tenant\AssessmentController::class, 'unlock']);
        Route::apiResource('assessments', Tenant\AssessmentController::class);

        Route::get('report-cards', [Tenant\ReportCardController::class, 'index']);
        Route::post('report-cards/generate', [Tenant\ReportCardController::class, 'generate']);
        Route::post('report-cards/publish', [Tenant\ReportCardController::class, 'publish']);
        Route::put('report-cards/{student}', [Tenant\ReportCardController::class, 'update']);

        Route::get('attendance/session', [Tenant\AttendanceController::class, 'session']);
        Route::post('attendance/session', [Tenant\AttendanceController::class, 'saveSession']);
        Route::get('attendance', [Tenant\AttendanceController::class, 'index']);
        Route::post('attendance/{attendance}/justify', [Tenant\AttendanceController::class, 'justify']);

        Route::get('timetables', [Tenant\TimetableController::class, 'index']);
        Route::post('timetables/entries', [Tenant\TimetableController::class, 'store']);
        Route::put('timetables/entries/{entry}', [Tenant\TimetableController::class, 'update']);
        Route::delete('timetables/entries/{entry}', [Tenant\TimetableController::class, 'destroy']);

        Route::apiResource('homework', Tenant\HomeworkController::class)->parameters(['homework' => 'homework']);
        Route::apiResource('announcements', Tenant\AnnouncementController::class);

        Route::apiResource('fees', Tenant\FeeController::class);
        Route::get('payments/summary', [Tenant\PaymentController::class, 'summary']);
        Route::post('payments/records/{record}/confirm', [Tenant\PaymentController::class, 'confirm']);
        Route::post('payments/records/{record}/cancel', [Tenant\PaymentController::class, 'cancel']);
        Route::get('payments', [Tenant\PaymentController::class, 'index']);
        Route::post('payments', [Tenant\PaymentController::class, 'store']);
        Route::get('payments/{payment}', [Tenant\PaymentController::class, 'show']);

        Route::get('documents', [Tenant\DocumentController::class, 'index']);
        Route::post('documents', [Tenant\DocumentController::class, 'store']);
        Route::delete('documents/{document}', [Tenant\DocumentController::class, 'destroy']);

        Route::post('users/bulk/{action}', [Tenant\UserController::class, 'bulk']);
        Route::post('users/{user}/toggle', [Tenant\UserController::class, 'toggle']);
        Route::apiResource('users', Tenant\UserController::class);

        Route::get('permissions', [Tenant\RoleController::class, 'permissions']);
        Route::put('roles/{role}/permissions', [Tenant\RoleController::class, 'syncPermissions']);
        Route::apiResource('roles', Tenant\RoleController::class);

        Route::get('settings/{section}', [Tenant\SettingController::class, 'show']);
        Route::post('settings/{section}', [Tenant\SettingController::class, 'update']);
        Route::put('settings/{section}', [Tenant\SettingController::class, 'update']);

        Route::get('support-tickets', [Tenant\SupportTicketController::class, 'index']);
        Route::post('support-tickets', [Tenant\SupportTicketController::class, 'store']);
        Route::get('support-tickets/{id}', [Tenant\SupportTicketController::class, 'show'])->whereNumber('id');
        Route::get('support-tickets/{id}/messages', [Tenant\SupportTicketController::class, 'messages'])->whereNumber('id');
        Route::post('support-tickets/{id}/messages', [Tenant\SupportTicketController::class, 'reply'])->whereNumber('id');
    });

    // Parents (application mobile et web)
    Route::prefix('parent')->middleware('role:parent')->group(function () {
        Route::get('children', [ParentPortalController::class, 'children']);
        Route::get('children/{student}', [ParentPortalController::class, 'child'])->whereNumber('student');
        Route::get('announcements', [ParentPortalController::class, 'announcements']);
        Route::get('documents', [ParentPortalController::class, 'documents']);
        Route::post('document-requests', [ParentPortalController::class, 'requestDocument']);
        Route::get('timetable', [ParentPortalController::class, 'timetable']);
        Route::post('attendance/{attendance}/justify', [ParentPortalController::class, 'justify']);
        Route::post('homework/{homework}/done', [ParentPortalController::class, 'homeworkDone']);
        Route::post('payments/{assignment}/checkout', [ParentPortalController::class, 'checkout']);
    });
});
