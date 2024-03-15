<?php
namespace Utils\Admin;

class Action {

    protected $ci;

    /**
     * 前置操作方法列表
     * @var array $beforeActionList
     * @access protected
     */
    protected $beforeActionList = [];

    public function init($ci) {
        $this->ci = $ci;
        if (strtolower($this->request->getMethod()) == 'get') {
            $data = $this->request->getQueryParams();
            $data['page'] = isset($data['page']) ? $data['page'] : 1;
            $data['page_size'] = isset($data['page_size']) ? $data['page_size'] : 10;
            $this->ci->request = $this->ci->request->withQueryParams($data);
        }

    }

    public function before(){
        if ($this->beforeActionList) {
            foreach ($this->beforeActionList as $method) {
                call_user_func([$this, $method]);
            }
        }
    }

    public function __get($field) {
        if (!isset($this->$field)) {
            return $this->ci->$field;
        } else {
            return $this->$field;
        }
    }


}
