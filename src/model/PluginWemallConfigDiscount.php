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
 * 用户优惠方案模型
 * @class PluginWemallConfigDiscount
 * @package plugin\wemall\model
 */
class PluginWemallConfigDiscount extends Abs
{

    /**
     * 获取折扣方案
     * @param boolean $allow
     * @return array[]
     */
    public static function items(bool $allow = false): array
    {
        $query = self::mk()->where(['status' => 1, 'deleted' => 0]);
        $items = $allow ? ['0' => ['id' => '0', 'name' => '无折扣']] : [];
        return $items + $query->order('sort desc,id desc')->column('id,name,items', 'id');
    }

    /**
     * 格式化等级规则
     * @param mixed $value
     * @return mixed
     */
    public function getItemsAttr($value)
    {
        return empty($value) ? $value : json_decode($value, true);
    }
}