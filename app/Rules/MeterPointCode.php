<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class MeterPointCode implements ValidationRule
{
    /**
     * BR-1: exactly 33 characters, uppercase letters and digits only.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[A-Z0-9]{33}$/', $value)) {
            $fail('The :attribute must be exactly 33 characters, using only uppercase letters and digits.');
        }
    }
}
