<?php
use Lib\Validate\BaseValidate;
use Logic\Admin\BaseController;

/**
 * 添加商品信息
 */
return new class extends BaseController{
    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run(){
        (new BaseValidate([
            'name' => 'require',
            'customer_name' => 'require',
            'logo' => 'require',
            'down_url' => 'require',
            'price' => 'require',
            'before_prob' => 'require',
            'after_prob' => 'require',
            'start_time' => 'require',
            'end_time' => 'require',
        ]))->paramsCheck('', $this->request, $this->response);

        $params = $this->request->getParams();

        if($params['before_prob'] > $params['after_prob']){
            return $this->lang->set(886, ['购买前好牌率不能大于购买后好牌率']);
        }

        if($params['start_time'] > $params['end_time']){
            return $this->lang->set(886, ['上架不能大于下架时间']);
        }

        $data = [
            'name'=>$params['name'],
            'customer_name'=>$params['customer_name'],
            'logo'=>$params['logo'],
            'down_url'=>$params['down_url'],
            'price'=>$params['price'],
            'before_prob'=>$params['before_prob'],
            'after_prob'=>$params['after_prob'],
            'start_time'=>$params['start_time'],//上架时间
            'end_time'=>$params['end_time'],//下架时间
            'creater_id'=>$this->playLoad['uid'],
            'creater'=>$this->playLoad['nick'],
        ];
        $result = DB::table('goods')->insertGetId($data);
        if ($result) {
            return $this->lang->set(0);
        }
        return $this->lang->set(-2);
    }

};