<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeDownloadUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid($value)) {
            $fail('downloads.validation.safe_url')->translate();
        }
    }

    public static function isValid(string $value): bool
    {
        // Browsers normalize slashes and control characters in URLs. Check the
        // decoded form too, so encoded network paths cannot become redirects.
        if ($value === '' || preg_match('/[\s\x00-\x1F\x7F\\\\]/u', $value) !== 0) {
            return false;
        }

        $decoded = $value;
        do {
            $previous = $decoded;
            $decoded = rawurldecode($decoded);
            if (preg_match('/[\x00-\x1F\x7F\\\\]/', $decoded) !== 0) {
                return false;
            }
        } while ($decoded !== $previous);

        if (str_starts_with($value, '/')) {
            return ! str_starts_with($decoded, '//');
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($value);

        return is_array($parts)
            && strtolower($parts['scheme'] ?? '') === 'https'
            && ! empty($parts['host'])
            && ! isset($parts['user'])
            && ! isset($parts['pass']);
    }
}
