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

namespace plugin\wemall\service;

use plugin\wemall\model\PluginWemallGoods;
use plugin\wemall\model\PluginWemallGoodsItem;
use plugin\wemall\model\PluginWemallGoodsStock;
use plugin\wemall\model\PluginWemallOrder;
use plugin\wemall\model\PluginWemallOrderItem;
use think\admin\Service;

/**
 * 商品数据服务
 * @class GoodsService
 * @package plugin\wemall\service
 */
class GoodsService extends Service
{
    /**
     * 更新商品库存数据
     * @param string $code
     * @return boolean
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function stock(string $code): bool
    {
        // 入库统计
        $query = PluginWemallGoodsStock::mk()->field('ghash,ifnull(sum(gstock),0) stock_total');
        $stockList = $query->where(['gcode' => $code])->group('gcode,gspec')->select()->toArray();
        // 销量统计
        $query = PluginWemallOrder::mk()->alias('a')->field('b.ghash,ifnull(sum(b.stock_sales),0) stock_sales');
        $query->join([PluginWemallOrderItem::mk()->getTable() => 'b'], 'a.order_no=b.order_no', 'left');
        $query->where([['b.gcode', '=', $code], ['a.status', '>', 0], ['a.deleted_status', '=', 0]]);
        $salesList = $query->group('b.ghash')->select()->toArray();
        // 组装数据
        $items = [];
        foreach (array_merge($stockList, $salesList) as $vo) {
            $key = $vo['ghash'];
            $items[$key] = array_merge($items[$key] ?? [], $vo);
            if (empty($items[$key]['stock_sales'])) $items[$key]['stock_sales'] = 0;
            if (empty($items[$key]['stock_total'])) $items[$key]['stock_total'] = 0;
        }
        unset($salesList, $stockList);
        // 更新商品规格销量及库存
        foreach ($items as $hash => $item) {
            PluginWemallGoodsItem::mk()->where(['ghash' => $hash])->update([
                'stock_total' => $item['stock_total'], 'stock_sales' => $item['stock_sales']
            ]);
        }
        // 更新商品主体销量及库存
        PluginWemallGoods::mk()->where(['code' => $code])->update([
            'stock_total'   => intval(array_sum(array_column($items, 'stock_total'))),
            'stock_sales'   => intval(array_sum(array_column($items, 'stock_sales'))),
            'stock_virtual' => PluginWemallGoodsItem::mk()->where(['gcode' => $code])->sum('number_virtual'),
        ]);
        return true;
    }
}