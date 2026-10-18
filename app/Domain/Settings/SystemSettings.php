<?php

namespace App\Domain\Settings;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Models\SystemSetting;
use App\Support\Money;
use NumberFormatter;

/**
 * Read and update access to the single row of system wide configuration.
 */
class SystemSettings
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * The single settings row, created with the defaults on first use.
     *
     * The identifier is written explicitly because the auto increment counter
     * is not guaranteed to hand out this value, and a second settings row would
     * silently split the configuration of the system.
     */
    public function get(): SystemSetting
    {
        $setting = SystemSetting::query()->find(SystemSetting::SINGLETON_ID);

        if ($setting instanceof SystemSetting) {
            return $setting;
        }

        return SystemSetting::query()->forceCreate(
            array_merge(SystemSetting::DEFAULTS, ['id' => SystemSetting::SINGLETON_ID]),
        );
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function update(array $values, ?string $reason = null): SystemSetting
    {
        $setting = $this->get();

        $old = $setting->only(array_keys($values));

        $setting->fill($values)->save();

        $this->audit->recordUpdated(
            AuditAction::SETTINGS_UPDATED,
            $setting,
            $old,
            $setting->only(array_keys($values)),
            $reason,
        );

        return $setting;
    }

    public function businessName(): string
    {
        return $this->get()->business_name;
    }

    public function currencyCode(): string
    {
        return $this->get()->currency;
    }

    public function currencySymbol(): string
    {
        return $this->get()->currency_symbol;
    }

    public function loanNumberFormat(): string
    {
        return $this->get()->loan_number_format;
    }

    public function borrowerNumberFormat(): string
    {
        return $this->get()->borrower_number_format;
    }

    /**
     * The period mode the Notes Collection Report posts in.
     *
     * Anything other than a known mode falls back to weekly so a stale or
     * hand-edited row can never make period resolution unbounded.
     */
    public function ncrPeriodMode(): string
    {
        $mode = $this->get()->ncr_period_mode;

        return in_array($mode, SystemSetting::NCR_PERIOD_MODES, true)
            ? $mode
            : SystemSetting::NCR_PERIOD_MODE_WEEKLY;
    }

    /**
     * Format an amount for display, print and export using the configured currency.
     *
     * The amount is grouped from its exact decimal string so that money is never
     * routed through a floating point value, even for presentation.
     */
    public function formatMoney(Money $amount): string
    {
        $symbol = (new NumberFormatter($this->locale(), NumberFormatter::CURRENCY))
            ->getSymbol(NumberFormatter::CURRENCY_SYMBOL);

        return ($amount->isNegative() ? '-' : '').$symbol.$this->formatAmount($amount->abs());
    }

    /**
     * Format an amount as a grouped decimal without a currency symbol.
     *
     * Compact reports state the currency once and print plain figures, which
     * saves a column of repeated symbols. Money is still grouped from its exact
     * decimal string, never through a float.
     */
    public function formatAmount(Money $amount): string
    {
        [$whole, $fraction] = array_pad(explode('.', $amount->abs()->toDecimalString(), 2), 2, '0');

        $grouped = strrev(implode(',', str_split(strrev($whole), 3)));

        return sprintf('%s%s.%s', $amount->isNegative() ? '-' : '', $grouped, $fraction);
    }

    private function locale(): string
    {
        $locale = config('lomas.money_locale');

        return is_string($locale) && $locale !== '' ? $locale : 'en_US';
    }
}
