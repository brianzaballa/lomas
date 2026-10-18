<?php

namespace App\Domain\Reporting;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Domain\Nodes\NodeIdentity;
use App\Models\NcrPosting;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Posts and unposts Notes Collection Report periods.
 *
 * Posting a period is the sign-off that its figures have been collected and
 * checked. While a period is posted it soft-locks its payments: nothing dated
 * inside it may be staged, posted, edited or reversed until the period is
 * unposted. Both transitions are audited and nothing is ever deleted. See
 * AGENTS.md sections 47 and 48.
 */
class NcrPostingService
{
    public function __construct(
        private readonly NodeIdentity $nodes,
        private readonly AuditLogger $audit,
    ) {}

    public function posting(NcrPeriod $period): ?NcrPosting
    {
        return NcrPosting::query()
            ->where('node_id', $this->nodes->id())
            ->whereDate('period_start', $period->start->toDateString())
            ->whereDate('period_end', $period->end->toDateString())
            ->first();
    }

    public function isPosted(NcrPeriod $period): bool
    {
        return $this->posting($period)?->isPosted() ?? false;
    }

    public function post(NcrPeriod $period): NcrPosting
    {
        return DB::transaction(function () use ($period): NcrPosting {
            $posting = NcrPosting::query()
                ->where('node_id', $this->nodes->id())
                ->whereDate('period_start', $period->start->toDateString())
                ->whereDate('period_end', $period->end->toDateString())
                ->lockForUpdate()
                ->first();

            if ($posting instanceof NcrPosting && $posting->isPosted()) {
                return $posting;
            }

            $wasOpen = $posting instanceof NcrPosting;

            $posting ??= new NcrPosting([
                'node_id' => $this->nodes->id(),
                'period_start' => $period->start->toDateString(),
                'period_end' => $period->end->toDateString(),
            ]);

            $posting->forceFill([
                'status' => NcrPosting::STATUS_POSTED,
                'posted_by' => Auth::id(),
                'posted_at' => now(),
            ])->save();

            $values = $posting->only(['period_start', 'period_end', 'status', 'posted_at']);

            if ($wasOpen) {
                $this->audit->recordUpdated(
                    AuditAction::NCR_PERIOD_POSTED,
                    $posting,
                    ['status' => NcrPosting::STATUS_OPEN],
                    $values,
                );
            } else {
                $this->audit->recordCreated(AuditAction::NCR_PERIOD_POSTED, $posting, $values);
            }

            return $posting;
        });
    }

    public function unpost(NcrPeriod $period): NcrPosting
    {
        return DB::transaction(function () use ($period): NcrPosting {
            $posting = NcrPosting::query()
                ->where('node_id', $this->nodes->id())
                ->whereDate('period_start', $period->start->toDateString())
                ->whereDate('period_end', $period->end->toDateString())
                ->lockForUpdate()
                ->firstOrFail();

            if ($posting->isOpen()) {
                return $posting;
            }

            $posting->forceFill([
                'status' => NcrPosting::STATUS_OPEN,
                'posted_by' => null,
                'posted_at' => null,
            ])->save();

            $this->audit->recordUpdated(
                AuditAction::NCR_PERIOD_UNPOSTED,
                $posting,
                ['status' => NcrPosting::STATUS_POSTED],
                ['status' => NcrPosting::STATUS_OPEN],
            );

            return $posting;
        });
    }

    /**
     * The posted period covering a date, if any.
     */
    public function postedPeriodCovering(CarbonInterface $date): ?NcrPosting
    {
        return NcrPosting::query()
            ->where('node_id', $this->nodes->id())
            ->where('status', NcrPosting::STATUS_POSTED)
            ->whereDate('period_start', '<=', $date->toDateString())
            ->whereDate('period_end', '>=', $date->toDateString())
            ->first();
    }

    /**
     * Refuse any payment action inside a posted period.
     *
     * The guard runs before the write, so an operator sees the reason instead
     * of a half-applied change; the fix is always to unpost the period first.
     */
    public function assertUnlocked(CarbonInterface $date): void
    {
        $posting = $this->postedPeriodCovering($date);

        if ($posting instanceof NcrPosting) {
            throw new LogicException(sprintf(
                'The period %s to %s is posted. Unpost the Notes Collection Report period before changing payments in it.',
                $posting->period_start->format('M j, Y'),
                $posting->period_end->format('M j, Y'),
            ));
        }
    }
}
