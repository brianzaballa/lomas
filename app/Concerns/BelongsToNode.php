<?php

namespace App\Concerns;

use App\Domain\Nodes\NodeIdentity;

/**
 * Stamps the record with the node that created it.
 *
 * Every operational record has to be attributable after consolidation, and every
 * report groups by node_id, so a record without it is a defect. The stamp is
 * applied here rather than in each action so a new action cannot forget it.
 * See AGENTS.md sections 42, 47 and 69.10.
 */
trait BelongsToNode
{
    /**
     * Fill the node only when the caller has not stated it, so an importing
     * node always records the origin node, never itself.
     */
    public static function bootBelongsToNode(): void
    {
        static::creating(function (self $model): void {
            if (blank($model->node_id)) {
                $model->node_id = app(NodeIdentity::class)->id();
            }
        });
    }
}
