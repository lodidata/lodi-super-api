<?php

namespace Utils\Validation\Rules;

use Respect\Validation\Rules\AbstractRule;
use Respect\Validation\Validator as V;
class InvitCode extends AbstractRule
{
    public function validate($input)
    {
        return V::noWhitespace()->validate($input);
    }
}