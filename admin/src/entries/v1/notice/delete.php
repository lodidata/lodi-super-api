<?php
/**
 * 超管公告删除
 * @author Taylor 2019-01-21
 */

return new class extends Logic\Admin\BaseController
{
    const TITLE = '超管公告删除';
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
        $this->checkID($id);

        $result = DB::table('super_notice')->delete($id);
        if ($result !== false) {
            return $this->lang->set(0);
        }
        return $this->lang->set(-2);
    }
};