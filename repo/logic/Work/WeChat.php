<?php
namespace Logic\Work;

use Utils\Client;
use Utils\Curl;
use Utils\Utils;

class WeChat extends \Logic\Logic{

    protected $key_token = 'wechat:token:';
    protected $key_click = 'wechat:click:';

    protected function getAccessToken($appid, $appsecret){
        $token = $this->ci->redis->get($this->key_token.$appid);
        if(!empty($token)){
            return [0, $token];
        }
        $t_json = Curl::get("https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$appsecret}");
//        {"access_token":"24_KZvKGt4-mbCnUhWVZotzN4JnJDRST1orJpYSU41JqgER3mYgYfV8xGXPRwYqorXaXGqXTO9CBpW5DghuYEs3D-ECW5-W1Tsr20P1L_ga1lKSoLLUKUToACQu1PNcmX3N-up5g7XNcOpBUFTgMJWgAAADAN","expires_in":7200}
//        {"errcode":40013,"errmsg":"invalid appid hint: [X8ae03482994]"}
        if(empty($t_json)){
            return [886, '请求获取token超时'];
        }else{
            $t_arr = json_decode($t_json, true);
            if(isset($t_arr['access_token']) && !empty($t_arr['access_token'])){
                $this->ci->redis->setex($this->key_token.$appid, 3600, $t_arr['access_token']);
                return [0, $t_arr['access_token']];
            }else{
                return [886, $t_arr['errcode'].':'.$t_arr['errmsg']];
            }
        }
    }

    public function getShortUrl($url){
        //获取可用的微信公众号
        $w_list= \DB::table('wechat')->where('account_status', 1)->orderBy('id','DESC')->get()->toArray();
        if(empty($w_list)){
            return $this->lang->set(886, ['暂无可用公众号']);
        }
        $w_id = array_rand($w_list);
        $token = $this->getAccessToken($w_list[$w_id]->app_id, $w_list[$w_id]->app_secret);
        if($token[0] !== 0){
            return $this->lang->set(886, [$token[1]]);
        }
        $para = [
            'action'=>'long2short',
            'long_url'=>$url,
        ];
        $t_json = Curl::post("https://api.weixin.qq.com/cgi-bin/shorturl?access_token={$token[1]}", null, $para);
//        {"errcode":0,"errmsg":"ok","short_url":"https:\/\/w.url.cn\/s\/AVgPn4S"}
        if(empty($t_json)){
            return $this->lang->set(886, ['请求获取短链超时']);
        }else{
            $t_arr = json_decode($t_json, true);
            if(isset($t_arr['errcode']) && $t_arr['errcode'] == 0){
                $key = $this->key_click.date('Ymd').':'.$w_list[$w_id]->app_id;
                $kit = $this->ci->redis->get($key);
                if(empty($kit)){
                    $this->ci->redis->setex($key, 24*3600, 1);
                }else{
                    $this->ci->redis->incr($key);
                }
                return $this->lang->set(0, [], $t_arr['short_url']);
            }else{
                return $this->lang->set(886, $t_arr['errmsg']);
            }
        }
    }

    public function getToday($app_id){
        $key = $this->key_click.date('Ymd').':'.$app_id;
        $kit = $this->ci->redis->get($key);
        return $kit ? $kit : 0;
    }

}





