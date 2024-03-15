<?php
use Utils\Work\Action;

/**
 * 签名  邮件相关
 */
return new class extends Action{

    public function run(){
        $params = $this->request->getParams();
        if(!$this->isSignLogin()){
            die('error');
        }
        $action = $params['action'];
        try{
            return $this->$action($params);
        }catch (\Exception $e){
            return $this->lang->set(0);
        }

    }

    public function updateDetail($params){
        $id = $this->setEmail($params,true);
        if(isset($params['templates']) && $id) {
            foreach ($params['templates'] as $val) {
                $val['scene'] = $id ;
                $this->setTemplates($val);
            }
        }
        return $this->lang->set(0);
    }

    public function setEmail($params,$l = false){
        $update = [];
        foreach ($params as $key=>$val){
            if(in_array($key,['use','mailhost','mailport','mailname','mailpass','mailaddress','verification','is_ssl'])){
                $update[$key] = $val;
            }
        }
        if(!$update){
            return $this->lang->set(0);
        }
        $update['updated'] = time();
        if(isset($params['id']) && $params['id']) {
            $id = $params['id'];
            \DB::table('mail_config')->where('id', $params['id'])->update($update);
        }else{
            $update['created'] = time();
            $id = \DB::table('mail_config')->insertGetId($update);
        }
        return $l ? $id : $this->lang->set(0);
    }

    public function deleteEmail($params){
        if(isset($params['id']) && $params['id']) {
            \DB::table('mail_config')->delete( $params['id']);
            \DB::table('mail_template')->where('scene',$params['id'])->delete();
        }
        return $this->lang->set(0);
    }

    public function setTemplates($params){
        $update = [];
        foreach ($params as $key=>$val){
            if(in_array($key,['scene','subject','desc']) && $val){
                $update[$key] = $val;
            }
        }
        if(!$update){
            return $this->lang->set(0);
        }
        if(isset($params['id']) && $params['id']) {
            \DB::table('mail_template')->where('id', $params['id'])->update($update);
        }else{
            \DB::table('mail_template')->insert($update);
        }
        return $this->lang->set(0);
    }

    public function deleteTemplates($params){
        if(isset($params['id']) && $params['id']) {
            \DB::table('mail_template')->delete( $params['id']);
        }
        return $this->lang->set(0);
    }

};