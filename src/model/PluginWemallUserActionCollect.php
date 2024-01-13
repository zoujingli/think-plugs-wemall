<?php

// +----------------------------------------------------------------------
// | WeMall Plugin for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2022~2024 ThinkAdmin [ thinkadmin.top ]
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

namespace plugin\wemall\model;

use plugin\account\model\Abs;
use think\model\relation\HasOne;

class PluginWemallUserActionCollect extends Abs
{
    /**
     * 关联商品信息
     * @return \think\model\relation\HasOne
     */
    public function goods(): HasOne
    {
        return $this->hasOne(PluginWemallGoods::class, 'code', 'gcode');
    }
}