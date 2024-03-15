<?php
use Lib\Validate\BaseValidate;
use Logic\Admin\BaseController;

/**
 * 修改商品信息
 */
return new class extends BaseController
{
    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run($id = null)
    {
        $this->checkID($id);
        (new BaseValidate([
            'desc' => 'require',
        ]))->paramsCheck('', $this->request, $this->response);

        $params = $this->request->getParams();

        $data = [
            'detail'=>json_encode($params),
        ];
        $result = DB::table('goods')->where('id', '=', $id)->update($data);
        if ($result !== false) {
            return $this->lang->set(0);
        }
        return $this->lang->set(-2);
    }

};