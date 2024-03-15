<?php
/**
 * 超管后台公告的发布
 * @author Taylor 2019-01-21
 */

use Logic\Admin\BaseController;

return new class extends BaseController
{
    const TITLE = '超管后台公告的发布';
    const TYPE = 'text/json';
    const PARAMs = [
        'id' => 'int(required)公告id',
    ];
    const SCHEMAs = [
        200 => []
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run($id)
    {
        if (empty($id))
            return $this->lang->set(10);
        $notice = DB::table('super_notice')->where('id', $id)->select(['status'])->first();
        if (!$notice) {
            return $this->lang->set(886, ['公告不存在']);
        }
        if($notice->status == 1){
            return $this->lang->set(886, ['公告已发布']);
        }

        DB::table('super_notice')->where('id', $id)->update(['status' => 1, 'pub_time' => date('Y-m-d H:i:s')]);
        return $this->lang->set(0);
    }
};