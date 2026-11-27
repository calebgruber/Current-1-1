<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Thrown by Controller::validate() so the App can send the user back to the form
 * with their input and the error messages.
 */
final class ValidationException extends \RuntimeException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct('The form has errors.');
    }
}
