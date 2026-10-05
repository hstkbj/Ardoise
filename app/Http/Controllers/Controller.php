<?php

namespace App\Http\Controllers;

use App\Models\Tenant\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    protected function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Pour un enseignant (sans rôle d'administration) : identifiants des
     * classes auxquelles il a accès. Null = pas de restriction.
     */
    protected function teacherClassIds(): ?array
    {
        $user = $this->user();

        if (! $user?->isTeacherOnly()) {
            return null;
        }

        return array_map('intval', $user->teacher?->classRoomIds() ?? []);
    }

    protected function teacherId(): ?int
    {
        $id = $this->user()?->isTeacherOnly() ? $this->user()->teacher?->id : null;

        return $id === null ? null : (int) $id;
    }
}
