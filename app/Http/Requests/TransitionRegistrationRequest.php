<?php

namespace App\Http\Requests;

use App\Enums\EnergyCommunityMeterPointState;
use App\Models\EnergyCommunityMeterPoint;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('transition', $this->route('registration'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'state' => ['required', Rule::enum(EnergyCommunityMeterPointState::class)],
            'status_code' => ['required_if:state,error', 'nullable', 'integer'],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        // BR-9: a friendly 422 for the common case of an obviously illegal
        // transition. The atomic update in the controller is still the real
        // guard against a state that changed between this check and the
        // write (BR-8) — this is UX, not the safety net.
        $validator->after(function (ValidatorContract $validator) {
            if ($validator->errors()->has('state')) {
                return;
            }

            /** @var EnergyCommunityMeterPoint $registration */
            $registration = $this->route('registration');
            $target = EnergyCommunityMeterPointState::from($this->string('state')->value());

            if (! $registration->state->canTransitionTo($target)) {
                $validator->errors()->add(
                    'state',
                    "Cannot transition from {$registration->state->value} to {$target->value}.",
                );
            }
        });
    }
}
