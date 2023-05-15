<?php

namespace plugin\wemall\controller\api\auth;

use plugin\account\controller\api\Auth;

class Center extends Auth
{
    protected function initialize()
    {
        parent::initialize();
        $this->checkUserStatus(false);
    }

    public function bind()
    {
        $bind = $this->account->get();
        if (empty($bind['unionid'])) {
            $this->error('无效终端用户！');
        } elseif ($this->account->isBind()) {
            $this->success('已经绑定成功！');
        } else {
            $this->account->bind(['unionid' => $bind['unionid']], [
                'phone'    => $bind['phone'],
                'headimg'  => $bind['headimg'],
                'nickname' => $bind['nickname'],
            ]);
            $this->success('绑定账号成功！');
        }
    }
}