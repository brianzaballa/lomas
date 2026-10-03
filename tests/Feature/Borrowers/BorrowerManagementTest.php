<?php

use App\Domain\Access\Role as RoleName;
use App\Domain\Audit\AuditAction;
use App\Enums\BorrowerStatus;
use App\Models\AuditLog;
use App\Models\Borrower;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * @return array<string, mixed>
 */
function borrowerPayload(array $overrides = []): array
{
    return [
        'first_name' => 'Juan',
        'middle_name' => 'Reyes',
        'last_name' => 'Dela Cruz',
        'suffix' => 'Jr.',
        'birth_date' => '1985-03-14',
        'mobile_number' => '09171234567',
        'address' => '12 Mabini St, Quezon City',
        ...$overrides,
    ];
}

function actingAsRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('requires authentication to reach the borrower list', function () {
    $this->get(route('borrowers.index'))->assertRedirect(route('login'));
});

it('refuses the borrower list without the view permission', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('borrowers.index'))
        ->assertForbidden();
});

it('lists borrowers for a user who may view them', function () {
    Borrower::factory()->create([
        'borrower_number' => 'BR-000001',
        'first_name' => 'Juan',
        'middle_name' => null,
        'last_name' => 'Dela Cruz',
    ]);

    $this->actingAs(actingAsRole(RoleName::ADMINISTRATOR))
        ->get(route('borrowers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('borrowers/Index')
            ->has('borrowers.data', 1)
            ->where('borrowers.data.0.borrower_number', 'BR-000001')
            ->where('borrowers.data.0.full_name', 'Juan Dela Cruz')
            ->where('sort.by', 'name')
            ->where('sort.direction', 'asc'),
        );
});

it('searches borrowers by number, name and mobile number', function () {
    Borrower::factory()->create([
        'borrower_number' => 'BR-000001',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'mobile_number' => '09171234567',
    ]);
    Borrower::factory()->create([
        'borrower_number' => 'BR-000002',
        'first_name' => 'Maria',
        'middle_name' => null,
        'last_name' => 'Santos',
        'mobile_number' => '09189876543',
    ]);

    $administrator = actingAsRole(RoleName::ADMINISTRATOR);

    $this->actingAs($administrator)
        ->get(route('borrowers.index', ['search' => 'BR-000002']))
        ->assertInertia(fn ($page) => $page->has('borrowers.data', 1)
            ->where('borrowers.data.0.borrower_number', 'BR-000002'));

    $this->actingAs($administrator)
        ->get(route('borrowers.index', ['search' => 'Santos']))
        ->assertInertia(fn ($page) => $page->has('borrowers.data', 1)
            ->where('borrowers.data.0.full_name', 'Maria Santos'));

    $this->actingAs($administrator)
        ->get(route('borrowers.index', ['search' => '0917']))
        ->assertInertia(fn ($page) => $page->has('borrowers.data', 1)
            ->where('borrowers.data.0.borrower_number', 'BR-000001'));
});

it('matches every word of a multi word search', function () {
    Borrower::factory()->create([
        'borrower_number' => 'BR-000001',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
    ]);
    Borrower::factory()->create([
        'borrower_number' => 'BR-000002',
        'first_name' => 'Juan',
        'last_name' => 'Santos',
    ]);

    $this->actingAs(actingAsRole(RoleName::ADMINISTRATOR))
        ->get(route('borrowers.index', ['search' => 'Juan Dela']))
        ->assertInertia(fn ($page) => $page->has('borrowers.data', 1)
            ->where('borrowers.data.0.borrower_number', 'BR-000001'));
});

it('treats wildcard characters as literal search text', function () {
    Borrower::factory()->create(['borrower_number' => 'BR-000001', 'last_name' => 'Santos']);
    Borrower::factory()->count(3)->create();

    $this->actingAs(actingAsRole(RoleName::ADMINISTRATOR))
        ->get(route('borrowers.index', ['search' => '%']))
        ->assertInertia(fn ($page) => $page->has('borrowers.data', 0));
});

it('sorts by the requested column and ignores unknown columns', function () {
    Borrower::factory()->create(['borrower_number' => 'BR-000002', 'last_name' => 'Santos']);
    Borrower::factory()->create(['borrower_number' => 'BR-000001', 'last_name' => 'Alvarez']);

    $administrator = actingAsRole(RoleName::ADMINISTRATOR);

    $this->actingAs($administrator)
        ->get(route('borrowers.index', ['sort' => 'borrower_number', 'direction' => 'asc']))
        ->assertInertia(fn ($page) => $page
            ->where('borrowers.data.0.borrower_number', 'BR-000001')
            ->where('borrowers.data.1.borrower_number', 'BR-000002'));

    $this->actingAs($administrator)
        ->get(route('borrowers.index', ['sort' => 'borrower_number', 'direction' => 'desc']))
        ->assertInertia(fn ($page) => $page
            ->where('borrowers.data.0.borrower_number', 'BR-000002'));

    $this->actingAs($administrator)
        ->get(route('borrowers.index', ['sort' => 'password']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('sort.by', 'name'));
});

it('shows the borrower profile', function () {
    $borrower = Borrower::factory()->create([
        'borrower_number' => 'BR-000001',
        'first_name' => 'Juan',
        'middle_name' => null,
        'last_name' => 'Dela Cruz',
    ]);

    $this->actingAs(actingAsRole(RoleName::ADMINISTRATOR))
        ->get(route('borrowers.show', $borrower))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('borrowers/Show')
            ->where('borrower.borrower_number', 'BR-000001')
            ->where('borrower.full_name', 'Juan Dela Cruz')
            ->where('borrower.status_label', 'Active'),
        );
});

it('shows the create form only to a user who may create borrowers', function () {
    $this->actingAs(actingAsRole(RoleName::ADMINISTRATOR))
        ->get(route('borrowers.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('borrowers/Create')
            ->has('statuses', 2),
        );

    $this->actingAs(actingAsRole(RoleName::VIEWER))
        ->get(route('borrowers.create'))
        ->assertForbidden();
});

it('registers a borrower with a generated business number and an audit entry', function () {
    $administrator = actingAsRole(RoleName::ADMINISTRATOR);

    $this->actingAs($administrator)
        ->post(route('borrowers.store'), borrowerPayload())
        ->assertRedirect();

    $borrower = Borrower::query()->sole();

    expect($borrower->borrower_number)->toBe('BR-'.config('lomas.node.code').'-000001')
        ->and($borrower->fullName())->toBe('Juan Reyes Dela Cruz Jr.')
        ->and($borrower->birth_date?->toDateString())->toBe('1985-03-14')
        ->and($borrower->status)->toBe(BorrowerStatus::Active);

    $this->assertDatabaseHas('audit_logs', [
        'action' => AuditAction::BORROWER_CREATED,
        'auditable_type' => $borrower->getMorphClass(),
        'auditable_id' => $borrower->id,
        'user_id' => $administrator->id,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => AuditAction::BORROWER_CREATED,
        'new_values' => json_encode([
            'borrower_number' => 'BR-'.config('lomas.node.code').'-000001',
            'name' => 'Juan Reyes Dela Cruz Jr.',
            'mobile_number' => '09171234567',
            'status' => 'active',
        ], JSON_THROW_ON_ERROR),
    ]);
});

it('never hands out the same business number twice', function () {
    $this->actingAs(actingAsRole(RoleName::ADMINISTRATOR));

    $this->post(route('borrowers.store'), borrowerPayload());
    $this->post(route('borrowers.store'), borrowerPayload());

    $numbers = Borrower::query()->orderBy('id')->pluck('borrower_number');

    expect($numbers)->toHaveCount(2)
        ->and($numbers->unique())->toHaveCount(2)
        ->and($numbers->first())->toStartWith('BR-')
        ->and($numbers->last())->toStartWith('BR-');
});

it('follows the configured borrower number format', function () {
    SystemSetting::query()->forceCreate([
        'id' => SystemSetting::SINGLETON_ID,
        ...SystemSetting::DEFAULTS,
        'borrower_number_format' => 'BM-{node}-{year}-{seq:4}',
    ]);

    $this->actingAs(actingAsRole(RoleName::ADMINISTRATOR))
        ->post(route('borrowers.store'), borrowerPayload());

    expect(Borrower::query()->sole()->borrower_number)
        ->toBe('BM-'.config('lomas.node.code').'-'.now()->format('Y').'-0001');
});

it('stores a cleared optional field as null', function () {
    $this->actingAs(actingAsRole(RoleName::ADMINISTRATOR))
        ->post(route('borrowers.store'), borrowerPayload([
            'middle_name' => '   ',
            'suffix' => '',
            'birth_date' => null,
        ]));

    $borrower = Borrower::query()->sole();

    expect($borrower->middle_name)->toBeNull()
        ->and($borrower->suffix)->toBeNull()
        ->and($borrower->fullName())->toBe('Juan Dela Cruz');
});

it('validates the borrower form', function () {
    $this->actingAs(actingAsRole(RoleName::ADMINISTRATOR))
        ->post(route('borrowers.store'), [
            'first_name' => '',
            'last_name' => '',
            'mobile_number' => 'not-a-number',
            'address' => '',
            'birth_date' => now()->addDay()->toDateString(),
            'status' => 'archived',
        ])
        ->assertSessionHasErrors(['first_name', 'last_name', 'mobile_number', 'address', 'birth_date', 'status']);

    expect(Borrower::query()->count())->toBe(0);
});

it('refuses to register a borrower without the create permission', function () {
    $this->actingAs(actingAsRole(RoleName::VIEWER))
        ->post(route('borrowers.store'), borrowerPayload())
        ->assertForbidden();

    expect(Borrower::query()->count())->toBe(0);
});

it('allows two borrowers to share a mobile number', function () {
    $this->actingAs(actingAsRole(RoleName::ADMINISTRATOR));

    $this->post(route('borrowers.store'), borrowerPayload());
    $this->post(route('borrowers.store'), borrowerPayload());

    expect(Borrower::query()->count())->toBe(2);
});

it('updates a borrower and records the previous values', function () {
    $borrower = Borrower::factory()->create([
        'borrower_number' => 'BR-000001',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'status' => BorrowerStatus::Active,
    ]);

    $administrator = actingAsRole(RoleName::ADMINISTRATOR);

    $this->actingAs($administrator)
        ->patch(route('borrowers.update', $borrower), borrowerPayload([
            'first_name' => 'Juan',
            'last_name' => 'Santos',
            'status' => 'inactive',
        ]))
        ->assertRedirect(route('borrowers.show', $borrower));

    expect($borrower->refresh())
        ->last_name->toBe('Santos')
        ->status->toBe(BorrowerStatus::Inactive)
        ->borrower_number->toBe('BR-000001');

    $log = AuditLog::query()
        ->where('action', AuditAction::BORROWER_UPDATED)
        ->sole();

    expect($log->user_id)->toBe($administrator->id)
        ->and($log->old_values)->toMatchArray(['last_name' => 'Dela Cruz', 'status' => 'active'])
        ->and($log->new_values)->toMatchArray(['last_name' => 'Santos', 'status' => 'inactive']);
});

it('writes no audit entry when an update changes nothing', function () {
    $borrower = Borrower::factory()->create([
        'first_name' => 'Juan',
        'middle_name' => 'Reyes',
        'last_name' => 'Dela Cruz',
        'suffix' => 'Jr.',
        'birth_date' => '1985-03-14',
        'mobile_number' => '09171234567',
        'address' => '12 Mabini St, Quezon City',
    ]);

    $this->actingAs(actingAsRole(RoleName::ADMINISTRATOR))
        ->patch(route('borrowers.update', $borrower), borrowerPayload());

    expect(AuditLog::query()->where('action', AuditAction::BORROWER_UPDATED)->count())->toBe(0);
});

it('refuses to update a borrower without the update permission', function () {
    $borrower = Borrower::factory()->create(['last_name' => 'Santos']);

    $this->actingAs(actingAsRole(RoleName::VIEWER))
        ->get(route('borrowers.edit', $borrower))
        ->assertForbidden();

    $this->actingAs(actingAsRole(RoleName::VIEWER))
        ->patch(route('borrowers.update', $borrower), borrowerPayload(['last_name' => 'Changed']))
        ->assertForbidden();

    expect($borrower->refresh()->last_name)->toBe('Santos');
});
