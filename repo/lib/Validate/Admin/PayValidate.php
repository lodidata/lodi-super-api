<?php
/**
 * author      : ben <956841620@qq.com>
 * createTime  : 2018/4/09 23:25
 * description :
 */

namespace Lib\Validate\Admin;

use Lib\Validate\BaseValidate;

class PayValidate extends BaseValidate
{
    // 验证规则
    protected $rule = [
        "status" => "in:enabled,disabled",
        "customer_id" => "integer",
        "pay_id" => "integer",
        "channel_id" => "require|integer",
        "key" => "require",
        "pub_key" => "require",
        "partner_id" => "require",
        "scene" => "require|in:wx,alipay,unionpay,qq,jd",
        "show_type" => "require|in:js,quick,h5,code",
        "pay_con_id"=>"require|integer",
        "cust_name"=>"require",
        "isStatus"=>"require|in:isNo,isTrue",
        "pay_chan_id"=>"integer",
        "order_number"=>"require"
    ];

    //字段名称
    protected $field = [
        "status" => "状态",
        "customer_id" => "客户id",
        "pay_id" => "支付!",
        "channel_id" => "渠道id",
        "pub_key" => "公钥",
        "key" => "秘钥",
        "partner_id" => "商户号",
        "scene" => "类型",
        "show_type" => "分类",
        "pay_con_id"=>"支付id",
        "cust_name"=>"客户名称",
        "isStatus"=>"是否同步",
        "pay_chan_id"=>"支付渠道id",
        "order_number"=>"订单号"
    ];

    //自定义返回信息
    protected $message = [
//        'scene.in' => '状态查询格式错误',
//        'channel_id.require' => '渠道id必须'
    ];

    //验证规则名称
    protected $scene = [
        'get' => [
             'customer_id', 'pay_id'
        ],
        'put' => [
            'pay_con_id','customer_id'
        ],
        'putsta' => [
            'status'
        ],
        'post' => [
             'channel_id','customer_id', 'pub_key', 'key', 'partner_id'
        ],
        'post_type' => [
            'customer_id', 'pay_config_id','passageway_config_id'
        ],
        'put_type' => [
            'customer_id', 'pay_config_id','passageway_config_id','payurl','isStatus'
        ],
        'get_order' => [
            'customer_id','pay_chan_id'
        ],
        'get_order_callback'=>[
            'order_number'
        ]
    ];

    public function setValidate( array $data,string $action){
        if(isset($this->scene[$action])) {
            $this->scene[$action] = array_values(array_intersect($data, $this->scene[$action]));
            return $this->scene[$action];
        }
        return [];
    }

}