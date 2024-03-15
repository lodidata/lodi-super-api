<?php
namespace Utils\Shop;

class Action {
    protected $page = 1;
    protected $page_size = 20;
    protected $ci;
    /**
     * 前置操作方法列表
     * @var array $beforeActionList
     * @access protected
     */
    protected $beforeActionList = [];

    protected $ipProtect = true;

    protected $maintaining = false;
    // public function __construct($ci)
    // {
    //     $this->ci = $ci;
    //     if ($this->beforeActionList) {
    //         foreach ($this->beforeActionList as $method ) {

    //             call_user_func([$this, $method]);

    //         }
    //     }
    // }

    public function init($ci) {

        $this->ci = $ci;

        // 系统维护性开关
//        if (in_array($this->request->getMethod(), ['POST', 'PUT', 'PATCH', "DELETE"]) && \Logic\Set\SystemConfig::getGlobalSystemConfig('system')['maintaining']) {
//            return $this->lang->set(5);
//        }

        // ip 请求流量保护
//        if (isset($website['ipProtect']) && $website['ipProtect'] && $this->ipProtect) {
//            $res = \Utils\Client::getApiProtectByIP();
//            if (!$res->allowNext()) {
//                return $res;
//            }
//        }

        if ($this->beforeActionList) {
            foreach ($this->beforeActionList as $method) {
                call_user_func([$this, $method]);
            }
        }
    }

    public function __get($field) {
        if (!isset($this->{$field})) {
            return $this->ci->{$field};
        } else {
            return $this->{$field};
        }
    }

}
