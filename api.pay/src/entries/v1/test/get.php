<?php
use Utils\Www\Action;
use QL\QueryList;
return new class extends Action{
    public function run($order_number = '196853245769995542', $money = 2000,$pay_id = 2)
    {

        $content = <<<STR
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    </head>
    <body>
        <form id = "pay_form" action='http://www.unspay.com/unspay/page/linkbank/payRequest.do' method='POST' accept-charset="UTF-8">
            <input type="hidden" name='currencyType' value='CNY'/>
            <input type="hidden" name='responseMode' value='3'/>
            <input type="hidden" name='amount' value='100.00'/>
            <input type="hidden" name='orderId' value='7236840322650931206'/>
            <input type="hidden" name='bankCardType' value='D'/>
            <input type="hidden" name='remark' value='20180915145909'/>
            <input type="hidden" name='frontURL' value='https://www.baidu.com'/>
            <input type="hidden" name='accessMode' value='WAP'/>
            <input type="hidden" name='version' value='unionpayNew'/>
            <input type="hidden" name='mac' value='195b47f0ca50a08c0f9968b973836e27'/>
            <input type="hidden" name='assuredPay' value='false'/>
            <input type="hidden" name='merchantId' value='2120180820175928001'/>
            <input type="hidden" name='time' value='20180915145909'/>
            <input type="hidden" name='merchantUrl' value='http://notify.i6pay.com/notify/93'/>
        </form>
    </body>
    <script type="text/javascript">document.all.pay_form.submit();</script>
</html>

STR;

        $data = QueryList::html($content)->rules([  //设置采集规则
            // 采集所有a标签的href属性
            'input' => ['$(":input")']
            // 采集所有a标签的文本内容
        ])->query()->getData()->toArray();

        $content = '<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    </head>
    <body>
        <form id = "pay_form" action=\'http://www.unspay.com/unspay/page/linkbank/payRequest.do\' method=\'POST\' accept-charset="UTF-8">
            <input type="hidden" name=\'currencyType\' value=\'CNY\'/>
            <input type="hidden" name=\'responseMode\' value=\'3\'/>
            <input type="hidden" name=\'amount\' value=\'100.00\'/>
            <input type="hidden" name=\'orderId\' value=\'7236840322650931206\'/>
            <input type="hidden" name=\'bankCardType\' value=\'D\'/>
            <input type="hidden" name=\'remark\' value=\'20180915145909\'/>
            <input type="hidden" name=\'frontURL\' value=\'https://www.baidu.com\'/>
            <input type="hidden" name=\'accessMode\' value=\'WAP\'/>
            <input type="hidden" name=\'version\' value=\'unionpayNew\'/>
            <input type="hidden" name=\'mac\' value=\'195b47f0ca50a08c0f9968b973836e27\'/>
            <input type="hidden" name=\'assuredPay\' value=\'false\'/>
            <input type="hidden" name=\'merchantId\' value=\'2120180820175928001\'/>
            <input type="hidden" name=\'time\' value=\'20180915145909\'/>
            <input type="hidden" name=\'merchantUrl\' value=\'http://notify.i6pay.com/notify/93\'/>
        </form>
    </body>
    <script type="text/javascript">document.all.pay_form.submit();</script>
</html>"';





        $test = new Logic\Recharge\Recharge($this->ci);
        print_r($test->creaseCustomer('201806141842582381',1000));
        $pay_site = $this->ci->get('settings')['PaySite'];
        $url = $pay_site['payrequest'].'://'.$pay_site['host'].DIRECTORY_SEPARATOR.$pay_site['customer'].DIRECTORY_SEPARATOR.$pay_site['path'];
        $param = ['order_number'=>$order_number,'money'=>$money,'third'=>$pay_id];
        $res = \Utils\Curl::get($url.'?'.http_build_query($param));
        return json_decode($res,true);
    }
};