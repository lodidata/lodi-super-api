<?php
/**
 * Created by PhpStorm.
 * User: tyleryang
 * Date: 2018/3/22
 * Time: 12:37
 */

namespace Logic;

use Slim\Container;

abstract class Logic {
    protected $ci;

    public function __construct(Container $ci) {
        $this->ci = $ci;
    }

    public function __get($field) {
        if(isset($this->$field)) {
            return $this->$field;
        }
        return $this->ci->$field;
    }
}