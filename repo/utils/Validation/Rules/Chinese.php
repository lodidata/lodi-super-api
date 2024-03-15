<?php

namespace Utils\Validation\Rules;

use Respect\Validation\Rules\AbstractRule;
use Respect\Validation\Validator as V;
class Chinese extends AbstractRule
{
    public function validate($input)
    {
        return V::regex('/^[\x{4e00}-\x{9fa5}]+$/u')->noWhitespace()->length(2, 10)->validate($input);
    }
}