<?php

namespace App\Concerns;

use Illuminate\Support\Str;

/**
 * Gives a model a uuid v7 primary key assigned by the creating node.
 *
 * A branch and the hub both create records while offline, so no domain table
 * may rely on a shared auto increment counter. See AGENTS.md section 69.3.
 *
 * Version 7 keys embed a timestamp, so keys stay time ordered and recent
 * inserts land at the end of the index.
 */
trait HasUuidKey
{
    /**
     * Assign the key before the insert when the caller did not supply one.
     */
    public static function bootHasUuidKey(): void
    {
        static::creating(function (self $model): void {
            if (! $model->getIncrementing() && blank($model->getKey())) {
                $model->setAttribute($model->getKeyName(), $model->newUuidKey());
            }
        });
    }

    public function getKeyType(): string
    {
        return 'string';
    }

    public function getIncrementing(): bool
    {
        return false;
    }

    protected function newUuidKey(): string
    {
        return (string) Str::uuid7();
    }
}
