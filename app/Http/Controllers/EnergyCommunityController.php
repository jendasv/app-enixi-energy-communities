<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RejectEnergyCommunity;
use App\Enums\CommunityRole;
use App\Enums\EnergyCommunityState;
use App\Http\Requests\ActivateEnergyCommunityRequest;
use App\Http\Requests\AddEnergyCommunityUserRequest;
use App\Http\Requests\RejectEnergyCommunityRequest;
use App\Http\Requests\StoreEnergyCommunityRequest;
use App\Http\Resources\EnergyCommunityResource;
use App\Models\EnergyCommunity;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EnergyCommunityController extends Controller
{
    /**
     * GET /api/energy-communities — scoped, paginated, filterable by state
     * (BR-11). Scoping happens in the query, not by filtering a loaded
     * collection.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'state' => ['sometimes', Rule::enum(EnergyCommunityState::class)],
        ]);

        $query = EnergyCommunity::query();

        if (! $request->user()->is_admin) {
            $query->whereHas('users', fn ($q) => $q->whereKey($request->user()->id));
        }

        if ($request->filled('state')) {
            $query->where('state', $request->string('state'));
        }

        // See MeterPointController::index() for why this is explicit.
        return EnergyCommunityResource::collection($query->orderBy('id')->paginate());
    }

    /**
     * GET /api/energy-communities/{energyCommunity} — members and admins
     * only. A non-member gets 404, not 403: this is about what they can
     * see, not a permission they lack.
     */
    public function show(Request $request, EnergyCommunity $energyCommunity): EnergyCommunityResource
    {
        if (! $request->user()->can('view', $energyCommunity)) {
            abort(404);
        }

        return new EnergyCommunityResource($energyCommunity);
    }

    /**
     * POST /api/energy-communities — BR-3: creator becomes manager
     * atomically, state starts at `new`.
     */
    public function store(StoreEnergyCommunityRequest $request): EnergyCommunityResource
    {
        try {
            $community = DB::transaction(function () use ($request) {
                $community = EnergyCommunity::create([
                    'ecid' => $request->validated('ecid'),
                    'name' => $request->validated('name'),
                    'state' => EnergyCommunityState::New,
                ]);

                $community->users()->attach($request->user()->id, [
                    'role' => CommunityRole::Manager,
                ]);

                return $community;
            });
        } catch (UniqueConstraintViolationException) {
            // Same race as MeterPointController::store(): two requests can both
            // pass the `unique` validation rule before either writes.
            throw ValidationException::withMessages([
                'ecid' => ['This ecid is already registered.'],
            ]);
        }

        return new EnergyCommunityResource($community);
    }

    /**
     * POST /api/energy-communities/{energyCommunity}/users — BR-4: only a
     * manager (or admin) adds users; authorization happens in the Form
     * Request so an unauthorized caller gets 403 before validation runs.
     */
    public function addUser(AddEnergyCommunityUserRequest $request, EnergyCommunity $energyCommunity): JsonResponse
    {
        try {
            $energyCommunity->users()->attach($request->validated('user_id'), [
                'role' => $request->validated('role'),
            ]);
        } catch (UniqueConstraintViolationException) {
            // BR-4's scoped-unique validation rule has the same TOCTOU race as
            // the two above — two requests adding the same user before either
            // commits both pass validation.
            throw ValidationException::withMessages([
                'user_id' => ['This user is already a member of the community.'],
            ]);
        }

        return response()->json(['message' => 'User added to the community.'], 201);
    }

    /**
     * POST /api/energy-communities/{energyCommunity}/activate — BR-12.
     */
    public function activate(ActivateEnergyCommunityRequest $request, EnergyCommunity $energyCommunity): EnergyCommunityResource
    {
        $energyCommunity->update(['state' => EnergyCommunityState::Activated]);

        return new EnergyCommunityResource($energyCommunity);
    }

    /**
     * POST /api/energy-communities/{energyCommunity}/reject — BR-13,
     * atomic: state change plus ending every blocking registration.
     */
    public function reject(
        RejectEnergyCommunityRequest $request,
        EnergyCommunity $energyCommunity,
        RejectEnergyCommunity $action,
    ): EnergyCommunityResource {
        $energyCommunity = $action->handle($energyCommunity);

        return new EnergyCommunityResource($energyCommunity);
    }
}
