<?php
use Utils\Work\Action;
use Lib\Validate\BaseValidate;

/**
 * 更新未开奖的开奖结果
 */
return new class extends Action{

    public function run($id){
        if(empty($id)){
            return $this->lang->set(10);
        }
        $validate = new BaseValidate([
            'period_result'   => 'require',
            'period_code'   => 'require',
        ], [
            'period_result'    => '开奖结果不能为空',
            'period_code'    => '中奖号码不能为空',
        ]);
        $validate->paramsCheck('', $this->request, $this->response);

        $data = $this->request->getParams();

        $l_data = \DB::connection('common')->table('lottery_info')->where('id', $id)->first();
        if(empty($l_data)){
            return $this->lang->set(886, ['彩期不存在']);
        }
        if(!empty($l_data->state)){
            return $this->lang->set(886, ['彩期已开奖']);
        }
        $period = explode(',', $data['period_code']);
        if(count($period) < 2){
            return $this->lang->set(886, ['开奖号码格式不合法']);
        }
        foreach($period as $key=>$val){
            $i_data['n'.($key+1)] = intval($val);
        }

        $i_data['period_code'] = $data['period_code'];
        $i_data['period_result'] = $data['period_result'];
        $i_data['catch_time'] = time();
        $i_data['official_time'] = time();
        $i_data['state'] = 'open';
        \DB::connection('common')->table('lottery_info')->where('id', $id)->update($i_data);
        $this->lang->set(0);
    }

};