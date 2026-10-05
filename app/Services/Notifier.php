<?php

namespace App\Services;

use App\Models\Tenant\ParentProfile;
use App\Models\Tenant\Role;
use App\Models\Tenant\Student;
use App\Models\Tenant\User;
use App\Notifications\SchoolNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/** Envoi groupé des notifications aux parents, enseignants ou au personnel. */
class Notifier
{
    /** @param  iterable<Student>|Student  $students */
    public function parentsOf(iterable|Student $students, string $title, string $body, ?string $link = null, bool $urgent = false): void
    {
        $ids = collect($students instanceof Student ? [$students] : $students)->pluck('id');

        $users = User::whereIn('id', ParentProfile::whereHas('students', fn ($q) => $q->whereIn('students.id', $ids))->pluck('user_id'))
            ->where('status', 'active')
            ->get();

        $this->send($users, new SchoolNotification($title, $body, 'parent', $link, $urgent));
    }

    public function roles(array $roleKeys, string $title, string $body, string $type = 'admin', ?string $link = null): void
    {
        $users = User::whereHas('roles', fn ($q) => $q->whereIn('key', $roleKeys))->where('status', 'active')->get();

        $this->send($users, new SchoolNotification($title, $body, $type, $link));
    }

    public function users(Collection $users, string $title, string $body, string $type = 'system', ?string $link = null, bool $urgent = false): void
    {
        $this->send($users, new SchoolNotification($title, $body, $type, $link, $urgent));
    }

    /** Une notification qui échoue (SMS, e-mail) ne doit jamais bloquer l'action métier. */
    protected function send(Collection $users, SchoolNotification $notification): void
    {
        if ($users->isEmpty()) {
            return;
        }

        try {
            Notification::send($users, $notification);
        } catch (Throwable $e) {
            Log::error('Notification non envoyée : '.$e->getMessage(), ['tenant' => tenant()?->code]);
        }
    }

    public static function roleExists(string $key): bool
    {
        return Role::where('key', $key)->exists();
    }
}
