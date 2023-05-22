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

use plugin\wemall\model\PluginWemallConfigDiscount;
use plugin\wemall\model\PluginWemallGoods;
use plugin\wemall\model\PluginWemallGoodsItem;
use plugin\wemall\model\PluginWemallOrder;
use plugin\wemall\model\PluginWemallOrderCart;
use plugin\wemall\model\PluginWemallOrderItem;
use plugin\wemall\model\PluginWemallUserRelation;
use think\admin\Exception;
use think\admin\Service;
use think\Model;

/**
 * 商城订单数据服务
 * @class OrderService
 * @package plugin\wemall\service
 */
class OrderService extends Service
{
    /**
     * 获取随减金额
     * @return float
     */
    public static function reduct(): float
    {
        return rand(1, 100) / 100;
    }

    /**
     * 解析商品数据
     * @param integer $unid 用户编号
     * @param string $rules 直接下单
     * @param string $carts 购物车下单
     * @return array
     * @throws \think\admin\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function parse(int $unid, string $rules, string $carts): array
    {
        // 读取商品数据
        [$lines, $carts] = [[], str2arr($carts)];
        if (!empty($carts)) {
            $where = [['unid', '=', $unid], ['id', 'in', $carts]];
            $field = ['ghash' => 'ghash', 'gcode' => 'gcode', 'gspec' => 'gspec', 'number' => 'count'];
            PluginWemallOrderCart::mk()->field($field)->where($where)->with([
                'goods' => function ($query) {
                    $query->where(['status' => 1, 'deleted' => 0]);
                    $query->withoutField(['specs', 'content', 'status', 'deleted', 'create_time', 'update_time']);
                },
                'specs' => function ($query) {
                    $query->where(['status' => 1]);
                    $query->withoutField(['status', 'create_time', 'update_time']);
                }
            ])->select()->each(function (Model $model) use (&$lines) {
                if (isset($lines[$ghash = $model->getAttr('ghash')])) {
                    $lines[$ghash]['count'] += $model->getAttr('count');
                } else {
                    $lines[$ghash] = $model->toArray();
                }
            });
        } elseif (!empty($rules)) {
            foreach (explode(';', $rules) as $rule) {
                [$ghash, $count] = explode(':', "{$rule}:1");
                if (isset($lines[$ghash])) {
                    $lines[$ghash]['count'] += $count;
                } else {
                    $lines[$ghash] = ['ghash' => $ghash, 'gcode' => '', 'gspec' => '', 'count' => $count];
                }
            }
            // 读取规格数据
            $map1 = [['status', '=', 1], ['ghash', 'in', array_column($lines, 'ghash')]];
            foreach (PluginWemallGoodsItem::mk()->where($map1)->select()->toArray() as $item) {
                foreach ($lines as &$line) if ($line['ghash'] === $item['ghash']) {
                    [$line['gcode'], $line['gspec'], $line['specs']] = [$item['gcode'], $item['gspec'], $item];
                }
            }
            // 读取商品数据
            $map2 = [['status', '=', 1], ['deleted', '=', 0], ['code', 'in', array_unique(array_column($lines, 'gcode'))]];
            foreach (PluginWemallGoods::mk()->where($map2)->withoutField(['specs', 'content'])->select()->toArray() as $goods) {
                foreach ($lines as &$line) if ($line['gcode'] === $goods['code']) $line['goods'] = $goods;
            }
        } else {
            throw new Exception('无效参数数据！');
        }
        return array_values($lines);
    }

    /**
     * 同步订单关联商品的库存
     * @param string $orderNo 订单编号
     * @return boolean
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function stock(string $orderNo): bool
    {
        $map = ['order_no' => $orderNo];
        $codes = PluginWemallOrderItem::mk()->where($map)->column('gcode');
        foreach (array_unique($codes) as $code) GoodsService::stock($code);
        return true;
    }

    /**
     * 根据订单更新用户等级
     * @param string $orderNo
     * @return array|null [USER, ORDER, ENTRY]
     * @throws \think\admin\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function upgrade(string $orderNo): ?array
    {
        // 目标订单数据
        $map = [['order_no', '=', $orderNo], ['status', '>=', 4]];
        $order = PluginWemallOrder::mk()->where($map)->findOrEmpty();
        if ($order->isEmpty()) return null;
        // 订单用户数据
        $user = PluginWemallUserRelation::mk()->where(['unid' => $order['unid']])->findOrEmpty();
        if ($user->isEmpty()) return null;
        // 更新用户购买资格
        $entry = static::vipEntry($order['unid']);
        // 尝试绑定代理用户
        if (empty($user['pids']) && ($order['puid1'] > 0 || $user['pid1'] > 0)) {
            $puid1 = $order['puid1'] > 0 ? $order['puid1'] : $user['pid0'];
            UserUpgradeService::bindAgent($user['id'], $puid1);
        }
        // 重置用户信息并绑定订单
        $user = PluginWemallUserRelation::mk()->where(['unid' => $order['unid']])->findOrEmpty();
        if ($user->isExists() && $user['pid1'] > 0) {
            $order->save(['puid1' => $user['pid1'], 'puid2' => $user['pid2']]);
        }
        // 重新计算用户等级
        UserUpgradeService::upgrade($user['id'], true, $orderNo);
        return [$user, $order, $entry];
    }

    /**
     * 刷新用户入会礼包
     * @param integer $unid 用户UID
     * @return integer
     * @throws \think\db\exception\DbException
     */
    private static function vipEntry(int $unid): int
    {
        // 检查是否购买入会礼包
        $query = PluginWemallOrder::mk()->alias('a')->join([PluginWemallOrderItem::mk()->getTable() => 'b'], 'a.order_no=b.order_no');
        $entry = $query->where("a.unid={$unid} and a.status>=4 and a.payment_status=1 and b.level_upgrade>-1")->count() ? 1 : 0;
        // 用户最后支付时间
        $lastMap = [['unid', '=', $unid], ['status', '>=', 4], ['payment_status', '=', 1]];
        $lastDate = PluginWemallOrder::mk()->where($lastMap)->order('payment_time desc')->value('payment_time');
        // 更新用户支付信息
        PluginWemallUserRelation::mk()->where(['id' => $unid])->update(['buy_vip_entry' => $entry, 'buy_last_date' => $lastDate]);
        return $entry;
    }

    /**
     * 获取等级折扣比例
     * @param integer $disId 折扣方案ID
     * @param integer $levelCode 等级序号
     * @param float $disRate 默认比例
     * @return array [方案编号, 折扣比例]
     */
    public static function discount(int $disId, int $levelCode, float $disRate = 100.00): array
    {
        if ($disId > 0) {
            $where = ['id' => $disId, 'status' => 1, 'deleted' => 0];
            $discount = PluginWemallConfigDiscount::mk()->where($where)->findOrEmpty();
            if ($discount->isExists()) foreach ($discount['items'] as $vo) {
                if ($vo['level'] == $levelCode) $disRate = floatval($vo['discount']);
            }
        }
        return [$disId, $disRate];
    }
}