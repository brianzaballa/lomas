<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Settings\SystemSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSystemSettingsRequest;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingsController extends Controller
{
    public function __construct(private readonly SystemSettings $settings) {}

    /**
     * Show the system settings form.
     */
    public function edit(): Response
    {
        $setting = $this->settings->get();

        return Inertia::render('settings/System', [
            'settings' => [
                'business_name' => $setting->business_name,
                'business_address' => $setting->business_address,
                'contact_email' => $setting->contact_email,
                'contact_phone' => $setting->contact_phone,
                'currency' => $setting->currency,
                'currency_symbol' => $setting->currency_symbol,
                'loan_number_format' => $setting->loan_number_format,
                'borrower_number_format' => $setting->borrower_number_format,
                'ncr_period_mode' => $setting->ncr_period_mode,
            ],
            'placeholders' => SystemSetting::FORMAT_PLACEHOLDERS,
            'updatedAt' => $setting->updated_at?->toDateTimeString(),
        ]);
    }

    /**
     * Persist the system settings and write an audit trail entry.
     */
    public function update(UpdateSystemSettingsRequest $request): RedirectResponse
    {
        $this->settings->update($request->settings(), $request->validated('reason'));

        $this->success('System settings updated.');

        return to_route('system-settings.edit');
    }
}
