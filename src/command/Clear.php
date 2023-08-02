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

namespace plugin\wemall\command;

use plugin\wemall\model\PluginWemallOrder;
use plugin\wemall\model\PluginWemallOrderItem;
use plugin\wemall\service\OrderService;
use think\admin\Command;
use think\console\Input;
use think\console\Output;

/**
 * 商城订单自动清理
 * @class Clear
 * @package app\data\command
 */
class Clear extends Command
{
    protected function configure()
    {
        $this->setName('xdata:mall:clear');
        $this->setDescription('清理商城订单数据');
    }

    /**
     * 业务指令执行
     * @param Input $input
     * @param Output $output
     * @return void
     * @throws \think\admin\Exception
     */
    protected function execute(Input $input, Output $output)
    {
        $this->_autoCancelOrder();
        $this->_autoRemoveOrder();
    }

    /**
     * 取消30分钟未支付订单
     * @return void
     * @throws \think\admin\Exception
     */
    private function _autoCancelOrder()
    {
        try {
            $where = [['status', '<', 3], ['create_time', '<', date('Y-m-d H:i:s', strtotime('-30 minutes'))]];
            [$count, $total] = [0, ($result = PluginWemallOrder::mk()->where($where)->select())->count()];
            $result->map(function (PluginWemallOrder $order) use ($total, &$count) {
                if ($order->payment()->findOrEmpty()->isExists()) {
                    $this->queue->message($total, ++$count, "订单 {$order->getAttr('order_no')} 存在支付记录");
                } else {
                    $this->queue->message($total, ++$count, "开始取消未支付的订单 {$order->getAttr('order_no')}");
                    $order->save(['status' => 0, 'cancel_status' => 1, 'cancel_time' => date('Y-m-d H:i:s'), 'cancel_remark' => '自动取消30分钟未完成支付']);
                    OrderService::stock($order->getAttr('order_no'));
                    $this->queue->message($total, $count, "完成取消未支付的订单 {$order->getAttr('order_no')}", 1);
                }
            });
        } catch (\Exception $exception) {
            $this->setQueueError($exception->getMessage());
        }
    }

    /**
     * 清理已取消的订单
     * @return void
     * @throws \think\admin\Exception
     */
    private function _autoRemoveOrder()
    {
        try {
            $where = [['status', '=', 0], ['create_time', '<', date('Y-m-d H:i:s', strtotime('-3 days'))]];
            [$count, $total] = [0, ($result = PluginWemallOrder::mk()->where($where)->select())->count()];
            $result->map(function (PluginWemallOrder $order) use ($total, &$count) {
                if ($order->payment()->findOrEmpty()->isExists()) {
                    $this->queue->message($total, ++$count, "订单 {$order->getAttr('order_no')} 存在支付记录");
                } else {
                    $this->queue->message($total, ++$count, "开始清理已取消的订单 {$order->getAttr('order_no')}");
                    PluginWemallOrder::mk()->where(['order_no' => $order->getAttr('order_no')])->delete();
                    PluginWemallOrderItem::mk()->where(['order_no' => $order->getAttr('order_no')])->delete();
                    $this->queue->message($total, $count, "完成清理已取消的订单 {$order->getAttr('order_no')}", 1);
                }
            });
        } catch (\Exception $exception) {
            $this->setQueueError($exception->getMessage());
        }
    }
}