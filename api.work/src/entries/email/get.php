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

    public function getList($params){
        $query = \DB::table('mail_config');
        $params['page'] = $params['page'] ?? 1;
        $params['size'] = $params['size'] ?? 20;
        isset($params['use']) && $params['use'] && $query->where('use',$params['use']);
        $total = $query->count();
        $data = $query->forPage($params['page'],$params['size'])->get()->toArray();
        return $this->lang->set(0,[],$data,['page'=>$params['page'],'size'=>$params['size'],'total'=>$total]);
    }

    public function getDetail($params){
        $data = (array)\DB::table('mail_config')->where('id',$params['id'])->first();
        $params['scene'] = $params['id'];
        $data['templates'] = $this->getTemplates($params,true);
        return $this->lang->set(0,[],$data);
    }

    public function getTemplates($params,$l = false){
        $query = \DB::table('mail_template');
        $params['page'] = $params['page'] ?? 1;
        $params['size'] = $params['size'] ?? 20;
        isset($params['scene']) && $params['scene'] && $query->where('scene',$params['scene']);
        if($l){
            return $query->get()->toArray();
        }
        $total = $query->count();
        $data = $query->forPage($params['page'],$params['size'])->get()->toArray();
        return $this->lang->set(0,[],$data,['page'=>$params['page'],'size'=>$params['size'],'total'=>$total]);
    }

    public function getSendLogs($params){
        $query = \DB::table('mail_send_log');
        $params['page'] = $params['page'] ?? 1;
        $params['size'] = $params['size'] ?? 20;
        $signs = \DB::table('sign')->get()->toArray();
        $signs = array_column($signs,'name','code');
        isset($params['scene']) && $params['scene'] && $query->where('scene',$params['scene']);
        $total = $query->count();
        $data = $query->forPage($params['page'],$params['size'])->orderBy('id','DESC')->get()->toArray();
        foreach ($data as &$val){
            $val = (array)$val;
            $val['useName'] = $signs[$val['use']] ?? '未知';
        }
        return $this->lang->set(0,[],$data,['page'=>$params['page'],'size'=>$params['size'],'total'=>$total]);
    }
};