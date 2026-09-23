<?php

namespace App\Http\Requests;

use App\Enums\EnergyCommunityState;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

class RejectEnergyCommunityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('reject', $this->route('energyCommunity'));
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

            // BR-13: only from `new` or `activated`.
            if (! in_array($community->state, [EnergyCommunityState::New, EnergyCommunityState::Activated], true)) {
                $validator->errors()->add(
                    'state',
                    "A community in state '{$community->state->value}' cannot be rejected.",
                );
            }
        });
    }
}
