<?php

namespace App\Http\Controllers;

use App\Domain\Settings\SystemSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly SystemSettings $settings) {}

    /**
     * Operational landing page.
     */
    public function index(Request $request): Response
    {
        $setting = $this->settings->get();

        return Inertia::render('Dashboard', [
            'settings' => [
                'business_name' => $setting->business_name,
                'currency' => $setting->currency,
                'currency_symbol' => $setting->currency_symbol,
                'loan_number_format' => $setting->loan_number_format,
                'borrower_number_format' => $setting->borrower_number_format,
            ],
        ]);
    }
}
