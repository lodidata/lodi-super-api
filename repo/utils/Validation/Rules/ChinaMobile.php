<?php

namespace Utils\Validation\Rules;

use Respect\Validation\Rules\AbstractRule;
use Respect\Validation\Validator as V;

class ChinaMobile extends AbstractRule
{
    public function validate($input)
    {
        $res = V::intVal()->noWhitespace()->length(11)->validate($input);
        if($res && !preg_match("/^1[3456789]{1}\d{9}$/",$input)){
            return false;
        }
        return $res;
    }
}