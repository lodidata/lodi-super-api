<?php

use Utils\Www\Action;

return new class extends Action
{

    public function run()
    {
        $params = $this->request->getParams();
        if (empty($params['id'])) {
            die ('无效请求,缺少支付id');
        }
        $id = $params['id'];

        $redis_key = 'pay_request_data_' . $id;
        $json = $this->redis->get($redis_key);
        if (empty($json)) {
            die ('支付订单已超时或不存在(超时时间为60s)');
        }

        //访问了就直接删除，防止恶意盗刷
        $this->redis->del($redis_key);

        $data = json_decode($json, true);

        if ($data) {
            echo $this->form($data['data'], $data['url'], $data['method']);
            die();
        }
        die ('支付订单请求数据异常');
    }

    function form($data, $GATEWAY, $METHOD)
    {
        $m = $METHOD ?? 'POST';
        $html = "<form method='$m' name='PAY_FORM' action='$GATEWAY'>";
        foreach ($data as $key => $val) {
            $html .= "<input type='hidden' name='$key' value='$val' />";
        }
        $html .= "</form>";
        return $html . '<script> document.PAY_FORM.submit();</script>';
    }
};