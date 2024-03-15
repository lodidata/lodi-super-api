<?php

/*
 * This file is part of Respect/Validation.
 *
 * (c) Alexandre Gomes Gaigalas <alexandre@gaigalas.net>
 *
 * For the full copyright and license information, please view the "LICENSE.md"
 * file that was distributed with this source code.
 */

namespace Utils\Validation\Exceptions;
class IdcardException extends \Utils\Validation\BaseValidationException
{
    protected $name = '身份证';

    public static $defaultTemplates = [
        self::MODE_DEFAULT => [
            self::STANDARD => '{{name}}格式不正确，请重新输入',
        ],
        self::MODE_NEGATIVE => [
            self::STANDARD => '{{name}}格式不正确，请重新输入',
        ],
    ];
}