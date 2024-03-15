<?php
namespace Func;
//合并多维数组
function _array_merge(array $arr,array $arr2){
    $re = [];
    foreach ($arr2 as $key=>$val){
        if(count($val) != count($val, 1) && isset($arr[$key])){
            $re[$key] = _array_merge($arr[$key], $val);
        }elseif(is_array($val) && isset($arr[$key])) {
            $re[$key] = array_merge($arr[$key], $val);
        }
    }
    $re = array_merge($arr2,$re);
    return $re;
}