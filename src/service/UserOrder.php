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

namespace plugin\wemall\service;

use plugin\payment\model\PluginPaymentAddress;
use plugin\payment\model\PluginPaymentRecord;
use plugin\payment\service\Balance;
use plugin\payment\service\Integral;
use plugin\payment\service\Payment;
use plugin\wemall\model\PluginWemallConfigDiscount;
use plugin\wemall\model\PluginWemallOrder;
use plugin\wemall\model\PluginWemallOrderItem;
use plugin\wemall\model\PluginWemallOrderSend;
use plugin\wemall\model\PluginWemallUserRelation;
use think\admin\Exception;
use think\admin\Library;

/**
 * 商城订单数据服务
 * @class UserOrder
 * @package plugin\wemall\service
 */
class UserOrder
{
    /**
     * 获取随减金额
     * @return float
     * @throws \think\admin\Exception
     */
    public static function reduct(): float
    {
        $config = sysdata('plugin.wemall.config');
        if (empty($config['enable_reduct'])) return 0.00;
        $min = intval(($config['reduct_min'] ?? 0) * 100);
        $max = intval(($config['reduct_max'] ?? 0) * 100);
        return rand($min, $max) / 100;
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
        // 更新入会资格
        $entry = self::vipEntry($order['unid']);
        // 尝试绑定代理用户
        if (empty($user['puid1']) && ($order['puid1'] > 0 || $user['puid0'] > 0)) {
            $puid1 = $order['puid1'] > 0 ? $order['puid1'] : $user['puid0'];
            UserUpgrade::bindAgent($user['id'], $puid1);
        }
        // 重置订单推荐关系
        $user = PluginWemallUserRelation::mk()->where(['unid' => $order['unid']])->findOrEmpty();
        if ($user->isExists() && $user['puid1'] > 0) {
            $order->save(['puid1' => $user['puid1'], 'puid2' => $user['puid2']]);
        }
        // 重新计算用户等级
        UserUpgrade::upgrade($user['id'], true, $orderNo);
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

    /**
     * 更新订单收货地址
     * @param \plugin\wemall\model\PluginWemallOrder $order
     * @param \plugin\payment\model\PluginPaymentAddress $address
     * @return boolean
     * @throws \think\admin\Exception
     */
    public static function perfect(PluginWemallOrder $order, PluginPaymentAddress $address): bool
    {
        $unid = $order->getAttr('unid');
        $orderNo = $order->getAttr('order_no');
        // 根据地址计算运费
        $map1 = ['order_no' => $orderNo, 'status' => 1, 'deleted' => 0];
        $map2 = ['order_no' => $order->getAttr('order_no'), 'unid' => $unid];
        [$amount, $tCount, $tCode, $remark] = ExpressService::amount(
            PluginWemallOrderItem::mk()->where($map1)->column('delivery_code'),
            $address->getAttr('region_prov'), $address->getAttr('region_city'),
            (int)PluginWemallOrderItem::mk()->where($map2)->sum('delivery_count')
        );
        // 创建订单发货信息
        $extra = [
            'delivery_code'   => $tCode, 'delivery_count' => $tCount, 'unid' => $unid,
            'delivery_remark' => $remark, 'delivery_amount' => $amount, 'status' => 1,
        ];
        $extra['order_no'] = $orderNo;
        $extra['address_id'] = $address->getAttr('id');
        // 收货人信息
        $extra['user_name'] = $address->getAttr('user_name');
        $extra['user_phone'] = $address->getAttr('user_phone');
        $extra['user_idcode'] = $address->getAttr('idcode');
        $extra['user_idimg1'] = $address->getAttr('idimg1');
        $extra['user_idimg2'] = $address->getAttr('idimg2');
        // 收货地址信息
        $extra['region_prov'] = $address->getAttr('region_prov');
        $extra['region_city'] = $address->getAttr('region_city');
        $extra['region_area'] = $address->getAttr('region_area');
        $extra['region_addr'] = $address->getAttr('region_addr');
        $extra['extra'] = $extra;
        PluginWemallOrderSend::mk()->where(['order_no' => $orderNo])->findOrEmpty()->save($extra);
        // 组装更新订单数据
        $update = ['status' => 2, 'amount_express' => $extra['delivery_amount']];
        // 重新计算订单金额
        $update['amount_real'] = $order->getAttr('amount_discount') + $amount - $order->getAttr('amount_reduct');
        $update['amount_total'] = $order->getAttr('amount_goods') + $amount;
        // 支付金额不能为零
        if ($update['amount_real'] <= 0) $update['amount_real'] = 0.00;
        if ($update['amount_total'] <= 0) $update['amount_total'] = 0.00;
        // 更新用户订单数据
        if ($order->save($update)) {
            // 触发订单确认事件
            Library::$sapp->event->trigger('PluginWemallOrderPerfect', $order);
            // 返回处理成功数据
            return true;
        } else {
            return false;
        }
    }

    /**
     * 更新订单支付状态
     * @param PluginWemallOrder $order 订单模型
     * @param PluginPaymentRecord $payment 支付行为记录
     * @return array|null|void|boolean
     * @throws \think\admin\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @remark 订单状态(0已取消,1预订单,2待支付,3待审核,4待发货,5已发货,6已收货,7已评论)
     */
    public static function payment(PluginWemallOrder $order, PluginPaymentRecord $payment)
    {
        $orderNo = $payment->getAttr('order_no');
        $paidAmount = Payment::paidAmount($orderNo, true);

        // 提交支付凭证，只需更新订单状态
        $isVoucher = $payment->getAttr('channel_type') === Payment::VOUCHER;
        if ($isVoucher && $payment->getAttr('audit_status') === 1) return $order->save([
            'status'         => 3,
            'payment_time'   => date('Y-m-d H:i:s'),
            'payment_amount' => $paidAmount,
            'payment_status' => 1,
        ]);

        // 发起订单退款，标记订单已取消
        if (empty($paidAmount) && $payment->getAttr('refund_status')) {
            $order->save([
                'status'         => 0,
                'payment_time'   => $payment->getAttr('payment_time'),
                'payment_amount' => $paidAmount,
                'payment_status' => 1,
            ]);
            try { /* 取消订单余额积分奖励及反拥 */
                static::cancel($orderNo, true);
            } catch (\Exception $exception) {
                trace_file($exception);
            }
            return self::upgrade($orderNo);
        }

        // 订单已经支付完成
        if ($paidAmount >= $order->getAttr('amount_real')) {
            // 已完成支付
            $order->save([
                'status'         => $order->getAttr('delivery_type') ? 4 : 5,
                'payment_time'   => $payment->getAttr('payment_time'),
                'payment_amount' => $paidAmount,
                'payment_status' => 1,
            ]);
            try { /* 奖励余额及积分 */
                static::confirm($orderNo);
            } catch (\Exception $exception) {
                trace_file($exception);
            }
            try { /* 订单返佣处理 */
                UserRebate::create($orderNo);
            } catch (\Exception $exception) {
                trace_file($exception);
            }
            return self::upgrade($orderNo);
        }

        // 凭证支付审核被拒绝
        if ($isVoucher && $payment->getAttr('audit_status') !== 1) {
            $map = ['channel_type' => Payment::VOUCHER, 'audit_status' => 1, 'order_no' => $orderNo];
            if (PluginPaymentRecord::mk()->where($map)->findOrEmpty()->isEmpty()) {
                if ($order->getAttr('status') === 3) $order->save(['status' => 2]);
            }
            return self::upgrade($orderNo);
        } else {
            $order->save(['payment_amount' => $paidAmount]);
        }
    }

    /**
     * 验证订单取消余额
     * @param string $orderNo
     * @param boolean $syncRebate
     * @return string
     * @throws \think\admin\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function cancel(string $orderNo, bool $syncRebate = false): string
    {
        $map = ['status' => 0, 'order_no' => $orderNo];
        $order = PluginWemallOrder::mk()->where($map)->findOrEmpty();
        if ($order->isEmpty()) throw new Exception('订单状态异常');
        $code = "CZ{$order['order_no']}";
        // 取消余额奖励
        if ($order['reward_balance'] > 0) Balance::cancel($code);
        // 取消积分奖励
        if ($order['reward_integral'] > 0) Integral::cancel($code);
        // 取消订单返佣
        $syncRebate && UserRebate::cancel($orderNo);
        return $code;
    }

    /**
     * 订单支付发放余额
     * @param string $orderNo
     * @return string
     * @throws \think\admin\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function confirm(string $orderNo): string
    {
        $map = [['status', '>=', 4], ['order_no', '=', $orderNo]];
        $order = PluginWemallOrder::mk()->where($map)->findOrEmpty();
        if ($order->isEmpty()) throw new Exception('订单状态异常');
        $code = "CZ{$order['order_no']}";
        // 确认奖励余额
        if ($order['reward_balance'] > 0) {
            $remark = "来自订单 {$order['order_no']} 奖励 {$order['reward_balance']} 余额";
            Balance::create($order['unid'], $code, '购物奖励余额', $order['reward_balance'], $remark, true);

        }
        // 确认奖励积分
        if ($order['reward_integral'] > 0) {
            $remark = "来自订单 {$order['order_no']} 奖励 {$order['reward_integral']} 积分";
            Integral::create($order['unid'], $code, '购物奖励积分', $order['reward_integral'], $remark, true);
        }
        // 升级用户等级
        UserUpgrade::upgrade($order->getAttr('unid'), true, $orderNo);
        // 返回奖励单号
        return $code;
    }
}