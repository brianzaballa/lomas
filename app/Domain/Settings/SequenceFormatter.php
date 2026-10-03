<?php

namespace App\Domain\Settings;

use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Renders business numbers such as "LN-MNL1-2026-000001" from a configurable
 * format.
 *
 * Supported placeholders:
 *   {year}    four digit year of the given date
 *   {month}   two digit month of the given date
 *   {node}    the node code, e.g. MNL1
 *   {seq}     the sequence number
 *   {seq:N}   the sequence number left padded with zeroes to N digits
 */
class SequenceFormatter
{
    /**
     * Render a number.
     *
     * A business number must be node qualified, because two offline nodes draw
     * from separate counters and would otherwise issue the same number. See
     * AGENTS.md sections 21, 22 and 69.3.
     */
    public function format(string $format, int $sequence, CarbonInterface $date, string $nodeCode): string
    {
        $sequence = max($sequence, 0);

        $replaced = preg_replace_callback(
            '/\{(\w+)(?::(\d+))?\}/',
            function (array $matches) use ($sequence, $date, $nodeCode): string {
                $token = $matches[1];

                return match ($token) {
                    'year' => $date->format('Y'),
                    'month' => $date->format('m'),
                    'node' => $nodeCode,
                    'seq' => $this->pad($sequence, isset($matches[2]) ? (int) $matches[2] : 0),
                    default => throw new InvalidArgumentException("Unknown number format token [{$token}]."),
                };
            },
            $format,
        );

        if ($replaced === null) {
            throw new InvalidArgumentException('The number format could not be rendered.');
        }

        return $replaced;
    }

    /**
     * Validate a configured format before it is stored.
     *
     * A business number format is valid when it carries both the node code and a
     * sequence number. Without the node code two offline nodes collide, and
     * without a sequence the format can never produce a new number.
     */
    public function supports(string $format): bool
    {
        return preg_match('/\{node\}/', $format) === 1
            && preg_match('/\{seq(?::\d+)?\}/', $format) === 1;
    }

    protected function pad(int $sequence, int $length): string
    {
        return $length > 0 ? str_pad((string) $sequence, $length, '0', STR_PAD_LEFT) : (string) $sequence;
    }
}
