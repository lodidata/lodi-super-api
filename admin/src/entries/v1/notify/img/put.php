<?php
/**
 * 修改图片域名
 */
return new class extends Logic\Admin\BaseController
{
//    protected $beforeActionList = [
//        'verifyToken', 'authorize',
//    ];

    public function run()
    {
        $params=$this->request->getParams();

        if((!isset($params['old_img_notify']) && empty($params['old_img_notify'])) ||(!isset($params['new_img_notify']) && empty($params['new_img_notify']))){
            return $this->lang->set(886,['新域名或旧域名为必传参数']);
        }
        $arr=['old_img_notify'=>$params['old_img_notify'],'new_img_notify'=>$params['new_img_notify']];

        \Logic\Recharge\Recharge::requestPaySit('updateImgNotify','all',$arr,[]);

        return $this->lang->set(0);


    }
};