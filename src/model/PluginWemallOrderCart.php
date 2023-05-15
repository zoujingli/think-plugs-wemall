<?php

namespace plugin\wemall\model;

use think\model\relation\HasOne;

class PluginWemallOrderCart extends Abs
{
    /**
     * 关联产品数据
     * @return \think\model\relation\HasOne
     */
    public function goods(): HasOne
    {
        return $this->hasOne(PluginWemallGoods::class, 'code', 'gcode');
    }

    /**
     * 关联规格数据
     * @return \think\model\relation\HasOne
     */
    public function specs(): HasOne
    {
        return $this->hasOne(PluginWemallGoodsItem::class, 'ghash', 'ghash');
    }
}