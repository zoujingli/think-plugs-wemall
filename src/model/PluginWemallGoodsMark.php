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

namespace plugin\wemall\model;

/**
 * 商城商品标题模型
 * @class PluginWemallGoodsMark
 * @package plugin\wemall\model
 */
class PluginWemallGoodsMark extends Abs
{
    /**
     * 获取所有标签
     * @return array
     */
    public static function items(): array
    {
        $map = ['status' => 1];
        return static::mk()->where($map)->order('sort desc,id desc')->column('name');
    }
}