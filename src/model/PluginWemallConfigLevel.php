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

/**
 * 商城会员等级数据
 * @class PluginWemallConfigLevel
 * @package plugin\wemall\model
 */
class PluginWemallConfigLevel extends Abs
{
    /**
     * 获取会员等级
     * @param ?string $first 增加首项内容
     * @param string $field 指定查询字段
     * @return array
     */
    public static function items(string $first = null, string $field = 'name,number as prefix,number,upgrade_team,extra'): array
    {
        try {
            $query = static::mk()->withoutField('id,utime,status,update_time,create_time');
            $items = $query->field($field)->where(['status' => 1])->order('number asc')->select()->toArray();
            if ($first) array_unshift($items, ['name' => $first, 'prefix' => '-', 'number' => -1, 'upgrade_team' => 0, 'extra' => []]);
            return $items;
        } catch (\Exception $exception) {
            trace_file($exception);
            return [];
        }
    }

    /**
     * 获取最大级别数
     * @return integer
     * @throws \think\db\exception\DbException
     */
    public static function maxNumber(): int
    {
        if (static::mk()->count() < 1) return 0;
        return intval(static::mk()->max('number') + 1);
    }
}