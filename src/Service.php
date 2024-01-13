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

namespace plugin\wemall;

use plugin\payment\model\PluginPaymentRecord;
use plugin\payment\Service as PaymentService;
use plugin\wemall\command\Clear;
use plugin\wemall\command\Trans;
use plugin\wemall\command\Users;
use plugin\wemall\model\PluginWemallOrder;
use plugin\wemall\model\PluginWemallUserRelation;
use plugin\wemall\service\UserOrder;
use plugin\wemall\service\UserRebate;
use think\admin\Plugin;

/**
 * 插件服务注册
 * @class Service
 * @package plugin\wemall
 */
class Service extends Plugin
{
    /**
     * 定义插件名称
     * @var string
     */
    protected $appName = '分销商城';

    /**
     * 定义安装包名
     * @var string
     */
    protected $package = 'zoujingli/think-plugs-wemall';

    /**
     * 插件服务注册
     * @return void
     */
    public function register(): void
    {
        $this->commands([Clear::class, Trans::class, Users::class]);

        // 注册用户绑定事件
        $this->app->event->listen('PluginAccountBind', function (array $data) {
            $this->app->log->notice("Event PluginAccountBind {$data['unid']}#{$data['usid']}");
            PluginWemallUserRelation::sync(intval($data['unid']));
        });

        // 注册支付审核事件
        $this->app->event->listen('PluginPaymentAudit', function (PluginPaymentRecord $payment) {
            $this->app->log->notice("Event PluginPaymentAudit {$payment->getAttr('order_no')}");
            $order = PluginWemallOrder::mk()->where(['order_no' => $payment->getAttr('order_no')])->findOrEmpty();
            $order->isExists() && UserOrder::payment($order, $payment);
        });

        // 注册支付拒审事件
        $this->app->event->listen('PluginPaymentRefuse', function (PluginPaymentRecord $payment) {
            $this->app->log->notice("Event PluginPaymentRefuse {$payment->getAttr('order_no')}");
            $order = PluginWemallOrder::mk()->where(['order_no' => $payment->getAttr('order_no')])->findOrEmpty();
            $order->isExists() && UserOrder::payment($order, $payment);
        });

        // 注册支付完成事件
        $this->app->event->listen('PluginPaymentSuccess', function (PluginPaymentRecord $payment) {
            $this->app->log->notice("Event PluginPaymentSuccess {$payment->getAttr('order_no')}");
            $order = PluginWemallOrder::mk()->where(['order_no' => $payment->getAttr('order_no')])->findOrEmpty();
            $order->isExists() && UserOrder::payment($order, $payment);
        });

        // 注册支付取消事件
        $this->app->event->listen('PluginPaymentCancel', function (PluginPaymentRecord $payment) {
            $this->app->log->notice("Event PluginPaymentCancel {$payment->getAttr('order_no')}");
            $order = PluginWemallOrder::mk()->where(['order_no' => $payment->getAttr('order_no')])->findOrEmpty();
            $order->isExists() && UserOrder::payment($order, $payment);
        });

        // 注册订单确认事件
        $this->app->event->listen('PluginPaymentConfirm', function (array $data) {
            $this->app->log->notice("Event PluginPaymentConfirm {$data['order_no']}");
            UserRebate::confirm($data['order_no']);
        });
    }

    /**
     * 定义插件菜单
     * @return array[]
     */
    public static function menu(): array
    {
        $code = app(static::class)->appCode;
        return array_merge([
            [
                'name' => '商城配置',
                'subs' => [
                    ['name' => '数据统计报表', 'icon' => 'layui-icon layui-icon-theme', 'node' => "{$code}/base.report/index"],
                    ['name' => '推广海报管理', 'icon' => 'layui-icon layui-icon-carousel', 'node' => "{$code}/base.poster/index"],
                    ['name' => '系统通知管理', 'icon' => 'layui-icon layui-icon-notice', 'node' => "{$code}/base.notify/index"],
                    ['name' => '商城参数管理', 'icon' => 'layui-icon layui-icon-set', 'node' => "{$code}/base.config/index"],
                    ['name' => '用户等级管理', 'icon' => 'layui-icon layui-icon-senior', 'node' => "{$code}/base.level/index"],
                    ['name' => '用户折扣方案', 'icon' => 'layui-icon layui-icon-engine', 'node' => "{$code}/base.discount/index"],
                    ['name' => '店铺页面装修', 'icon' => 'layui-icon layui-icon-code-circle', 'node' => "{$code}/base.design/index"],
                ],
            ],
            [
                'name' => '用户管理',
                'subs' => [
                    ['name' => '用户关系管理', 'icon' => 'layui-icon layui-icon-user', 'node' => "{$code}/user.admin/index"],
                    // ['name' => '用户余额管理', 'icon' => 'layui-icon layui-icon-rmb', 'node' => "plugin-payment/balance/index"],
                    // ['name' => '用户积分管理', 'icon' => 'layui-icon layui-icon-diamond', 'node' => "plugin-payment/integral/index"],
                    ['name' => '用户返佣管理', 'icon' => 'layui-icon layui-icon-transfer', 'node' => "{$code}/user.rebate/index"],
                    ['name' => '用户提现管理', 'icon' => 'layui-icon layui-icon-component', 'node' => "{$code}/user.transfer/index"],
                ],
            ],
            [
                'name' => '商城管理',
                'subs' => [
                    ['name' => '商品数据管理', 'icon' => 'layui-icon layui-icon-star', 'node' => "{$code}/shop.goods/index"],
                    ['name' => '订单数据管理', 'icon' => 'layui-icon layui-icon-template', 'node' => "{$code}/shop.order/index"],
                    ['name' => '订单发货管理', 'icon' => 'layui-icon layui-icon-transfer', 'node' => "{$code}/shop.send/index"],
                    ['name' => '快递公司管理', 'icon' => 'layui-icon layui-icon-website', 'node' => "{$code}/base.express.company/index"],
                    ['name' => '邮费模板管理', 'icon' => 'layui-icon layui-icon-template-1', 'node' => "{$code}/base.express.template/index"],
                ],
            ],
        ], PaymentService::menu());
    }
}