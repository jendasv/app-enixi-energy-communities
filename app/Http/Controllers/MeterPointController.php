<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EnergyDirection;
use App\Http\Requests\StoreMeterPointRequest;
use App\Http\Resources\MeterPointResource;
use App\Models\MeterPoint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MeterPointController extends Controller
{
    /**
     * GET /api/meter-points — own metering points; admins see all;
     * filterable by energy_direction (BR-2, BR-11).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'energy_direction' => ['sometimes', Rule::enum(EnergyDirection::class)],
        ]);

        $query = MeterPoint::query();

        if (! $request->user()->is_admin) {
            $query->where('user_id', $request->user()->id);
        }

        if ($request->filled('energy_direction')) {
            $query->where('energy_direction', $request->string('energy_direction'));
        }

        // Explicit order: without one, row order across pages is undefined and
        // can shift between requests under concurrent writes (duplicate or
        // skipped rows for a client paging through results).
        return MeterPointResource::collection($query->orderBy('id')->paginate());
    }

    /**
     * POST /api/meter-points — the caller registers a metering point of
     * their own (BR-1, BR-2).
     */
    public function store(StoreMeterPointRequest $request): MeterPointResource
    {
        try {
            $meterPoint = $request->user()->meterPoints()->create([
                'name' => $request->validated('name'),
                'energy_direction' => $request->validated('energy_direction'),
                'grid_operator_id' => $request->gridOperator()->identifier,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two requests can both pass the `unique` validation rule before
            // either writes — the DB constraint is the real guarantee.
            throw ValidationException::withMessages([
                'name' => ['This metering point code is already registered.'],
            ]);
        }

        return new MeterPointResource($meterPoint);
    }
}
