<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * An upload that passed the request rules but turned out not to be a usable
 * image. Rendered as a field error rather than a 500 page.
 */
class InvalidImageException extends RuntimeException
{
    /**
     * Convert to a validation error on the given field.
     */
    public function asValidationError(string $field = 'image'): ValidationException
    {
        return ValidationException::withMessages([$field => $this->getMessage()]);
    }
}
