<?php

namespace Tests\Feature;

use App\Mail\SubscriptionReminderMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithTenantSchool;
use Tests\TestCase;

/** Rappels de renouvellement envoyés par la tâche quotidienne. */
class SubscriptionRemindersTest extends TestCase
{
    use InteractsWithTenantSchool, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownSchool();
        parent::tearDown();
    }

    public function test_a_reminder_is_sent_once_seven_days_before_the_end(): void
    {
        Mail::fake();
        $this->freezeTime();
        $this->createSchool()->update(['expires_at' => now()->addDays(7)]);

        $this->artisan('subscriptions:check')->assertSuccessful();
        $this->artisan('subscriptions:check')->assertSuccessful();

        Mail::assertQueuedCount(1);
        Mail::assertQueued(SubscriptionReminderMail::class, fn ($mail) => $mail->kind === 'ending' && $mail->hasTo('admin@ecole.test'));
    }

    public function test_the_admin_is_told_when_access_is_blocked(): void
    {
        Mail::fake();
        $this->freezeTime();
        $this->createSchool()->update(['expires_at' => now()->subDays(30)]);

        $this->artisan('subscriptions:check')->assertSuccessful();

        Mail::assertQueued(SubscriptionReminderMail::class, fn ($mail) => $mail->kind === 'expired' && str_contains($mail->render(), '/admin/billing'));
    }

    public function test_no_reminder_far_from_the_end(): void
    {
        Mail::fake();
        $this->createSchool()->update(['expires_at' => now()->addDays(40)]);

        $this->artisan('subscriptions:check')->assertSuccessful();

        Mail::assertNothingQueued();
    }
}
