<?php
/**
 * Created by PhpStorm.
 * User: tyleryang
 * Date: 2018/3/21
 * Time: 14:16
 */

namespace Logic\Define;
use Slim\Container;

/**
 * Class Lang
 * 统一文本返回类
 * @package Logic\Define
 */
class ErrMsg {

    protected $ci;

    protected $state;

    protected $defaultHttpCode = 400;

    protected $defaultErrorState = 21;

    protected $stateParams = [];

    protected $data;

    protected $define;

    protected $attributes;

    public function __construct(Container $ci, array $define = []) {
        $this->ci = $ci;
        $this->define = $define;
    }

    /**
     * 赋值
     * @param       $state
     * @param array $stateParams
     * @param array $data
     *
     * @return $this
     */
    public function set($state, $stateParams = [], $data = [], $attributes = null) {
        $this->state = intval($state);
        $this->stateParams = $stateParams;
        $this->data = $data;
        $this->attributes = $attributes;
        return $this;
    }

    /**
     * 取值
     * @return array [http状态码，state值, msg, data]
     * @throws \Exception
     */
    public function get() {
        if (!isset($this->define[$this->state])) {
            $this->state = $this->defaultErrorState;
            if (!isset($this->define[$this->state])) {
                throw new \Exception('缺失定义');
            }
        }

        $res = explode('|', $this->define[$this->state]);
        if (!empty($this->stateParams)) {
            $res[0] = call_user_func_array('sprintf', array_merge([$res[0]], $this->stateParams));
        }

        if (!isset($res[1])) {
            $res[1] = $this->defaultHttpCode;
        }

        return [intval($res[1]), intval($this->state), $res[0], $this->data, $this->attributes];
    }

    /**
     * 取数据
     * @return [type] [description]
     */
    public function getData() {
        return $this->get()[3];
    }

    /**
     * 判断是否可以继续下一步
     * @return bool
     */
    public function allowNext() {
        return $this->get()[0] == 200 ? true : false;
    }

    public function __get($field) {
        if(isset($this->$field)) {
            return $this->$field;
        }
        return $this->ci->$field;
    }
}