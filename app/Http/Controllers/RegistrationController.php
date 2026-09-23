<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RegisterMeterPointIntoCommunity;
use App\Actions\TransitionRegistration;
use App\Enums\EnergyCommunityMeterPointState;
use App\Http\Requests\RegisterMeterPointRequest;
use App\Http\Requests\TransitionRegistrationRequest;
use App\Http\Resources\EnergyCommunityMeterPointResource;
use App\Models\EnergyCommunity;
use App\Models\EnergyCommunityMeterPoint;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RegistrationController extends Controller
{
    /**
     * POST /api/energy-communities/{energyCommunity}/meter-points — BR-5
     * through BR-8.
     */
    public function store(
        RegisterMeterPointRequest $request,
        EnergyCommunity $energyCommunity,
        RegisterMeterPointIntoCommunity $action,
    ): EnergyCommunityMeterPointResource {
        $registration = $action->handle(
            $energyCommunity,
            // Reuses the Form Request's own memoized lookup instead of a second
            // query — it already had to load this row for the BR-6 membership check.
            $request->meterPoint(),
            CarbonImmutable::parse($request->validated('from_date')),
            $request->validated('to_date') ? CarbonImmutable::parse($request->validated('to_date')) : null,
            CarbonImmutable::parse($request->validated('consent_date')),
        );

        return new EnergyCommunityMeterPointResource($registration);
    }

    /**
     * GET /api/energy-communities/{energyCommunity}/meter-points — the
     * community's registrations, filterable by state. Same 404-for-visibility
     * convention as EnergyCommunityController::show().
     */
    public function index(Request $request, EnergyCommunity $energyCommunity): AnonymousResourceCollection
    {
        if (! $request->user()->can('view', $energyCommunity)) {
            abort(404);
        }

        $request->validate([
            'state' => ['sometimes', Rule::enum(EnergyCommunityMeterPointState::class)],
        ]);

        $query = $energyCommunity->registrations();

        if ($request->filled('state')) {
            $query->where('state', $request->string('state'));
        }

        // See MeterPointController::index() for why this is explicit.
        return EnergyCommunityMeterPointResource::collection($query->orderBy('id')->paginate());
    }

    /**
     * POST /api/registrations/{registration}/transition — BR-9.
     */
    public function transition(
        TransitionRegistrationRequest $request,
        EnergyCommunityMeterPoint $registration,
        TransitionRegistration $action,
    ): EnergyCommunityMeterPointResource {
        $target = EnergyCommunityMeterPointState::from($request->validated('state'));

        $registration = $action->handle($registration, $target, $request->validated('status_code'));

        return new EnergyCommunityMeterPointResource($registration);
    }

    /**
     * DELETE /api/registrations/{registration} — BR-10: never hard-deleted,
     * maps to whichever BR-9 transition ends the current state.
     */
    public function destroy(EnergyCommunityMeterPoint $registration, TransitionRegistration $action): JsonResponse
    {
        Gate::authorize('transition', $registration);

        $target = $registration->state->deletionTarget();

        if ($target === null) {
            throw new ConflictHttpException('This registration is already in a terminal state.');
        }

        $action->handle($registration, $target, null);

        return response()->json(null, 204);
    }
}
