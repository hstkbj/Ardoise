<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ActivityLog;
use App\Models\Tenant\Setting;
use App\Services\Payments\SchoolFedaPay;
use App\Support\NotificationEvents;
use App\Support\SettingDefaults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    protected const RULES = [
        'identity' => ['name' => 'required|string|max:150', 'primary_color' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/', 'motto' => 'nullable|string|max:150'],
        'contact' => ['address' => 'nullable|string|max:255', 'phone' => 'nullable|string|max:30', 'email' => 'nullable|email', 'legal_id' => 'nullable|string|max:60', 'tax_id' => 'nullable|string|max:60'],
        'year' => ['academic_year_id' => 'nullable|integer', 'period_type' => 'nullable|in:trimester,semester', 'week_days' => 'nullable|array'],
        'grading' => ['max_score' => 'required|numeric|min:1|max:100', 'pass_mark' => 'required|numeric|min:0', 'rounding' => 'required|in:0.01,0.1,0.25,0.5', 'lock_after_validation' => 'nullable|in:yes,no', 'composition_weight' => 'nullable|numeric|min:0|max:100'],
        'reports' => ['show_rank' => 'nullable|in:yes,no', 'show_class_average' => 'nullable|in:yes,no', 'decisions' => 'nullable|string|max:2000'],
        'notifications' => ['absence_notify' => 'nullable|in:immediate,daily,never', 'sms_sender' => 'nullable|string|max:11'],
        'notification_rules' => ['rules' => 'required|array', 'rules.*.recipients' => 'present|array', 'rules.*.recipients.*' => 'string', 'rules.*.channels' => 'present|array', 'rules.*.channels.*' => 'in:in_app,email,sms'],
        'online_payments' => ['enabled' => 'required|in:yes,no', 'environment' => 'required|in:sandbox,live', 'public_key' => 'nullable|string|max:200', 'secret_key' => 'nullable|string|max:200', 'webhook_secret' => 'nullable|string|max:200'],
        'finance' => ['currency' => 'nullable|in:XOF,XAF,GNF', 'receipt_prefix' => 'nullable|string|max:10', 'methods' => 'nullable|array', 'late_after_days' => 'nullable|integer|min:0|max:365'],
    ];

    public function show(string $section): JsonResponse
    {
        $this->authorize('settings.view');
        abort_unless(in_array($section, SettingDefaults::SECTIONS, true), 404);

        return response()->json(['data' => $this->present($section, Setting::section($section))]);
    }

    public function update(Request $request, string $section): JsonResponse
    {
        $this->authorize('settings.update');
        abort_unless(isset(self::RULES[$section]), 404);

        $values = $request->validate(self::RULES[$section]);

        if ($section === SchoolFedaPay::SECTION) {
            $values = SchoolFedaPay::prepareForStorage($values);
        }

        if ($section === 'notification_rules') {
            $values['rules'] = array_intersect_key($values['rules'], NotificationEvents::EVENTS);
        }

        foreach (['logo', 'signature'] as $file) {
            if ($request->hasFile($file)) {
                $request->validate([$file => ['image', 'max:2048']]);
                $values[$file] = Storage::disk('public')->url($request->file($file)->store('tenants/'.tenant()->code.'/branding', 'public'));
            }
        }

        $saved = Setting::put($section, $values);
        ActivityLog::record('settings.updated', null, ['section' => $section]);

        if ($section === 'identity' && ! empty($values['name'])) {
            tenant()->update(['name' => $values['name']]);
        }

        return response()->json(['data' => $this->present($section, $saved), 'message' => 'Paramètres enregistrés.']);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function present(string $section, array $values): array
    {
        return match ($section) {
            SchoolFedaPay::SECTION => SchoolFedaPay::forDisplay($values) + ['available' => tenant()->hasFeature('online_payments')],
            'notification_rules' => ['rules' => NotificationEvents::rules(), 'catalog' => NotificationEvents::catalog(), 'sms_available' => tenant()->hasFeature('sms')],
            default => $values,
        };
    }
}
