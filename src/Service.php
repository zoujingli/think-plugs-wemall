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

namespace plugin\wemall;

use plugin\wemall\command\Clean;
use plugin\wemall\command\Transfer;
use plugin\wemall\command\Userinfo;
use plugin\wemall\model\PluginWemallOrder;
use plugin\wemall\model\PluginWemallUserRelation;
use plugin\wemall\service\UserRebateService;
use think\admin\Plugin;

class Service extends Plugin
{
    protected $package = 'zoujingli/think-plugs-wemall';

    public function register(): void
    {
        $this->commands([Userinfo::class, Clean::class, Transfer::class]);

        // 注册用户绑定事件
        $this->app->event->listen('PluginAccountBind', function (array $data) {
            $this->app->log->notice("Event PluginAccountBind {$data['unid']}#{$data['usid']}");
            PluginWemallUserRelation::sync(intval($data['unid']));
        });

        // 注册支付完成事件
        $this->app->event->listen('PluginPaymentSuccess', function (array $data) {
            $this->app->log->notice("Event PluginPaymentSuccess {$data['order_no']}");

            // 更新支付状态
            $order = PluginWemallOrder::mk()->where(['order_no' => $data['order_no']])->findOrEmpty();
            if ($order->isExists() && $order['status'] <= 2) $order->save([
                'status' => 4, 'payment_status' => 1, 'payment_type' => $data['payment_type'], 'payment_time' => $data['payment_time']
            ]);

            // 订单返利处理
            UserRebateService::execute($data['order_no']);
        });

        // 注册订单确认事件
        $this->app->event->listen('PluginPaymentConfirm', function (array $data) {
            $this->app->log->notice("Event PluginPaymentConfirm {$data['order_no']}");
            // 更新返利记录
            UserRebateService::confirm($data['order_no']);
        });
    }

    public static function menu(): array
    {
        $name = app(static::class)->appName;
        return [
            [
                'name' => '数据管理',
                'subs' => [
                    // ['name' => '数据统计报表', 'icon' => 'layui-icon layui-icon-theme', 'node' => "{$code}/total.portal/index"],
                    // ['name' => '轮播图片管理', 'icon' => 'layui-icon layui-icon-carousel', 'node' => "{$name}/base.slider/index"],
                    ['name' => '页面内容管理', 'icon' => 'layui-icon layui-icon-read', 'node' => "{$name}/base.page/index"],
                    ['name' => '支付参数管理', 'icon' => 'layui-icon layui-icon-rmb', 'node' => "plugin-payment/config/index"],
                    ['name' => '手机短信管理', 'icon' => 'layui-icon layui-icon-email', 'node' => "plugin-account/message/index"],
                    // ['name' => '系统通知管理', 'icon' => 'layui-icon layui-icon-notice', 'node' => "{$code}/base.message/index"],
                    ['name' => '用户等级管理', 'icon' => 'layui-icon layui-icon-senior', 'node' => "{$name}/base.upgrade/index"],
                    ['name' => '用户折扣方案', 'icon' => 'layui-icon layui-icon-set', 'node' => "{$name}/base.discount/index"],
                    ['name' => '店铺页面装修', 'icon' => 'layui-icon layui-icon-screen-restore', 'node' => "{$name}/base.design/index"],
                    ['name' => '邀请二维码设置', 'icon' => 'layui-icon layui-icon-cols', 'node' => "{$name}/base.config/cropper"],
                    ['name' => '微信小程序配置', 'icon' => 'layui-icon layui-icon-login-wechat', 'node' => "{$name}/base.config/wxapp"],
                ],
            ],
            [
                'name' => '用户管理',
                'subs' => [
                    ['name' => '用户账号管理', 'icon' => 'layui-icon layui-icon-user', 'node' => "{$name}/user.admin/index"],
                    ['name' => '用户余额管理', 'icon' => 'layui-icon layui-icon-rmb', 'node' => "plugin-payment/balance/index"],
                    ['name' => '用户返利管理', 'icon' => 'layui-icon layui-icon-transfer', 'node' => "{$name}/user.rebate/index"],
                    ['name' => '用户提现管理', 'icon' => 'layui-icon layui-icon-component', 'node' => "{$name}/user.transfer/index"],
                ],
            ],
            [
                'name' => '商城管理',
                'subs' => [
                    ['name' => '商品数据管理', 'icon' => 'layui-icon layui-icon-star', 'node' => "{$name}/shop.goods/index"],
                    ['name' => '订单数据管理', 'icon' => 'layui-icon layui-icon-template', 'node' => "{$name}/shop.order/index"],
                    ['name' => '订单发货管理', 'icon' => 'layui-icon layui-icon-transfer', 'node' => "{$name}/shop.send/index"],
                    ['name' => '快递公司管理', 'icon' => 'layui-icon layui-icon-website', 'node' => "{$name}/base.express.company/index"],
                    ['name' => '邮费模板管理', 'icon' => 'layui-icon layui-icon-template-1', 'node' => "{$name}/base.express.template/index"],
                ],
            ],
        ];
    }
}