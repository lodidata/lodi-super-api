<?php
use Utils\Shop\Action;

return new class extends Action {
    const TITLE = "GET 首页 广告";
    const TYPE = "text/json";
    const QUERY = [
        "type" => "int(optional) #广告类型(1：pc，2：h5)",
    ];
    const SCHEMAs = [
        200 => [
            "pic"      => "string(optional) #图片地址",
            "language" => "string(optional) #语言",
            "link"     => "string(optional) #活动跳转链接",
        ],
    ];

    public function run()
    {
        $type = !$this->auth->isMobilePlatform() ? 1 : 2;
        $data = DB::table('advert')->where('advert.type', 'banner')
                          ->leftjoin('active', 'advert.link', '=', 'active.id')
                          ->where('advert.pf', $type == 1 ? 'pc' : 'h5')
                          ->where('advert.status', 'enabled')
                          ->whereRaw('(active.status = \'enabled\' OR advert.link_type = 1)')
                          ->selectRaw('advert.link_type,advert.link,advert.picture,type_id')
                          ->orderby('advert.sort')
                          ->get()
                          ->toArray();

        $arr = [];
        if (!empty($data)) {
            foreach ($data as $v) {
                $v = (array)$v;
                $temp = [];
                $temp['m_pic'] = $v['picture'];
                $temp['pic'] = $v['picture'];
                $temp['link'] = $v['link'];
                $temp['link_type'] = $v['link_type'];
                $temp['type_id'] = $v['type_id'];
                $temp['active_id'] = '';
                if ($temp['link_type'] == 2) {
                    $temp['active_id'] = $v['link'];
                }
                $temp['language'] = "zh";
                array_push($arr, $temp);
            }
        }
//            if (!empty($data)) {
//                $this->redis->setex(\Logic\Define\CacheKey::$perfix['banner'] . $type, 5, json_encode($arr));
//            }
        return $arr;
    }
};