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
 * 商城商品规格模型
 * @class PluginWemallGoodsItem
 * @package plugin\wemall\model
 */
class PluginWemallGoodsItem extends Abs
{
    /**
     * 获取商品规格JSON数据
     * @param string $code
     * @return string
     */
    public static function itemsJson(string $code): string
    {
        return json_encode(self::itemsArray($code), 64 | 256);
    }

    /**
     * 获取商品规格数组
     * @param string $code
     * @return array
     */
    public static function itemsArray(string $code): array
    {
        return self::mk()->where(['gcode' => $code])->column([
            'gsku'            => 'sku',
            'ghash'           => 'ghash',
            'gcode'           => 'gcode',
            'gspec'           => 'key',
            'status'          => 'status',
            'price_market'    => 'market',
            'price_selling'   => 'selling',
            'number_virtual'  => 'virtual',
            'number_express'  => 'express',
            'reward_balance'  => 'balance',
            'reward_integral' => 'integral',
        ], 'gspec');
    }
}