<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreAreaRequest;
use App\Http\Requests\Settings\UpdateAreaRequest;
use App\Models\Area;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AreaController extends Controller
{
    public function index(): Response
    {
        $areas = Area::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Area $area): array => [
                'id' => $area->id,
                'code' => $area->code,
                'name' => $area->name,
                'is_active' => $area->is_active,
            ]);

        return Inertia::render('settings/Areas', [
            'areas' => $areas,
        ]);
    }

    public function store(StoreAreaRequest $request): RedirectResponse
    {
        $area = Area::query()->create($request->validated());

        $this->success("Area [{$area->name}] created.");

        return to_route('areas.index');
    }

    public function update(UpdateAreaRequest $request, Area $area): RedirectResponse
    {
        $area->update($request->validated());

        $this->success("Area [{$area->name}] updated.");

        return to_route('areas.index');
    }
}
