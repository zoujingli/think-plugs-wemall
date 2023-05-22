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

use plugin\payment\service\Payment;
use plugin\wemall\command\Clean;
use plugin\wemall\command\Transfer;
use plugin\wemall\command\Userinfo;
use plugin\wemall\model\PluginWemallOrder;
use plugin\wemall\model\PluginWemallUserRelation;
use plugin\wemall\service\UserRebateService;
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
    protected $appName = '多端微商城';

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
            if ($order->isExists() && $order->getAttr('status') <= 2) {
                if (Payment::isPayed($data['order_no'], $order->getAttr('amount_real'))) {
                    // 更新订单状态
                    $order->save([
                        'status'         => 4,
                        'payment_type'   => $data['payment_type'],
                        'payment_time'   => $data['payment_time'],
                        'payment_amount' => $data['payment_amount'],
                        'payment_status' => 1,
                    ]);
                    // 订单返利处理
                    UserRebateService::execute($data['order_no']);
                }
            }
        });

        // 注册订单确认事件
        $this->app->event->listen('PluginPaymentConfirm', function (array $data) {
            $this->app->log->notice("Event PluginPaymentConfirm {$data['order_no']}");
            // 更新返利记录
            UserRebateService::confirm($data['order_no']);
        });
    }

    /**
     * 定义插件菜单
     * @return array[]
     */
    public static function menu(): array
    {
        $code = app(static::class)->appCode;
        return [
            [
                'name' => '商城配置',
                'subs' => [
                    // ['name' => '数据统计报表', 'icon' => 'layui-icon layui-icon-theme', 'node' => "{$code}/total.portal/index"],
                    // ['name' => '轮播图片管理', 'icon' => 'layui-icon layui-icon-carousel', 'node' => "{$name}/base.slider/index"],
                    // ['name' => '页面内容管理', 'icon' => 'layui-icon layui-icon-read', 'node' => "{$name}/base.page/index"],
                    ['name' => '支付参数管理', 'icon' => 'layui-icon layui-icon-rmb', 'node' => "plugin-payment/config/index"],
                    ['name' => '手机短信管理', 'icon' => 'layui-icon layui-icon-email', 'node' => "plugin-account/message/index"],
                    // ['name' => '系统通知管理', 'icon' => 'layui-icon layui-icon-notice', 'node' => "{$code}/base.message/index"],
                    ['name' => '用户等级管理', 'icon' => 'layui-icon layui-icon-senior', 'node' => "{$code}/base.level/index"],
                    ['name' => '用户折扣方案', 'icon' => 'layui-icon layui-icon-set', 'node' => "{$code}/base.discount/index"],
                    ['name' => '店铺页面装修', 'icon' => 'layui-icon layui-icon-screen-restore', 'node' => "{$code}/base.design/index"],
                    // ['name' => '邀请二维码设置', 'icon' => 'layui-icon layui-icon-cols', 'node' => "{$name}/base.config/cropper"],
                    ['name' => '微信小程序配置', 'icon' => 'layui-icon layui-icon-login-wechat', 'node' => "{$code}/base.config/wxapp"],
                ],
            ],
            [
                'name' => '用户管理',
                'subs' => [
                    ['name' => '用户账号管理', 'icon' => 'layui-icon layui-icon-user', 'node' => "{$code}/user.admin/index"],
                    ['name' => '用户余额管理', 'icon' => 'layui-icon layui-icon-rmb', 'node' => "plugin-payment/balance/index"],
                    ['name' => '用户返利管理', 'icon' => 'layui-icon layui-icon-transfer', 'node' => "{$code}/user.rebate/index"],
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
        ];
    }
}