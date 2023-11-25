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

declare (strict_types=1);

namespace plugin\wemall\controller\api\auth;

use plugin\wemall\controller\api\Auth;
use plugin\wemall\service\UserOrderService;
use plugin\wemall\service\UserRebateService;
use plugin\wemall\service\UserUpgradeService;

/**
 * 会员中心
 * @class Center
 * @package plugin\wemall\controller\api\auth
 */
class Center extends Auth
{
    /**
     * 控制器初始化
     */
    protected function initialize()
    {
        parent::initialize();
        $this->checkUserStatus(false);
    }

    /**
     * 获取会员资料
     * @throws \think\admin\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function get()
    {
        $account = $this->account->get();
        if (empty($account['user']['extra']['level_name'])) {
            UserUpgradeService::recount($this->unid, true);
            $account = $this->account->get();
        }
        $this->success('获取资料成功！', [
            'account' => $account, 'relation' => $this->relation,
        ]);
    }

    /**
     * 获取会员等级
     * @throws \think\admin\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function levels()
    {
        $this->success('获取会员等级！', UserRebateService::levels());
    }

    /**
     * 获取会员折扣
     */
    public function discount()
    {
        $data = $this->_vali(['discount.require' => '折扣不能为空！']);
        [, $rate] = UserOrderService::discount(intval($data['discount']), $this->levelCode);
        $this->success('获取会员折扣！', ['rate' => floatval($rate)]);
    }
}