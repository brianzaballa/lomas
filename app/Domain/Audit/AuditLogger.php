<?php

namespace App\Domain\Audit;

use App\Domain\Nodes\NodeIdentity;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Writes the audit trail described in AGENTS.md: who changed what, when and why.
 */
class AuditLogger
{
    public function __construct(private readonly NodeIdentity $nodes) {}

    /**
     * Record an arbitrary change.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function record(string $action, ?Model $auditable = null, array $oldValues = [], array $newValues = [], ?string $reason = null): AuditLog
    {
        return AuditLog::create([
            'node_id' => $this->nodes->id(),
            'user_id' => $this->actingUserId(),
            'user_label' => $this->actingUserLabel(),
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues === [] ? null : $oldValues,
            'new_values' => $newValues === [] ? null : $newValues,
            'reason' => $reason,
            'ip_address' => $this->ipAddress(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function recordCreated(string $action, Model $auditable, array $values = [], ?string $reason = null): AuditLog
    {
        return $this->record($action, $auditable, newValues: $values === [] ? $this->attributesOf($auditable) : $values, reason: $reason);
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function recordUpdated(string $action, Model $auditable, array $oldValues, array $newValues = [], ?string $reason = null): AuditLog
    {
        return $this->record($action, $auditable, $oldValues, $newValues === [] ? $this->attributesOf($auditable) : $newValues, $reason);
    }

    public function recordDeleted(string $action, Model $auditable, ?string $reason = null): AuditLog
    {
        return $this->record($action, $auditable, oldValues: $this->attributesOf($auditable), reason: $reason);
    }

    /**
     * Only track the attributes that are safe to read and useful for an auditor.
     *
     * @return array<string, mixed>
     */
    protected function attributesOf(Model $auditable): array
    {
        return $auditable->attributesToArray();
    }

    protected function actingUserId(): ?int
    {
        $user = Auth::user();

        return $user instanceof User ? $user->getKey() : null;
    }

    /**
     * The actor as staff know them, e.g. "MNL1 - admin@lomas.test".
     *
     * The label is resolved at write time because it is the only part of the
     * actor that survives consolidation. See AGENTS.md section 69.10.
     */
    protected function actingUserLabel(): string
    {
        $user = Auth::user();

        return $user instanceof User
            ? sprintf('%s - %s', $this->nodes->code(), $user->email)
            : $this->nodes->code();
    }

    protected function ipAddress(): ?string
    {
        return request()->ip();
    }
}
