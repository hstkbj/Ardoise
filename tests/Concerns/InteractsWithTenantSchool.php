<?php

namespace Tests\Concerns;

use App\Actions\CreateTenant;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Tenant\AcademicYear;
use App\Models\Tenant\Campus;
use App\Models\Tenant\ClassRoom;
use App\Models\Tenant\Fee;
use App\Models\Tenant\FeeAssignment;
use App\Models\Tenant\Level;
use App\Models\Tenant\Role;
use App\Models\Tenant\Student;
use App\Models\Tenant\Teacher;
use App\Models\Tenant\User;
use App\Services\AccountService;
use App\Services\EnrollmentService;
use App\Services\FeeService;
use App\Tenancy\DatabaseCreator;
use App\Tenancy\TenantManager;
use App\Tenancy\TenantResolver;
use Database\Seeders\DatabaseSeeder;

/**
 * Crée une vraie école (base SQLite dédiée) pour les tests, et la supprime ensuite.
 * Les requêtes passent par le domaine central avec un jeton préfixé t_{code}.
 */
trait InteractsWithTenantSchool
{
    protected ?Tenant $tenant = null;

    protected function createSchool(array $attributes = []): Tenant
    {
        $this->seed(DatabaseSeeder::class);

        $this->tenant = app(CreateTenant::class)->handle($attributes + [
            'name' => 'École Test',
            'code' => 'test-'.substr(uniqid(), -6),
            'admin_name' => 'Awa Admin',
            'admin_email' => 'admin@ecole.test',
            'admin_password' => 'admin-password',
            'status' => 'active',
            'expires_at' => now()->addMonths(6)->toDateString(),
            'plan_id' => Plan::where('name', 'Groupe scolaire')->value('id'),
            'send_credentials' => false,
        ])['tenant'];

        return $this->tenant;
    }

    protected function tearDownSchool(): void
    {
        if ($this->tenant) {
            app(TenantManager::class)->disconnect();
            app(DatabaseCreator::class)->drop($this->tenant);
            $this->tenant = null;
        }
    }

    protected function inSchool(callable $callback): mixed
    {
        return app(TenantManager::class)->run($this->tenant->fresh(), $callback);
    }

    /** Jeton Bearer d'un membre de l'école (créé si besoin avec le rôle donné). */
    protected function tokenFor(string $role = 'school_admin', ?string $email = null): string
    {
        $token = $this->inSchool(function () use ($role, $email) {
            $user = User::firstOrCreate(
                ['email' => $email ?? $role.'@ecole.test'],
                ['name' => ucfirst($role).' Test', 'password' => 'password', 'status' => 'active'],
            );
            $user->roles()->syncWithoutDetaching([Role::where('key', $role)->value('id')]);

            return $user->createToken('tests')->plainTextToken;
        });

        return TenantResolver::prefixToken($this->tenant, $token);
    }

    /**
     * Établissement, classe, élève, parent (avec code) et une échéance de frais.
     *
     * @return array{student_id: int, class_id: int, parent_user_id: int, parent_code: string, assignment_id: int, head_teacher_user_id: int}
     */
    protected function seedSchoolData(int $feeAmount = 150000): array
    {
        return $this->inSchool(function () use ($feeAmount) {
            $year = AcademicYear::current();
            $campus = Campus::create(['name' => 'Campus Centre', 'code' => 'CC', 'status' => 'active']);
            $teacher = Teacher::create(['first_name' => 'Moussa', 'last_name' => 'Diallo', 'email' => 'prof@ecole.test', 'status' => 'active']);
            app(AccountService::class)->ensureTeacherAccount($teacher)->update(['status' => 'active']);
            $teacher->refresh();

            $class = ClassRoom::create([
                'name' => '5e B', 'level_id' => Level::where('name', '5e')->value('id'), 'campus_id' => $campus->id,
                'academic_year_id' => $year->id, 'head_teacher_id' => $teacher->id, 'status' => 'active',
            ]);
            $student = Student::create(['matricule' => 'T-0001', 'first_name' => 'Awa', 'last_name' => 'TRAORÉ', 'gender' => 'F', 'status' => 'active']);
            app(EnrollmentService::class)->enroll($student, $class, $year->starts_on->toDateString());

            [$parent, $code] = app(AccountService::class)->createParent(['first_name' => 'Mariam', 'last_name' => 'Traoré', 'phone' => '+22997000001']);
            $parent->students()->attach($student->id, ['relation' => 'Mère', 'is_primary' => true]);
            $parent->user->update(['email' => 'parent@ecole.test']);

            $fee = Fee::create(['academic_year_id' => $year->id, 'name' => 'Scolarité', 'category' => 'scolarite', 'amount' => $feeAmount, 'installments' => 1, 'first_due_date' => now()->addDays(10)->toDateString(), 'status' => 'active']);
            app(FeeService::class)->assign($fee);

            return [
                'student_id' => $student->id,
                'class_id' => $class->id,
                'parent_user_id' => $parent->user_id,
                'parent_code' => $code,
                'assignment_id' => FeeAssignment::where('student_id', $student->id)->value('id'),
                'head_teacher_user_id' => $teacher->user_id,
            ];
        });
    }
}
