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

use think\model\relation\HasMany;

/**
 * 商城商品数据模型
 * @class PluginWemallGoods
 * @package plugin\wemall\model
 */
class PluginWemallGoods extends Abs
{

    /**
     * 关联产品规格
     * @return \think\model\relation\HasMany
     */
    public function items(): HasMany
    {
        return static::mk()
            ->hasMany(PluginWemallGoodsItem::class, 'gcode', 'code')
            ->withoutField('id,status,create_time,update_time')
            ->where(['status' => 1]);
    }

    public static function lists(): array
    {
        $model = static::mk()->with('items')->withoutField('specs');
        return $model->order('sort desc,id desc')->where(['deleted' => 0])->select()->toArray();
    }

    /**
     * 标签处理
     * @param mixed $value
     * @return array
     */
    public function getMarksAttr($value): array
    {
        if (empty($items = sysvar('PluginWemallGoodsMarkItems'))) {
            $items = sysvar('PluginWemallGoodsMarkItems', PluginWemallGoodsMark::items());
        }
        return str2arr(is_array($value) ? arr2str($value) : $value, ',', $items);
    }

    /**
     * 处理商品分类数据
     * @param mixed $value
     * @return array
     */
    public function getCatesAttr($value): array
    {
        if (empty($cates = sysvar('PluginWemallGoodsCateItem'))) {
            $cates = sysvar('PluginWemallGoodsCateItem', PluginWemallGoodsCate::items(true));
        }
        $cateids = is_string($value) ? str2arr($value) : (array)$value;
        foreach ($cates as $cate) if (in_array($cate['id'], $cateids)) return $cate;
        return [];
    }

    public function getSliderAttr($value): array
    {
        return is_string($value) ? str2arr($value, '|') : [];
    }

    public function setSpecsAttr($value): string
    {
        return is_array($value) ? json_encode($value, 64 | 256) : (string)$value;
    }

    public function getSpecsAttr($value): array
    {
        return is_string($value) ? json_decode($value, true) : [];
    }
}