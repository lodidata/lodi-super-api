<?php
namespace Logic\Spider;
use Requests;
use Server\Utils;
use Workerman\Lib\Timer;

/**
 *
 * 彩票控API
 */
interface Api {
    
    public function getFast($fetchOne);

    public function getHistory($fetchOne);

}