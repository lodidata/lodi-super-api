<?php

namespace Logic\Define;

/**
 * Class Lang
 * 统一文本返回类
 * @package Logic\Define
 */
class Lang {

    private static $status = [
        886 => '第三方支付原因-%s',
        887 => '添加入款单失败，请联系客服',
        889 => '该通道已坏！请联系客服',
        890 => '充值金额区间：%s-%s元',
        891 => '该通道今日充值额度已剩%s',
        892 => '该通道初始化失败，请联系客服',
        893 => '该通道数据有误，请联系客服',
        894 => '未有该客户相关信息，请联系DBA添加',
        895 => '添加备用订单信息失败，请联系技术人员排查',
        896 => '发起订单失败，请联系第三方与我方同时排查',
        897 => '请求数据有误，请联系商务或技术人员核查',
        898 => '请联系技术人员配置回调地址',
        900 => '请求第三方支付失败，请联系我方技术人员',
        901 => '该通道只能充值金额需要带尾数',
        902 => '该通道只能充值整数',
        903 => '该通道充值金额只能为%s',
        904 => '该通道充值金额只能为整百',
        1062 => '数据异常',
    ];
    /**
     * 赋值
     * @param       $state
     * @param array $stateParams
     * @param array $data
     *
     * @return $this
     */
    public static function get($state,$data = []) {
        if($data)
            self::$status[$state] = vsprintf(self::$status[$state],$data);
        return self::$status[$state];
    }


}