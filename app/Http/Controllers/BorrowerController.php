<?php

namespace App\Http\Controllers;

use App\Actions\Borrowers\CreateBorrower;
use App\Actions\Borrowers\UpdateBorrower;
use App\Domain\Access\Permission;
use App\Enums\BorrowerStatus;
use App\Http\Requests\Borrowers\StoreBorrowerRequest;
use App\Http\Requests\Borrowers\UpdateBorrowerRequest;
use App\Models\Borrower;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Borrower registry. A borrower is reused across every loan, so the record is
 * never deleted, only made inactive.
 */
class BorrowerController extends Controller
{
    /**
     * Sortable columns, mapped to real columns so a request cannot inject one.
     *
     * @var array<string, string>
     */
    protected const SORTS = [
        'borrower_number' => 'borrower_number',
        'name' => 'last_name',
        'mobile_number' => 'mobile_number',
        'created_at' => 'created_at',
    ];

    /**
     * The searchable borrower list.
     */
    public function index(): Response
    {
        $this->authorize(Permission::BORROWERS_VIEW);

        $sort = $this->sort();
        $direction = $this->direction();
        $column = self::SORTS[$sort];

        $borrowers = Borrower::query()
            ->search(request('search'))
            ->when(
                $direction === 'desc',
                fn ($query) => $query->orderByDesc($column)->orderByDesc('id'),
                fn ($query) => $query->orderBy($column)->orderBy('id'),
            )
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Borrower $borrower): array => $this->row($borrower));

        return Inertia::render('borrowers/Index', [
            'borrowers' => $borrowers,
            'filters' => ['search' => request('search')],
            'sort' => ['by' => $sort, 'direction' => $direction],
        ]);
    }

    /**
     * Show the form for registering a borrower.
     */
    public function create(): Response
    {
        $this->authorize(Permission::BORROWERS_CREATE);

        return Inertia::render('borrowers/Create', [
            'statuses' => $this->statuses(),
        ]);
    }

    /**
     * Store a newly registered borrower.
     */
    public function store(StoreBorrowerRequest $request, CreateBorrower $createBorrower): RedirectResponse
    {
        $borrower = $createBorrower->handle($request->borrowerAttributes());

        $this->success("Borrower [{$borrower->borrower_number}] created.");

        return to_route('borrowers.show', $borrower);
    }

    /**
     * Show the borrower profile and loan history.
     */
    public function show(Borrower $borrower): Response
    {
        $this->authorize(Permission::BORROWERS_VIEW);

        return Inertia::render('borrowers/Show', [
            'borrower' => $this->profile($borrower),
        ]);
    }

    /**
     * Show the form for editing a borrower.
     */
    public function edit(Borrower $borrower): Response
    {
        $this->authorize(Permission::BORROWERS_UPDATE);

        return Inertia::render('borrowers/Edit', [
            'borrower' => $this->form($borrower),
            'statuses' => $this->statuses(),
        ]);
    }

    /**
     * Update an existing borrower.
     */
    public function update(UpdateBorrowerRequest $request, Borrower $borrower, UpdateBorrower $updateBorrower): RedirectResponse
    {
        $updateBorrower->handle($borrower, $request->borrowerAttributes());

        $this->success("Borrower [{$borrower->borrower_number}] updated.");

        return to_route('borrowers.show', $borrower);
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(Borrower $borrower): array
    {
        return [
            'id' => $borrower->id,
            'borrower_number' => $borrower->borrower_number,
            'full_name' => $borrower->fullName(),
            'mobile_number' => $borrower->mobile_number,
            'status' => $borrower->status->value,
            'status_label' => $borrower->status->label(),
            'created_at' => $borrower->created_at?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function form(Borrower $borrower): array
    {
        return [
            ...$this->row($borrower),
            'first_name' => $borrower->first_name,
            'middle_name' => $borrower->middle_name,
            'last_name' => $borrower->last_name,
            'suffix' => $borrower->suffix,
            'birth_date' => $borrower->birth_date?->toDateString(),
            'address' => $borrower->address,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function profile(Borrower $borrower): array
    {
        return [
            ...$this->form($borrower),
            'updated_at' => $borrower->updated_at?->toDateTimeString(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function statuses(): array
    {
        return array_map(
            static fn (BorrowerStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            BorrowerStatus::cases(),
        );
    }

    protected function sort(): string
    {
        $sort = request('sort');

        return is_string($sort) && array_key_exists($sort, self::SORTS) ? $sort : 'name';
    }

    protected function direction(): string
    {
        return request('direction') === 'desc' ? 'desc' : 'asc';
    }
}
