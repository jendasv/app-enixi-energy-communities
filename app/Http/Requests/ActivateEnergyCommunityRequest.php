<?php

namespace App\Http\Requests;

use App\Enums\EnergyCommunityMeterPointState;
use App\Enums\EnergyCommunityState;
use App\Enums\EnergyDirection;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

class ActivateEnergyCommunityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('activate', $this->route('energyCommunity'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $community = $this->route('energyCommunity');

            // BR-12: only from `new`.
            if ($community->state !== EnergyCommunityState::New) {
                $validator->errors()->add(
                    'state',
                    "Only a community in state 'new' can be activated (currently '{$community->state->value}').",
                );

                return;
            }

            // BR-12: at least one accepted registration of a generation metering point.
            $hasAcceptedGeneration = $community->registrations()
                ->where('state', EnergyCommunityMeterPointState::Accepted)
                ->whereHas('meterPoint', fn ($q) => $q->where('energy_direction', EnergyDirection::Generation))
                ->exists();

            if (! $hasAcceptedGeneration) {
                $validator->errors()->add(
                    'state',
                    'The community needs at least one accepted registration of a generation metering point.',
                );
            }
        });
    }
}
