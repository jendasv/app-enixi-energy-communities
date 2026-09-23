<?php

namespace App\Http\Requests;

use App\Enums\CommunityRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddEnergyCommunityUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('addUser', $this->route('energyCommunity'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $community = $this->route('energyCommunity');

        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                // BR-4: a user holds at most one role per community.
                Rule::unique('energy_community_user', 'user_id')
                    ->where('energy_community_id', $community->id),
            ],
            'role' => ['required', Rule::enum(CommunityRole::class)],
        ];
    }
}
