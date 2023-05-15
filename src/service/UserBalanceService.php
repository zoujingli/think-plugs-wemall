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

use plugin\payment\model\PluginPaymentBalance;
use plugin\payment\service\Balance;
use plugin\wemall\model\PluginWemallOrder;
use think\admin\Exception;
use think\admin\Service;

/**
 * 用户余额数据服务
 * @class UserBalanceService
 * @package plugin\wemall\service
 */
class UserBalanceService extends Service
{

    /**
     * 验证订单发放余额
     * @param string $orderNo
     * @return array [total, count]
     * @throws \think\admin\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function confirm(string $orderNo): array
    {
        $order = PluginWemallOrder::mk()->where([['status', '>=', 4], ['order_no', '=', $orderNo]])->find();
        if (empty($order)) throw new Exception('需处理的订单状态异常');

        if ($order['reward_balance'] > 0) {
            $code = "CZ{$order['order_no']}";
            Balance::create($order['unid'], $code, "购物返还余额", $order['reward_balance'], "来自订单 {$order['order_no']} 的返还余额 {$order['reward_balance']} 元");
            Balance::unlock($code);
        }

        return static::amount($order['unid']);
    }

    /**
     * 同步刷新用户余额
     * @param integer $unid 用户UID
     * @return array [total, count]
     * @throws \think\admin\Exception
     */
    public static function amount(int $unid): array
    {
        if ($unid > 0) {
            $data = Balance::recount($unid);
            return [$data['balance_total'], $data['balance_used']];
        } else {
            $total = abs(PluginPaymentBalance::mk()->whereRaw("amount > 0 and deleted = 0")->sum('amount'));
            $count = abs(PluginPaymentBalance::mk()->whereRaw("amount < 0 and deleted = 0")->sum('amount'));
            return [$total, $count];
        }
    }
}