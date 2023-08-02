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

use plugin\wemall\controller\api\Auth;
use plugin\wemall\model\PluginWemallUserRelation;
use think\admin\helper\QueryHelper;

/**
 * 推广用户管理
 * @class Spread
 * @package plugin\wemall\controller\api\auth
 */
class Spread extends Auth
{
    /**
     * 获取我推广的用户
     * @return void
     */
    public function get()
    {
        PluginWemallUserRelation::mQuery(null, function (QueryHelper $query) {
            $query->with(['user'])->where(['puid0' => $this->unid])->order('id desc');
            $this->success('获取数据成功！', $query->page(intval(input('page', 1)), false, false, 10));
        });
    }
}