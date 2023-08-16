<?php

// +----------------------------------------------------------------------
// | WeMall Plugin for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2022~2023 ThinkAdmin [ thinkadmin.top ]
// +----------------------------------------------------------------------
// | 官方网站: https://thinkadmin.top
// +----------------------------------------------------------------------
// | 免责声明 ( https://thinkadmin.top/disclaimer )
// | 会员免费 ( https://thinkadmin.top/vip-introduce )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/think-plugs-wemall
// | github 代码仓库：https://github.com/zoujingli/think-plugs-wemall
// +----------------------------------------------------------------------

namespace plugin\wemall\controller\api\auth;

use plugin\payment\service\Balance;
use plugin\payment\service\Integral;
use plugin\wemall\controller\api\Auth;
use plugin\wemall\service\UserOrderService;

class Center extends Auth
{
    protected function initialize()
    {
        parent::initialize();
        $this->checkUserStatus(false);
    }

    /**
     * 获取会员资料
     * @return void
     * @throws \think\admin\Exception
     */
    public function get()
    {
        $account = $this->account->get();
        if (empty($account['user']['extra'])) {
            Balance::recount($this->unid);
            Integral::recount($this->unid);
            $account = $this->account->get();
        }
        $this->success('获取资料成功', [
            'account'  => $account,
            'relation' => $this->relation,
        ]);
    }

    /**
     * 绑定手机号
     * @return void
     */
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

    /**
     * 获取会员折扣
     * @return void
     */
    public function discount()
    {
        $data = $this->_vali(['discount.require' => '折扣编号不能为空！']);
        [, $rate] = UserOrderService::discount(intval($data['discount']), $this->levelCode);
        $this->success('会员折扣', ['rate' => floatval($rate)]);
    }
}