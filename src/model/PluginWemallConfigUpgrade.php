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

namespace plugin\wemall\model;

/**
 * 用户等级配置模型
 * @class PluginWemallConfigUpgrade
 * @package plugin\wemall\model
 */
class PluginWemallConfigUpgrade extends Abs
{
    /**
     * 获取用户等级
     * @param boolean $allow
     * @return array
     */
    public static function items(bool $allow = false): array
    {
        $items = $allow ? [-1 => ['name' => '非入会礼包', 'prefix' => '-', 'number' => -1, 'upgrade_team' => 0]] : [];
        return $items + static::mk()->where(['status' => 1])
                ->hidden(['id', 'utime', 'status', 'create_at'])
                ->order('number asc')->column('name,number as prefix,number,upgrade_team', 'number');
    }

    /**
     * 获取最大级别数
     * @return integer
     * @throws \think\db\exception\DbException
     */
    public static function maxNumber(): int
    {
        if (static::mk()->count() < 1) return 0;
        return static::mk()->max('number') + 1;
    }
}