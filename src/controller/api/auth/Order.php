<?php

namespace plugin\wemall\controller\api\auth;

use plugin\payment\model\PluginPaymentAddress;
use plugin\payment\service\Balance;
use plugin\payment\service\Integral;
use plugin\payment\service\Payment;
use plugin\wemall\controller\api\Auth;
use plugin\wemall\model\PluginWemallOrder;
use plugin\wemall\model\PluginWemallOrderItem;
use plugin\wemall\model\PluginWemallOrderSend;
use plugin\wemall\service\ExpressService;
use plugin\wemall\service\GoodsService;
use plugin\wemall\service\OrderService;
use plugin\wemall\service\UserUpgradeService;
use think\admin\extend\CodeExtend;
use think\admin\helper\QueryHelper;
use think\exception\HttpResponseException;

class Order extends Auth
{

    /**
     * 获取订单数据
     * @return void
     */
    public function get()
    {
        PluginWemallOrder::mQuery(null, function (QueryHelper $query) {
            $query->where(['unid' => $this->unid])->with('items');
            $query->in('status')->equal('order_no')->order('id desc');
            $this->success('获取订单成功！', $query->page(true, false, false, 20));
        });
    }

    /**
     * 创建订单数据
     * @return void
     */
    public function add()
    {
        try {
            // 请求参数检查
            $input = $this->_vali(['carts.default' => '', 'rules.default' => '', 'agent.default' => '0']);
            if (empty($input['rules']) && empty($input['carts'])) $this->error('商品参数无效');
            // 绑定代理数据
            $order = UserUpgradeService::withAgent($this->unid, intval($input['agent']), $this->relation);
            [$items, $deliveryType, $order['order_no']] = [[], 0, CodeExtend::uniqidDate(16, 'N')];
            // 组装订单数据
            foreach (OrderService::parse($this->unid, trim($input['rules'], ':;'), $input['carts']) as $item) {
                if (empty($item['count'])) continue;
                if (empty($item['goods']) || empty($item['specs'])) $this->error('商品无效');
                [$goods, $gspec, $count] = [$item['goods'], $item['specs'], intval($item['count'])];
                // 订单物流类型
                if (empty($deliveryType) && $goods['delivery_code'] !== 'NONE') $deliveryType = 1;
                // 限制购买数量
                if (isset($goods['limit_maxnum']) && $goods['limit_maxnum'] > 0) {
                    $join = [PluginWemallOrderItem::mk()->getTable() => 'b'];
                    $where = [['a.unid', '=', $this->unid], ['a.status', 'in', [2, 3, 4, 5]], ['b.gcode', '=', $goods['code']]];
                    $buyCount = PluginWemallOrder::mk()->alias('a')->join($join, 'a.order_no=b.order_no')->where($where)->sum('b.stock_sales');
                    if ($buyCount + $count > $goods['limit_maxnum']) $this->error('商品限购');
                }
                // 限制购买身份
                if ($goods['limit_lowvip'] > $this->levelCode) $this->error('等级不够');
                // 商品库存检查
                if ($gspec['stock_sales'] + $count > $gspec['stock_total']) $this->error('库存不足');
                // 商品折扣处理
                [$discountId, $discountRate] = OrderService::discount($goods['discount_id'], $this->levelCode);
                // 订单详情处理
                $items[] = [
                    'unid'                  => $order['unid'],
                    'order_no'              => $order['order_no'],
                    // 商品字段
                    'gsku'                  => $gspec['gsku'],
                    'gname'                 => $goods['name'],
                    'gcode'                 => $gspec['gcode'],
                    'ghash'                 => $gspec['ghash'],
                    'gspec'                 => $gspec['gspec'],
                    'gcover'                => $goods['cover'],
                    // 库存数量处理
                    'stock_sales'           => $count,
                    // 快递发货数据
                    'delivery_code'         => $goods['delivery_code'],
                    'delivery_count'        => $goods['rebate_type'] > 0 ? $gspec['number_express'] * $count : 0,
                    // 商品费用字段
                    'price_market'          => $gspec['price_market'],
                    'price_selling'         => $gspec['price_selling'],
                    // 商品费用统计
                    'total_price_market'    => $gspec['price_market'] * $count,
                    'total_price_selling'   => $gspec['price_selling'] * $count,
                    'total_allow_balance'   => $gspec['allow_balance'] * $count,
                    'total_allow_integral'  => $gspec['allow_integral'] * $count,
                    'total_reward_balance'  => $gspec['reward_balance'] * $count,
                    'total_reward_integral' => $gspec['reward_integral'] * $count,
                    // 用户等级
                    'level_code'            => $this->levelCode,
                    'level_name'            => $this->levelName,
                    'level_upgrade'         => $goods['level_upgrade'],
                    // 是否参与返利
                    'rebate_type'           => $goods['rebate_type'],
                    'rebate_amount'         => $goods['rebate_type'] > 0 ? $gspec['price_selling'] * $count : 0,
                    // 等级优惠方案
                    'discount_id'           => $discountId,
                    'discount_rate'         => $discountRate,
                    'discount_amount'       => $discountRate * $gspec['price_selling'] * $count / 100,
                ];
            }
            // 默认使用销售销售
            $order['rebate_amount'] = array_sum(array_column($items, 'rebate_amount'));
            $order['reward_balance'] = array_sum(array_column($items, 'total_reward_balance'));
            $order['reward_integral'] = array_sum(array_column($items, 'total_reward_integral'));
            // 订单发货类型
            $order['status'] = $deliveryType ? 1 : 2;
            $order['delivery_type'] = $deliveryType;
            // 统计商品数量
            $order['number_goods'] = array_sum(array_column($items, 'stock_sales'));
            $order['number_express'] = array_sum(array_column($items, 'delivery_count'));
            // 统计商品金额
            $order['amount_goods'] = array_sum(array_column($items, 'total_selling'));
            // 优惠折扣金额
            $order['amount_discount'] = array_sum(array_column($items, 'discount_amount'));
            // 订单随减金额
            $order['amount_reduct'] = OrderService::reduct();
            if ($order['amount_reduct'] > $order['amount_goods']) {
                $order['amount_reduct'] = $order['amount_goods'];
            }
            // 统计订单金额
            $order['amount_real'] = $order['amount_discount'] - $order['amount_reduct'];
            $order['amount_total'] = $order['amount_goods'];
            // 写入商品数据
            $this->app->db->transaction(function () use ($order, $items) {
                PluginWemallOrder::mk()->save($order);
                PluginWemallOrderItem::mk()->saveAll($items);
            });
            // 同步商品库存销量
            foreach (array_unique(array_column($items, 'gcode')) as $gcode) {
                GoodsService::stock($gcode);
            }
            // 触发订单创建事件
            $this->app->event->trigger('PluginWemallOrderCreate', $order);
            // 无需发货且无需支付，直接完成支付流程
            if ($order['status'] === 2 && empty($order['amount_real'])) {
                Payment::emptyPayment($this->account, $order['order_no']);
                $this->success('下单成功', PluginWemallOrder::mk()->where(['order_no' => $order['order_no']])->findOrEmpty()->toArray());
            }
            // 返回处理成功数据
            $this->success('下单成功', array_merge($order, ['items' => $items]));
        } catch (HttpResponseException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            $this->error("下单失败，{$exception->getMessage()}");
        }
    }

    /**
     * 获取会员折扣
     * @return void
     */
    public function discount()
    {
        $data = $this->_vali(['discount.require' => '折扣编号不能为空！']);
        [, $rate] = OrderService::discount(intval($data['discount']), $this->levelCode);
        $this->success('会员折扣', ['rate' => floatval($rate)]);
    }

    /**
     * 模拟计算运费
     * @return void
     * @throws \think\admin\Exception
     */
    public function express()
    {
        $data = $this->_vali([
            'unid.value'         => $this->unid,
            'order_no.require'   => '单号不能为空',
            'address_id.require' => '地址不能为空',
        ]);

        // 用户收货地址
        $map = ['unid' => $this->unid, 'id' => $data['address_id']];
        $addr = PluginPaymentAddress::mk()->where($map)->findOrEmpty();
        if ($addr->isEmpty()) $this->error('收货地址异常');

        // 订单状态检查
        $map = ['unid' => $this->unid, 'order_no' => $data['order_no']];
        $tCount = PluginWemallOrderItem::mk()->where($map)->sum('delivery_count');

        // 根据地址计算运费
        $map = ['status' => 1, 'deleted' => 0, 'order_no' => $data['order_no']];
        $tCode = PluginWemallOrderItem::mk()->where($map)->column('delivery_code');
        [$amount, , , $remark] = ExpressService::amount($tCode, $addr['region_prov'], $addr['region_city'], $tCount);
        $this->success('计算运费', ['amount' => $amount, 'remark' => $remark]);
    }

    /**
     * 确认收货地址
     * @return void
     * @throws \think\admin\Exception
     */
    public function perfect()
    {
        $data = $this->_vali([
            'unid.value'         => $this->unid,
            'order_no.require'   => '单号不能为空',
            'address_id.require' => '地址不能为空',
        ]);

        // 用户收货地址
        $where = ['id' => $data['address_id'], 'unid' => $this->unid, 'deleted' => 0];
        $address = PluginPaymentAddress::mk()->where($where)->findOrEmpty();
        if ($address->isEmpty()) $this->error('地址异常');

        // 订单状态检查
        $map1 = ['unid' => $this->unid, 'order_no' => $data['order_no'], 'delivery_type' => 1];
        $order = PluginWemallOrder::mk()->where($map1)->whereIn('status', [1, 2])->findOrEmpty();
        if ($order->isEmpty()) $this->error('不能修改地址');

        // 根据地址计算运费
        $map1 = ['order_no' => $data['order_no'], 'unid' => $this->unid];
        $map2 = ['order_no' => $data['order_no'], 'status' => 1, 'deleted' => 0];
        $tCount = PluginWemallOrderItem::mk()->where($map1)->sum('delivery_count');
        $tCodes = PluginWemallOrderItem::mk()->where($map2)->column('delivery_code');
        [$amount, $tCount, $tCode, $remark] = ExpressService::amount($tCodes, $address['region_prov'], $address['region_city'], $tCount);

        // 创建订单发货信息
        $extra = [
            'delivery_code'   => $tCode, 'delivery_count' => $tCount, 'unid' => $this->unid,
            'delivery_remark' => $remark, 'delivery_amount' => $amount, 'status' => 1,
        ];
        $extra['order_no'] = $data['order_no'];
        $extra['address_id'] = $data['address_id'];

        // 收货人信息
        $extra['user_name'] = $address['user_name'];
        $extra['user_phone'] = $address['user_phone'];
        $extra['user_idcode'] = $address['idcode'];
        $extra['user_idimg1'] = $address['idimg1'];
        $extra['user_idimg2'] = $address['idimg2'];

        // 收货地址信息
        $extra['region_prov'] = $address['region_prov'];
        $extra['region_city'] = $address['region_city'];
        $extra['region_area'] = $address['region_area'];
        $extra['region_addr'] = $address['region_addr'];

        $extra['extra'] = $extra;

        PluginWemallOrderSend::mk()->where(['order_no' => $data['order_no']])->findOrEmpty()->save($extra);

        // 组装更新订单数据
        $update = ['status' => 2, 'amount_express' => $extra['delivery_amount']];
        // 重新计算订单金额
        $update['amount_real'] = $order['amount_discount'] + $amount - $order['amount_reduct'];
        $update['amount_total'] = $order['amount_goods'] + $amount;
        // 支付金额不能为零
        if ($update['amount_real'] <= 0) $update['amount_real'] = 0.00;
        if ($update['amount_total'] <= 0) $update['amount_total'] = 0.00;
        // 更新用户订单数据
        if ($order->save($update)) {
            // 触发订单确认事件
            $this->app->event->trigger('PluginWemallOrderPerfect', $order->refresh()->toArray());
            // 订单无需支付，直接完成支付流程
            if (empty($update['amount_real'])) {
                Payment::emptyPayment($this->account, $data['order_no']) && $order->refresh();
            }
            // 返回处理成功数据
            $this->success('订单确认成功', $order->toArray());
        } else {
            $this->error('订单确认失败');
        }
    }

    /**
     * 获取支付通道
     * @return void
     */
    public function channel()
    {
        $this->success('获取支付通道', Payment::typesByAccess($this->type, true));
    }

    /**
     * 获取支付参数
     * @return void
     */
    public function payment()
    {
        $data = $this->_vali([
            'unid.value'            => $this->unid,
            'balance.default'       => '0.00',
            'integral.default'      => '0',
            'order_no.require'      => '单号不能为空',
            'order_ps.default'      => '',
            'channel_code.require'  => '支付不能为空',
            'payment_back.default'  => '', # 支付回跳地址
            'payment_image.default' => '', # 支付凭证图片
        ]);
        try {
            $order = $this->getOrderModel();
            if ($order->getAttr('status') !== 2) $this->error('不能发起支付');
            if ($order->getAttr('payment_status') > 0) $this->error('已经完成支付');
            empty($data['order_ps']) || $order->save(['order_ps' => $data['order_ps']]);
            // 返回订单数据及支付发起参数
            if (empty($orderAmount = $order->getAttr('amount_real'))) {
                $this->success('订单支付成功', Payment::emptyPayment($this->account, $data['order_no']));
            }
            $payAmount = Payment::leaveAmount($data['order_no']);
            if ($payAmount <= 0) $this->success('该订单已经完成支付！');

            if ($data['integral'] > 0) {
                if ($data['integral'] > $order->getAttr('allow_integral')) {
                    $this->error("抵扣不得超过 {$order->getAttr('balance')} 积分");
                }
                $integral = Integral::recount($this->unid);
                if ($data['integral'] > $integral['usable']) $this->error('账号积分不足！');
                $param = Payment::mk(payment::INTEGRAL)->create($this->account, $data['order_no'], '账号积分抵扣', $orderAmount, $data['integral'], '');
            }

            if ($data['balance'] > 0) {
                if ($data['balance'] > $order->getAttr('allow_balance')) {
                    $this->error("余额不得超过 {$order->getAttr('balance')} 元");
                }
                $balance = Balance::recount($this->unid);
                if ($data['balance'] > $balance['usable']) $this->error('账号余额不足！');
                $param = Payment::mk(Payment::BALANCE)->create($this->account, $data['order_no'], '账号余额支付', $orderAmount, $data['balance'], '');
            }

            $payAmount = Payment::leaveAmount($data['order_no']);
            if ($payAmount <= 0) $this->success('已经完成支付', $param ?? []);

            $param = Payment::mk($data['channel_code'])->create($this->account, $data['order_no'], $orderAmount, $payAmount, '商城订单支付', '', $data['payment_back'], $data['payment_image']);
            $this->success('订单支付参数', $param);
        } catch (HttpResponseException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }
    }

    /**
     * 取消未支付订单
     * @return void
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\db\exception\DataNotFoundException
     */
    public function cancel()
    {
        $order = $this->getOrderModel();
        if (in_array($order->getAttr('status'), [1, 2, 3])) {
            $data = [
                'status'        => 0,
                'cancel_time'   => date('Y-m-d H:i:s'),
                'cancel_status' => 1,
                'cancel_remark' => '用户主动取消订单',
            ];
            if ($order->save($data) && OrderService::stock($order->getAttr('order_no'))) {
                // 触发订单取消事件
                $this->app->event->trigger('PluginWemallOrderCancel', $order->toArray());
                // 返回处理成功数据
                $this->success('订单取消成功');
            } else {
                $this->error('订单取消失败');
            }
        } else {
            $this->error('订单不可取消');
        }
    }

    /**
     * 删除已取消订单
     * @return void
     */
    public function remove()
    {
        $order = $this->getOrderModel();
        if ($order->isEmpty()) $this->error('读取订单失败');
        if ($order->getAttr('status') == 0) {
            $data = [
                'status'         => 0,
                'deleted_time'   => date('Y-m-d H:i:s'),
                'deleted_status' => 1,
                'deleted_remark' => '用户主动删除订单',
            ];
            if ($order->save($data)) {
                // 触发订单删除事件
                $this->app->event->trigger('PluginWemallOrderRemove', $order->toArray());
                // 返回处理成功数据
                $this->success('订单删除成功');
            } else {
                $this->error('订单删除失败');
            }
        } else {
            $this->error('订单不可删除');
        }
    }

    /**
     * 订单确认收货
     * @return void
     */
    public function confirm()
    {
        $order = $this->getOrderModel();
        if ($order->getAttr('status') == 5 && $order->save(['status' => 6])) {
            // 触发订单确认事件
            $this->app->event->trigger('PluginWemallOrderConfirm', $order->refresh()->toArray());
            // 返回处理成功数据
            $this->success('订单确认成功');
        } else {
            $this->error('订单确认失败');
        }
    }

    /**
     * 订单状态统计
     * @return void
     */
    public function total()
    {
        $data = ['t0' => 0, 't1' => 0, 't2' => 0, 't3' => 0, 't4' => 0, 't5' => 0, 't6' => 0, 't7' => 0];
        $query = PluginWemallOrder::mk()->where(['unid' => $this->unid, 'deleted_status' => 0]);
        foreach ($query->field('status,count(1) count')->group('status')->cursor() as $item) {
            $data["t{$item['status']}"] = $item['count'];
        }
        $this->success('获取订单统计', $data);
    }

    /**
     * 物流追踪查询
     * @return void
     */
    public function track()
    {
        try {
            $data = $this->_vali([
                'code.require'   => '快递不能为空',
                'number.require' => '单号不能为空'
            ]);
            $result = ExpressService::query($data['code'], $data['number']);
            empty($result['code']) ? $this->error($result['info']) : $this->success('快递追踪信息', $result);
        } catch (HttpResponseException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }
    }

    /**
     * 获取输入订单模型
     * @return PluginWemallOrder
     */
    private function getOrderModel(): PluginWemallOrder
    {
        $map = $this->_vali([
            'unid.value'       => $this->unid,
            'order_no.require' => '单号不能为空',
        ]);
        $order = PluginWemallOrder::mk()->where($map)->findOrEmpty();
        if ($order->isEmpty()) $this->error('读取订单失败');
        return $order;
    }
}