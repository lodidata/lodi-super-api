<?php
    //  外部中间跳转文件
    $METHODS = array(
        'POST' => 'form',//表单提交
        'GET' => 'goahead',  //直接跳转
        'GET_FORM' => 'form',  //表单提交
        'HTML' => 'html',  //表单提交
//        'CURL_COMMON_POST' => 'curl_common_post',
    );

    $GATEWAY = $_REQUEST['url'] ?? '';
    $METHOD = $_REQUEST['method2'] ? $_REQUEST['method2'] : $_REQUEST['method'];
    $DATA = array();
    unset($_REQUEST['url']);
    if(isset($_REQUEST['method2']))
        unset($_REQUEST['method2']);
    else
        unset($_REQUEST['method']);
    $DATA = $_REQUEST;
    if(isset($METHODS[$METHOD])&&function_exists($METHODS[$METHOD])) {
        $html = $METHODS[$METHOD]($DATA, $GATEWAY);
        echo $html;
        die();
    }else{
        echo '参数出错';die();
    }

    function html($data) {
        return base64_decode($data['html']);
    }

    function form($data,$GATEWAY){
        GLOBAL $METHOD;
        $temp = explode('_',$METHOD);
        $m = $temp[0] ?? 'POST';
        $html = "<form method='$m' name='PAY_FORM' action='$GATEWAY'>";
        foreach ($data as $key => $val) {
            $html .= "<input type='hidden' name='$key' value='$val' />";
        }
        $html .= "</form>";
        return $html.'<script> document.PAY_FORM.submit();</script>';
    }
    function goahead($data,$GATEWAY){
        $str = urlstring($data);
        if(strpos($GATEWAY,'?' === FALSE))
            $html = $GATEWAY.'?'.$str;
        else
            $html = $GATEWAY.'&'.$str;
        return "<script> window.location.href = '{$html}' </script>";
    }

//    function curl_common_post($data, $GATEWAY) {
//        // $data,$GATEWAY
//        $ch = curl_init();
//        curl_setopt($ch, CURLOPT_URL, $GATEWAY);
//        curl_setopt($ch, CURLOPT_POST, 1);
//        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
//        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
//        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
//        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
//        $backStr = curl_exec($ch);
//        curl_close($ch);
//        return $backStr;
//    }

    function urlstring($parameter){
        ksort($parameter);
        $signStr = '';
        foreach ($parameter as $k=>$v) {
            $signStr .= "$k=$v&";
        }
        $signStr = trim($signStr,'&');
        return $signStr;
    }

    function getDecode($data){
        $result = array();
        foreach ($data as $k=>$v) {
            $result[urldecode($k)] = urldecode($v);
        }
        return $result;
    }
