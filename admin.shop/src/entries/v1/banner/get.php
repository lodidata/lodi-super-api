<?php
use Logic\Admin\Advert as advertLogic;
use Logic\Admin\BaseController;
use Respect\Validation\Validator as v;

/**
 * 轮播广告列表
 */
return new class() extends BaseController {
    const TITLE       = '轮播广告列表';
    const DESCRIPTION = 'PC轮播广告/H5轮播广告';
    const QUERY       = [
        'pf'        => 'enum[pc,h5] #平台，可选值：pc h5',
        'page'      => 'int #第几页',
        'page_size' => 'int #每页多少条'
    ];
    const SCHEMAs     = [
        200 => ['map # position: [home 首页,egame 电子页,live 视讯页,lottery 彩票页,sport 体育页,coupon 优惠页,agent 代理页]']
    ];
    
    //前置方法
    protected $beforeActionList = [
        'verifyToken','authorize'
    ];

    public function run() {
        $params = $this->request->getParams();
//        $validation = $this->validator->validate($this->request, [
//            'pf' => v::notEmpty()->noWhitespace()->in(['pc','h5'])->setName('平台'),
//        ]);
//
//        if (!$validation->isValid()) {
//            return $validation;
//        }
        
        $pf = isset($params['pf']) && !empty($params['pf']) ? $params['pf'] : 'h5';

        $query = DB::table('advert')->from('advert as ad')
            ->leftJoin('active as a','ad.link','=','a.id')
            ->selectRaw('ad.*,a.title as link_name,type_id')
            ->where('ad.pf', $pf)
            ->where('ad.type','banner')
            ->where('ad.status','!=', 'deleted');

        $total = $query->count();
        $res = $query->orderBy('ad.created','desc')->forPage($params['page'], $params['page_size'])->get()->toArray();
        return $this->lang->set(0, [], $res, ['number' => $params['page'], 'size' => $params['page_size'], 'total' => $total]);
    }
};