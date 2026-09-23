<?php

namespace App\Http\Requests;

use App\Enums\EnergyCommunityState;
use App\Models\MeterPoint;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

class RegisterMeterPointRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('registerMeterPoint', $this->route('energyCommunity'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'meter_point_id' => ['required', 'integer', 'exists:meter_points,id'],
            'from_date' => ['required', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            // BR-6: consent_date is required and must not be in the future.
            'consent_date' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $community = $this->route('energyCommunity');

            // BR-5: only while the community is new or activated.
            if ($community->state === EnergyCommunityState::Rejected) {
                $validator->errors()->add(
                    'energy_community_id',
                    'This community is rejected and cannot accept new registrations.',
                );
            }

            // BR-6: the metering point's owner must be a member of the community.
            if (! $validator->errors()->has('meter_point_id')) {
                $meterPoint = $this->meterPoint();

                if ($meterPoint !== null && ! $community->users()->whereKey($meterPoint->user_id)->exists()) {
                    $validator->errors()->add(
                        'meter_point_id',
                        'The metering point\'s owner must be a member of this community.',
                    );
                }
            }
        });
    }

    public function meterPoint(): ?MeterPoint
    {
        return once(fn () => MeterPoint::find($this->integer('meter_point_id')));
    }
}
