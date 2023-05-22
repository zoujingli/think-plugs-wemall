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

use plugin\account\model\PluginAccountUser;
use plugin\wemall\model\PluginWemallConfigDiscount;
use plugin\wemall\model\PluginWemallConfigLevel;
use plugin\wemall\model\PluginWemallOrder;
use plugin\wemall\model\PluginWemallOrderItem;
use plugin\wemall\model\PluginWemallUserRebate;
use plugin\wemall\model\PluginWemallUserRelation;
use plugin\wemall\model\PluginWemallUserTransfer;
use think\admin\Exception;
use think\admin\extend\CodeExtend;
use think\admin\Library;
use think\admin\Service;

/**
 * 系统实时返利服务
 * @class UserRebateService
 * @package plugin\wemall\service
 */
class UserRebateService extends Service
{
    public const prize_01 = 'PRIZE01';
    public const prize_02 = 'PRIZE02';
    public const prize_03 = 'PRIZE03';
    public const prize_04 = 'PRIZE04';
    public const prize_05 = 'PRIZE05';
    public const prize_06 = 'PRIZE06';
    public const prize_07 = 'PRIZE07';
    public const prize_08 = 'PRIZE08';

    public const prizes = [
        self::prize_01 => '首推奖励',
        self::prize_02 => '复购奖励',
        self::prize_03 => '直属团队',
        self::prize_04 => '间接团队',
        self::prize_05 => '差额奖励',
        self::prize_06 => '管理奖励',
        self::prize_07 => '升级奖励',
        self::prize_08 => '平推返利',
    ];

    /**
     * 用户数据
     * @var array
     */
    protected static $user;

    /**
     * 推荐用户
     * @var array
     */
    protected static $from1;
    protected static $from2;

    /**
     * 订单数据
     * @var array
     */
    protected static $order;

    /**
     * 奖励到账时机
     * @var integer
     */
    protected static $status;

    /**
     * 执行订单返利处理
     * @param string $orderNo
     * @throws \think\admin\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function execute(string $orderNo)
    {
        // 返利奖励到账时机 ( 1 支付后到账，2 确认后到账 )
        self::$status = self::config('settl_type') > 1 ? 0 : 1;
        // 获取订单数据
        $map = ['order_no' => $orderNo, 'payment_status' => 1];
        self::$order = PluginWemallOrder::mk()->where($map)->findOrEmpty();
        if (self::$order->isEmpty()) throw new Exception('订单不存在');
        if (in_array(self::$order['payment_type'], ['empty', 'balance'])) return;
        if (self::$order['amount_total'] <= 0) throw new Exception('订单金额为零');
        if (self::$order['rebate_amount'] <= 0) throw new Exception('订单返利为零');
        // 获取用户数据
        self::$user = PluginWemallUserRelation::mk()->where(['unid' => self::$order['unid']])->findOrEmpty();
        if (self::$user->isEmpty()) throw new Exception('用户不存在');
        // 获取直接代理数据
        if (self::$order['puid1'] > 0) {
            self::$from1 = PluginWemallUserRelation::mk()->where(['unid' => self::$order['puid1']])->findOrEmpty();
            if (self::$from1->isEmpty()) throw new Exception('直接代理不存在');
        }
        // 获取间接代理数据
        if (self::$order['puid2'] > 0) {
            self::$from2 = PluginWemallUserRelation::mk()->where(['unid' => self::$order['puid2']])->findOrEmpty();
            if (self::$from2->isEmpty()) throw new Exception('间接代理不存在');
        }
        // 批量发放配置奖励
        foreach (self::prizes as $k => $v) if (method_exists(static::class, $k)) {
            Library::$sapp->log->notice("订单 {$orderNo} 开始发放 [{$k}] {$v}");
            self::{strtolower($k)}();
            Library::$sapp->log->notice("订单 {$orderNo} 完成发放 [{$k}] {$v}");
        }
    }

    /**
     * 确认收货订单处理
     * @param string $orderNo
     * @return array [status, message]
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function confirm(string $orderNo): array
    {
        $map = [['status', '>=', 4], ['order_no', '=', $orderNo]];
        $order = PluginWemallOrder::mk()->where($map)->findOrEmpty()->toArray();
        if (empty($order)) return [0, '需处理的订单状态异常！'];
        $map = [['status', '=', 0], ['order_no', 'like', "{$orderNo}%"]];
        PluginWemallUserRebate::mk()->where($map)->update(['status' => 1]);
        if (UserUpgradeService::upgrade($order['uuid'])) {
            return [1, '重新计算用户金额成功！'];
        } else {
            return [0, '重新计算用户金额失败！'];
        }
    }

    /**
     * 同步刷新用户返利
     * @param integer $unid
     * @return array [total, count, lock]
     */
    public static function amount(int $unid): array
    {
        if ($unid > 0) {
            $count = PluginWemallUserTransfer::mk()->whereRaw("unid='{$unid}' and status>0")->sum('amount');
            $total = PluginWemallUserRebate::mk()->whereRaw("unid='{$unid}' and status=1 and deleted=0")->sum('amount');
            $locks = PluginWemallUserRebate::mk()->whereRaw("unid='{$unid}' and status=0 and deleted=0")->sum('amount');
            if (($relation = PluginWemallUserRelation::mk()->where(['unid' => $unid])->findOrEmpty())->isExists()) {
                $relation->save(['extra' => array_merge($relation->getAttr('extra'), ['rebate_total' => $total, 'rebate_used' => $count, 'rebate_lock' => $locks])]);
            }
        } else {
            $count = PluginWemallUserTransfer::mk()->whereRaw("status>0")->sum('amount');
            $total = PluginWemallUserRebate::mk()->whereRaw("status=1 and deleted=0")->sum('amount');
            $locks = PluginWemallUserRebate::mk()->whereRaw("status=0 and deleted=0")->sum('amount');
        }
        return [$total, $count, $locks];
    }

    /**
     * 获取配置数据
     * @param ?string $name 配置名称
     * @return array|string
     * @throws \think\admin\Exception
     */
    public static function config(?string $name = null)
    {
        if (empty($data = sysvar('plugin.wemall.rebate.rule'))) {
            $data = sysvar('plugin.wemall.rebate.rule', sysdata('plugin.wemall.rebate.rule'));
        }
        return is_null($name) ? $data : ($data[$name] ?? '');
    }

    /**
     * 用户首推奖励
     * @return boolean
     * @throws \think\admin\Exception
     */
    private static function _prize01(): bool
    {
        if (empty(self::$from1)) return false;
        $map = ['order_unid' => self::$user['id']];
        if (PluginWemallUserRebate::mk()->where($map)->findOrEmpty()->isExists()) return false;
        // 创建返利奖励记录
        $key = sprintf('vip_%d_%d', self::$user['level_code'], self::$from1['level_code']);
        $map = ['type' => self::prize_01, 'order_no' => self::$order['order_no'], 'order_unid' => self::$order['unid']];
        if (self::config("frist_type_{$key}") > 0 && PluginWemallUserRebate::mk()->where($map)->findOrEmpty()->isEmpty()) {
            $value = self::config("frist_value_{$key}");
            if (self::config("frist_type_{$key}") == 1) {
                $val = floatval($value ?: '0.00');
                $name = sprintf('%s，每单 %s 元', self::prizes[self::prize_01], $val);
            } else {
                $val = floatval($value * self::$order['rebate_amount'] / 100);
                $name = sprintf('%s，订单 %s%%', self::prizes[self::prize_01], $value);
            }
            // 写入返利记录
            self::writeRabate(self::$from1['id'], $map, $name, $val);
        }
        return true;
    }


    /**
     * 用户复购奖励
     * @return boolean
     * @throws \think\admin\Exception
     * @throws \think\db\exception\DbException
     */
    protected static function _prize02(): bool
    {
        $map = [];
        $map[] = ['order_unid', '=', self::$user['id']];
        $map[] = ['order_no', '<>', self::$order['order_no']];
        if (PluginWemallUserRebate::mk()->where($map)->findOrEmpty()->isExists()) return false;
        // 检查上级可否奖励
        if (empty(self::$from1) || empty(self::$from1['level_code'])) return false;
        // 创建返利奖励记录
        $key = sprintf('vip_%d_%d', self::$from1['level_code'], self::$user['level_code']);
        $map = ['type' => self::prize_02, 'order_no' => self::$order['order_no'], 'order_unid' => self::$order['unid']];
        if (self::config("repeat_type_{$key}") > 0 && PluginWemallUserRebate::mk()->where($map)->findOrEmpty()->isEmpty()) {
            $value = self::config("repeat_value_{$key}");
            if (self::config("repeat_type_{$key}") == 1) {
                $val = floatval($value ?: '0.00');
                $name = sprintf("%s，每人 %s 元", self::prizes[self::prize_02], $val);
            } else {
                $val = floatval($value * self::$order['rebate_amount'] / 100);
                $name = sprintf("%s，订单 %s%%", self::prizes[self::prize_02], $value);
            }
            // 写入返利记录
            self::writeRabate(self::$from1['id'], $map, $name, $val);
        }
        return true;
    }

    /**
     * 用户直属团队
     * @return boolean
     * @throws \think\admin\Exception
     */
    private static function _prize03(): bool
    {
        if (empty(self::$from1)) return false;
        // 创建返利奖励记录
        $key = self::$user['level_code'];
        $map = ['type' => self::prize_03, 'order_no' => self::$order['order_no'], 'order_unid' => self::$order['unid']];
        if (self::config("direct_type_vip_{$key}") > 0 && PluginWemallUserRebate::mk()->where($map)->findOrEmpty()->isEmpty()) {
            $value = self::config("direct_value_vip_{$key}");
            if (self::config("direct_type_vip_{$key}") == 1) {
                $val = floatval($value ?: '0.00');
                $name = sprintf("%s，每人 %s 元", self::prizes[self::prize_03], $val);
            } else {
                $val = floatval($value * self::$order['rebate_amount'] / 100);
                $name = sprintf("%s，订单 %s%%", self::prizes[self::prize_03], $value);
            }
            // 写入返利记录
            self::writeRabate(self::$from1['id'], $map, $name, $val);
        }
        return true;
    }

    /**
     * 用户间接团队
     * @return boolean
     * @throws \think\admin\Exception
     */
    private static function _prize04(): bool
    {
        if (empty(self::$from2)) return false;
        $key = self::$user['level_code'];
        $map = ['type' => self::prize_04, 'order_no' => self::$order['order_no'], 'order_unid' => self::$order['unid']];
        if (self::config("indirect_type_vip_{$key}") > 0 && PluginWemallUserRebate::mk()->where($map)->findOrEmpty()->isEmpty()) {
            $value = self::config("indirect_value_vip_{$key}");
            if (self::config("indirect_type_vip_{$key}") == 1) {
                $val = floatval($value ?: '0.00');
                $name = sprintf('%s，每人 %s 元', self::prizes[self::prize_04], $val);
            } else {
                $val = floatval($value * self::$order['rebate_amount'] / 100);
                $name = sprintf("%s，订单 %s%%", self::prizes[self::prize_04], $value);
            }
            // 写入返利记录
            self::writeRabate(self::$from2['id'], $map, $name, $val);
        }
        return true;
    }

    /**
     * 用户差额奖励
     * @return false
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    private static function _prize05(): bool
    {
        $puids = array_reverse(str2arr(self::$user['path'], '-'));
        if (empty($puids) || self::$order['amount_total'] <= 0) return false;
        // 获取可以参与奖励的代理
        $vips = PluginWemallConfigLevel::mk()->whereLike('rebate_rule', '%,' . self::prize_05 . ',%')->column('number');
        $users = PluginAccountUser::mk()->whereIn('level_code', $vips)->whereIn('id', $puids)->orderField('id', $puids)->select()->toArray();
        if (empty($vips) || empty($users)) return true;
        // 查询需要计算奖励的商品
        foreach (PluginWemallOrderItem::mk()->where(['order_no' => self::$order['order_no']])->cursor() as $item) {
            if ($item['discount_id'] > 0 && $item['rebate_type'] === 1) {
                [$tVip, $tRate] = [$item['level_code'], $item['discount_rate']];
                $map = ['id' => $item['discount_id'], 'status' => 1, 'deleted' => 0];
                $rules = json_decode(PluginWemallConfigDiscount::mk()->where($map)->value('items', '[]'), true);
                foreach ($users as $user) if (isset($rules[$user['level_code']]) && $user['level_code'] > $tVip) {
                    if (($rule = $rules[$user['level_code']]) && $tRate > $rule['discount']) {
                        $map = ['unid' => $user['id'], 'type' => self::prize_05, 'order_no' => self::$order['order_no']];
                        if (PluginWemallUserRebate::mk()->where($map)->count() < 1) {
                            $vvvv = self::prizes[self::prize_05];
                            $dRate = ($rate = $tRate - $rule['discount']) / 100;
                            $name = "{$vvvv}{$tVip}#{$user['level_code']}商品原价{$item['total_selling']}元的{$rate}%";
                            $amount = $dRate * $item['total_selling'];
                            // 写入用户返利记录
                            self::writeRabate($user['id'], $map, $name, $amount);
                        }
                        [$tVip, $tRate] = [$user['level_code'], $rule['discount']];
                    }
                }
            }
        }
        return true;
    }

    /**
     * 用户管理奖励发放
     * @return boolean
     * @throws \think\admin\Exception
     * @throws \think\db\exception\DbException
     */
    private static function _prize06(): bool
    {
        $puids = array_reverse(str2arr(self::$user['path'], '-'));
        if (empty($puids) || self::$order['amount_total'] <= 0) return false;
        // 记录用户原始等级
        $prevLevel = self::$user['level_code'];
        // 获取参与奖励的代理
        $vips = PluginWemallConfigLevel::mk()->whereLike('rebate_rule', '%,' . self::prize_06 . ',%')->column('number');
        foreach (PluginAccountUser::mk()->whereIn('level_code', $vips)->whereIn('id', $puids)->orderField('id', $puids)->cursor() as $user) {
            if ($user['level_code'] > $prevLevel) {
                if (($amount = self::_prize06amount($prevLevel + 1, $user['level_code'])) > 0.00) {
                    $map = ['unid' => $user['id'], 'type' => self::prize_06, 'order_no' => self::$order['order_no']];
                    if (PluginWemallUserRebate::mk()->where($map)->count() < 1) {
                        $name = sprintf("%s，[ VIP%d > VIP%d ] 每单 %s 元", self::prizes[self::prize_06], $prevLevel, $user['level_code'], $amount);
                        self::writeRabate($user['id'], $map, $name, $amount);
                    }
                }
                $prevLevel = $user['level_code'];
            }
        }
        return true;
    }

    /**
     * 计算两等级之间的管理奖差异
     * @param integer $prevLevel 上个等级
     * @param integer $nextLevel 下个等级
     * @return float
     * @throws \think\admin\Exception
     */
    private static function _prize06amount(int $prevLevel, int $nextLevel): float
    {
        if (self::config("manage_type_vip_{$nextLevel}") == 2) {
            $amount = 0.00;
            foreach (range($prevLevel, $nextLevel) as $level) {
                $value = floatval(self::config("manage_value_vip_{$level}"));
                if (self::config("manage_type_vip_{$level}") > 0 && $value > 0) $amount += $value;
            }
            return $amount;
        } elseif (self::config("manage_type_vip_{$nextLevel}") == 1) {
            return floatval(self::config("manage_value_vip_{$nextLevel}"));
        } else {
            return floatval(0);
        }
    }

    /**
     * 用户升级奖励发放
     * @return boolean
     * @throws \think\admin\Exception
     */
    private static function _prize07(): bool
    {
        if (empty(self::$from1)) return false;
        $levelOrder = empty(self::$user['extra']['level_order']) ? '' : self::$user['extra']['level_order'];
        if (self::$order['order_no'] !== $levelOrder) return false;
        // 创建返利奖励记录
        $vip = self::$user['level_code'];
        $map = ['type' => self::prize_07, 'order_no' => self::$order['order_no'], 'order_unid' => self::$order['unid']];
        if (self::config("upgrade_type_vip_{$vip}") > 0 && PluginWemallUserRebate::mk()->where($map)->findOrEmpty()->isEmpty()) {
            $value = self::config("upgrade_value_vip_{$vip}");
            if (self::config("upgrade_type_vip_{$vip}") == 1) {
                $val = floatval($value ?: '0.00');
                $name = sprintf('%s，每人 %s 元', self::prizes[self::prize_07], $val);
            } else {
                $val = floatval($value * self::$order['rebate_amount'] / 100);
                $name = sprintf("%s，订单 %s%%", self::prizes[self::prize_07], $value);
            }
            // 写入返利记录
            self::writeRabate(self::$from1['id'], $map, $name, $val);
        }
        return true;
    }

    /**
     * 用户平推奖励发放
     * @return boolean
     */
    private static function _prize08(): bool
    {
        if (empty(self::$from1)) return false;
        $map = ['level_code' => self::$user['level_code']];
        $unids = array_reverse(str2arr(trim(self::$user['path'], '-'), '-'));
        $puids = PluginAccountUser::mk()->whereIn('id', $unids)->orderField('id', $unids)->where($map)->column('id');
        if (count($puids) < 2) return false;

        Library::$sapp->db->transaction(function () use ($map, $puids) {
            foreach ($puids as $key => $puid) {
                // 最多两层
                if (($layer = $key + 1) > 2) break;
                // 检查重复
                $map = ['unid' => $puid, 'type' => self::prize_08, 'order_no' => self::$order['order_no']];
                if (PluginWemallUserRebate::mk()->where($map)->count() < 1) {
                    // 返利比例
                    $rate = self::config("equal_value_vip_{$layer}_" . self::$user['level_code']);
                    // 返利金额
                    $money = floatval($rate * self::$order['rebate_amount'] / 100);
                    $name = sprintf("%s, 返回订单的 %s%%", self::prizes[self::prize_08], $rate);
                    // 写入返利
                    self::writeRabate($puid, $map, $name, $money);
                }
            }
        });
        return true;
    }

    /**
     * 获取奖励名称
     * @param string $prize
     * @return string
     */
    public static function name(string $prize): string
    {
        return self::prizes[$prize] ?? $prize;
    }

    /**
     * 写入返利记录
     * @param int $unid 奖励用户
     * @param array $map 查询条件
     * @param string $name 奖励名称
     * @param float $amount 奖励金额
     */
    private static function writeRabate(int $unid, array $map, string $name, float $amount)
    {
        PluginWemallUserRebate::mk()->insert(array_merge($map, [
            'unid'         => $unid,
            'date'         => date('Y-m-d'),
            'code'         => CodeExtend::uniqidDate(16, 'R'),
            'name'         => $name,
            'amount'       => $amount,
            'status'       => self::$status,
            'order_no'     => self::$order['order_no'],
            'order_unid'   => self::$order['unid'],
            'order_amount' => self::$order['amount_total'],
        ]));
        // 刷新用户返利统计
        UserRebateService::amount($unid);
    }
}