<?php
use Logic\Admin\BaseController;

return new class() extends BaseController {
    const TITLE = '资金流水记录';
    //前置方法
    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run() {
        $username = $this->request->getParam('username');
        $user_id = $this->request->getParam('user_id');
        $deal_category = $this->request->getParam('deal_category');//交易类别：1收入，2支出
        $deal_type = $this->request->getParam('deal_type');//交易类型：101 线上充值，102 线下入款，202 商品购买
        $order_number = $this->request->getParam('order_number');
        $stime = $this->request->getParam('start_time');
        $etime = $this->request->getParam('end_time');
        $page = $this->request->getParam('page', 1);
        $size = $this->request->getParam('page_size', 10);

        $query = DB::table('funds_deal_log')
                   ->select([
                       'funds_deal_log.balance',
                       'funds_deal_log.created',
                       'funds_deal_log.admin_id',
                       'funds_deal_log.admin_user',
//                       'user.tags',
                       'funds_deal_log.deal_category',
                       'funds_deal_log.deal_money',
                       \DB::raw('CONCAT(funds_deal_log.order_number,"") AS order_number'),
                       'funds_deal_log.deal_number',
                       'funds_deal_log.deal_type',
                       'funds_deal_log.id',
                       'funds_deal_log.memo',
                       'funds_deal_log.username',
//                       'withdraw_bet',
//                       'total_bet',
//                       'total_require_bet',
//                       'free_money',
                       'user.id as user_id'
                   ])
                   ->leftJoin('user', 'funds_deal_log.user_id', '=', 'user.id');

        $stime && $query->where('funds_deal_log.created', '>=', $stime . ' 00:00:00');
        $etime && $query->where('funds_deal_log.created', '<=', $etime . ' 23:59:59');
        $user_id && $query->where('funds_deal_log.user_id', '=', $user_id);
        $username && $query->where('funds_deal_log.username', '=', $username);
        $order_number && $query->where('funds_deal_log.order_number', '=', $order_number);
        $deal_type && $query->whereRaw("funds_deal_log.deal_type in  ({$deal_type})");
        $deal_category && $query->where('funds_deal_log.deal_category', '=', $deal_category);

        $count = clone $query;
        $attributes['total'] = $count->count();

        $attributes['size'] = $size;
        $attributes['number'] = $page;
        $data = $query->orderby('funds_deal_log.created', 'desc')
                      ->orderby('funds_deal_log.id', 'desc')
                      ->forPage($page, $size)
                      ->get()
                      ->toArray();

        // dd(DB::getQueryLog());exit;
        foreach ($data as &$datum) {
            $types = \Logic\Shop\FundsDealLog::getDealLogTypeFlat();
            $datum->deal_type_name = $types[$datum->deal_type] ?? '';
            $datum->deal_number = (string)$datum->deal_number;
//            if ($datum->free_money > $datum->balance) {
//                $datum->free_money = $datum->balance;
//            }
        }

        return $this->lang->set(0, [], $data, $attributes);
    }
};
