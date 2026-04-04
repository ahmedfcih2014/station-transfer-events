<?php

namespace App\Rules;

use Closure;
use DateTimeImmutable;
use Illuminate\Contracts\Validation\ValidationRule;

class Iso8601DateTime implements ValidationRule
{
    /**
     * RFC 3339 / ISO 8601 datetime: calendar date + time + Z or numeric offset (optional fractional seconds).
     */
    private const PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,9})?(Z|[+-]\d{2}:\d{2})$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('The :attribute field must be a valid ISO8601 datetime, example: 2026-02-19T10:00:00Z');

            return;
        }

        if (! preg_match(self::PATTERN, $value)) {
            $fail('The :attribute field must be a valid ISO8601 datetime, example: 2026-02-19T10:00:00Z');

            return;
        }

        try {
            new DateTimeImmutable($value);
        } catch (\Exception) {
            $fail('The :attribute field must be a valid ISO8601 datetime, example: 2026-02-19T10:00:00Z');
        }
    }
}
