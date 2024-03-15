<?php

    require_once  __DIR__.'/../../repo/utils/Curl.php';
    $method = $_REQUEST['method'];
    $url = $_REQUEST['url'];
    $data = $_REQUEST['params'];
    switch ($method) {
        case 'GET' : $re = \Utils\Curl::get($url);  break;
        case 'POST' : $re = \Utils\Curl::post($url,'',$data);  break;
        case 'POST_DATA' : $re = \Utils\Curl::post($url,'',json_decode($data,true));  break;
        case 'POST_HEADER' : $re = \Utils\Curl::post_header($url,'',$data); break;
        default  : $re = \Utils\Curl::commonPost($url,'',$data);
    }

    die(base64_encode($re));


