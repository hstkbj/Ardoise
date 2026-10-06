<?php

namespace App\Services;

use App\Models\Tenant\ClassRoom;
use App\Models\Tenant\ClassSubject;
use App\Models\Tenant\Enrollment;
use App\Models\Tenant\ParentProfile;
use App\Models\Tenant\Role;
use App\Models\Tenant\Student;
use App\Models\Tenant\Teacher;
use App\Models\Tenant\User;
use App\Notifications\SchoolNotification;
use App\Support\NotificationEvents;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Envoi des notifications selon les règles de l'école
 * (Paramètres → Notifications : destinataires et canaux par événement).
 *
 * Les notifications partent en file d'attente : l'action métier n'attend
 * ni l'e-mail ni le SMS.
 */
class Notifier
{
    /** Espace de chaque catégorie de destinataire : sert à choisir le lien. */
    protected const AUDIENCE = ['parents' => 'parent', 'head_teacher' => 'teacher', 'class_teachers' => 'teacher'];

    /**
     * Notifie un événement (ex. « attendance.recorded ») aux destinataires prévus par les règles.
     *
     * @param  iterable<Student>|Student|null  $students  élèves concernés (parents, professeurs)
     * @param  array{parent?: string, teacher?: string, staff?: string}  $links  lien par espace
     * @param  array<int>  $classIds  classes concernées si aucun élève n'est fourni
     */
    public function event(string $event, string $title, string $body, iterable|Student|null $students = null, array $links = [], array $classIds = []): void
    {
        $rule = NotificationEvents::rule($event);

        if ($rule['recipients'] === [] || $rule['channels'] === []) {
            return;
        }

        $studentIds = collect($students instanceof Student ? [$students] : ($students ?? []))->pluck('id')->filter()->unique()->values();
        $classIds = $classIds ?: ($studentIds->isNotEmpty() ? $this->classIdsOf($studentIds) : []);
        $type = NotificationEvents::EVENTS[$event]['type'] ?? 'system';
        $seen = [];

        foreach ($rule['recipients'] as $recipient) {
            $audience = self::AUDIENCE[$recipient] ?? 'staff';
            $users = $this->resolve($recipient, $studentIds, $classIds)
                ->reject(fn (User $user) => isset($seen[$user->id]))
                ->each(function (User $user) use (&$seen) {
                    $seen[$user->id] = true;
                });

            $this->send($users, new SchoolNotification($title, $body, $type, $links[$audience] ?? $links['staff'] ?? null, $rule['channels']));
        }
    }

    /**
     * Envoi direct à une liste d'utilisateurs (annonces : destinataires et canaux choisis à la publication).
     *
     * @param  list<string>  $channels
     */
    public function users(Collection $users, string $title, string $body, string $type = 'system', ?string $link = null, array $channels = ['in_app']): void
    {
        $this->send($users, new SchoolNotification($title, $body, $type, $link, $channels));
    }

    /**
     * @param  Collection<int, int>  $studentIds
     * @param  array<int>  $classIds
     * @return Collection<int, User>
     */
    protected function resolve(string $recipient, Collection $studentIds, array $classIds): Collection
    {
        $userIds = match ($recipient) {
            'parents' => $studentIds->isEmpty() ? collect() : ParentProfile::whereHas('students', fn ($q) => $q->whereIn('students.id', $studentIds))->pluck('user_id'),
            'head_teacher' => Teacher::whereIn('id', ClassRoom::whereIn('id', $classIds)->whereNotNull('head_teacher_id')->pluck('head_teacher_id'))->pluck('user_id'),
            'class_teachers' => Teacher::whereIn('id', ClassSubject::whereIn('class_room_id', $classIds)->whereNotNull('teacher_id')->pluck('teacher_id'))->pluck('user_id'),
            default => null,
        };

        $query = User::where('status', 'active');

        $userIds === null
            ? $query->whereHas('roles', fn ($q) => $q->where('key', $recipient))
            : $query->whereIn('id', $userIds->filter());

        return $query->get();
    }

    /**
     * @param  Collection<int, int>  $studentIds
     * @return array<int>
     */
    protected function classIdsOf(Collection $studentIds): array
    {
        return Enrollment::whereIn('student_id', $studentIds)->where('status', 'active')->pluck('class_room_id')->unique()->values()->all();
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
