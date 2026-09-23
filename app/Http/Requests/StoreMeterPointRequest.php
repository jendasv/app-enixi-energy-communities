<?php

namespace App\Http\Requests;

use App\Enums\EnergyDirection;
use App\Models\GridOperator;
use App\Rules\MeterPointCode;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeterPointRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Any authenticated user may register a metering point of their own.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', new MeterPointCode, 'unique:meter_points,name'],
            'energy_direction' => ['required', Rule::enum(EnergyDirection::class)],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        // BR-1: reject if no grid operator matches the code's first 8 characters.
        // Skipped when `name` already failed format/uniqueness — nothing coherent
        // to look up otherwise.
        $validator->after(function (ValidatorContract $validator) {
            if ($validator->errors()->has('name')) {
                return;
            }

            if ($this->gridOperator() === null) {
                $validator->errors()->add(
                    'name',
                    'No grid operator is registered for this metering point\'s operator code.',
                );
            }
        });
    }

    public function gridOperator(): ?GridOperator
    {
        return once(fn () => GridOperator::query()
            ->where('identifier', substr((string) $this->string('name'), 0, 8))
            ->first());
    }
}
