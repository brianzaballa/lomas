<?php

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Domain\Nodes\NodeIdentity;
use App\Models\AuditLog;
use App\Models\Borrower;
use App\Models\User;

it('records the node and the actor label so the actor survives consolidation', function () {
    $user = User::factory()->create(['email' => 'encoder@lomas.test']);
    $borrower = Borrower::factory()->create();

    $this->actingAs($user);

    app(AuditLogger::class)->recordCreated(
        AuditAction::BORROWER_CREATED,
        $borrower,
    );

    $log = AuditLog::query()->where('action', AuditAction::BORROWER_CREATED)->firstOrFail();

    expect($log->node_id)->toBe(app(NodeIdentity::class)->id())
        ->and($log->user_label)->toBe(config('lomas.node.code').' - encoder@lomas.test')
        ->and($log->auditable_id)->toBe($borrower->id)
        ->and($log->occurred_at)->not->toBeNull();
});

it('qualifies the actor label with the node so two nodes never produce one label', function () {
    $user = User::factory()->create(['email' => 'admin@lomas.test']);

    $this->actingAs($user);

    $log = app(AuditLogger::class)->record(AuditAction::SETTINGS_UPDATED);

    // The node code is part of the label, so the same account on another node
    // produces a different label and the two user 1s stay apart at central.
    expect($log->user_label)->toBe(config('lomas.node.code').' - admin@lomas.test')
        ->and($log->user_label)->toStartWith('MNL1 - ');
});

it('records the acting user only as a convenience, never as the identity', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $log = app(AuditLogger::class)->record(AuditAction::SETTINGS_UPDATED);

    expect($log->user_id)->toBe($user->getKey())
        ->and($log->user_label)->not->toBeNull()
        ->and($log->node_id)->not->toBeNull();
});

it('labels an action performed with no signed in user by the node alone', function () {
    auth()->logout();

    $log = app(AuditLogger::class)->record(AuditAction::SETTINGS_UPDATED);

    expect($log->user_id)->toBeNull()
        ->and($log->user_label)->toBe(config('lomas.node.code'));
});

it('stamps the node and the business time even when written directly', function () {
    $log = AuditLog::query()->create(['action' => AuditAction::SETTINGS_UPDATED]);

    expect($log->node_id)->toBe(app(NodeIdentity::class)->id())
        ->and($log->occurred_at)->not->toBeNull();
});
