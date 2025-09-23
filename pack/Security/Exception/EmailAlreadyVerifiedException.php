<?php

namespace Pack\Security\Exception;

use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

final class EmailAlreadyVerifiedException extends \Exception implements VerifyEmailExceptionInterface
{
    public function getReason(): string
    {
        return 'Account Already Confirmed.';
    }
}
