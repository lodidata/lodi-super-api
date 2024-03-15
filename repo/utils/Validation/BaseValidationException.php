<?php

/*
 * This file is part of Respect/Validation.
 *
 * (c) Alexandre Gomes Gaigalas <alexandre@gaigalas.net>
 *
 * For the full copyright and license information, please view the "LICENSE.md"
 * file that was distributed with this source code.
 */
namespace Utils\Validation;
class BaseValidationException extends \Respect\Validation\Exceptions\NestedValidationException
{
    public function setName($name)
    {
        $name = $name == 'null' || empty($name) ? $this->name : $name;
        parent::setName($name);
    }
}