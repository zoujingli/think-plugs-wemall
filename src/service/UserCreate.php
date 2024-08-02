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

use plugin\account\model\PluginAccountUser;
use plugin\account\service\Account;
use plugin\wemall\model\PluginWemallUserCreate;
use plugin\wemall\model\PluginWemallUserRebate;
use plugin\wemall\model\PluginWemallUserRelation;
use plugin\wemall\model\PluginWemallUserTransfer;
use think\admin\Exception;
use think\admin\extend\CodeExtend;
use think\admin\Library;

/**
 * 用户账号管理
 * @class UserCreate
 * @package plugin\wemall\service
 */
abstract class UserCreate
{
    /**
     * 创建账号及返佣
     * @param int|string|PluginWemallUserCreate $user
     * @return void
     * @throws \think\admin\Exception
     */
    public static function create($user)
    {
        if (($user = self::withModel($user))->isExists()) try {
            $data = $user->hidden(['id', 'create_time', 'update_time'])->toArray();
            Library::$sapp->db->transaction(function () use ($user, $data) {
                // 检查代理权限
                if (!empty($data['agent_phone'])) {
                    $where = ['phone' => $data['agent_phone'], 'deleted' => 0];
                    $parent = PluginAccountUser::mk()->where($where)->findOrEmpty();
                    $relation = PluginWemallUserRelation::mk()->where(['unid' => $parent->getAttr('id')])->findOrEmpty();
                    if ($parent->isEmpty() || $relation->isEmpty()) throw new Exception('无效推荐人！');
                    if (empty($relation->getAttr('entry_agent'))) throw new Exception('上级无权限！');
                }
                // 检查并创建账号
                $inset = ['phone' => $data['phone'], 'headimg' => $data['headimg'], 'nickname' => $data['name'], 'deleted' => 0];
                ($account = Account::mk(Account::WAP, $inset))->isNull() && $account->set($inset);
                $account->isBind() || $account->bind($inset, $data) && $account->pwdModify($data['password']);
                // 绑定上级代理身份
                if (isset($parent)) UserUpgrade::bindAgent($account->getUnid(), intval($parent->getAttr('id')));
                // 创建返佣记录及提现记录
                $map = ['code' => $data['rebate_total_code'] ?: CodeExtend::uniqidDate(16, 'R'), 'unid' => $account->getUnid()];
                ($rebate = PluginWemallUserRebate::mk()->where($map)->findOrEmpty())->save([
                    'unid'         => $account->getUnid(),
                    'code'         => $map['code'],
                    'hash'         => md5($map['code']),
                    'date'         => date('Y-m-d'),
                    'type'         => 'platform',
                    'name'         => '初始化累计佣金',
                    'remark'       => $user->getAttr('rebate_total_desc'),
                    'amount'       => floatval($user->getAttr('rebate_total')),
                    'order_no'     => '',
                    'order_amount' => 0.00,
                    'confirm_time' => date('Y-m-d H:i:s'),
                ]);
                // 创建提现记录
                $map = ['code' => $user->getAttr('rebate_usable_code') ?: CodeExtend::uniqidDate(16, 'T'), 'unid' => $account->getUnid()];
                ($transfer = PluginWemallUserTransfer::mk()->where($map)->findOrEmpty())->save([
                    'unid'          => $account->getUnid(),
                    'type'          => 'platform',
                    'date'          => date('Y-m-d'),
                    'code'          => $map['code'],
                    'amount'        => floatval($user->getAttr('rebate_total')) - floatval($user->getAttr('rebate_usable')),
                    'status'        => 5,
                    'remark'        => $user->getAttr('rebate_usable_desc'),
                    'charge_rate'   => 0,
                    'charge_amount' => 0,
                    'change_time'   => date('Y-m-d H:i:s'),
                    'change_desc'   => '已经处理完成'
                ]);
                $user->save([
                    'unid'               => $account->getUnid(),
                    'rebate_total_code'  => $rebate->getAttr('code'),
                    'rebate_usable_code' => $transfer->getAttr('code')
                ]);
                // 更新代理身份及返佣记录
                UserOrder::entry($user->getAttr('unid'));
                UserRebate::recount($user->getAttr('unid'));
            });
        } catch (\Exception $exception) {
            trace_file($exception);
            throw new Exception($exception->getMessage());
        } else {
            throw new Exception('无效的用户记录！');
        }
    }

    /**
     * 取消账号及返佣
     * @param $user
     * @return void
     * @throws \think\admin\Exception
     */
    public static function cancel($user)
    {
        if (($user = self::withModel($user))->isExists()) try {
            Library::$sapp->db->transaction(function () use ($user) {
                // 取消返佣记录
                if (!empty($rCode = $user->getAttr('rebate_total_code'))) {
                    $map = ['code' => $rCode, 'unid' => $user->getAttr('unid')];
                    PluginWemallUserRebate::mk()->where($map)->delete();
                }
                // 创建提现记录
                if (!empty($tCode = $user->getAttr('rebate_usable_code'))) {
                    $map = ['code' => $tCode, 'unid' => $user->getAttr('unid')];
                    PluginWemallUserTransfer::mk()->where($map)->delete();
                }
                // 更新代理身份及返佣记录
                UserOrder::entry($user->getAttr('unid'));
                UserRebate::recount($user->getAttr('unid'));
            });
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage());
        } else {
            throw new Exception('无效的用户记录！');
        }
    }

    /**
     * 标准化模型
     * @param string|integer|PluginWemallUserCreate $model
     * @return \plugin\wemall\model\PluginWemallUserCreate
     * @throws \think\admin\Exception
     */
    public static function withModel($model): PluginWemallUserCreate
    {
        if (is_numeric($model)) {
            return PluginWemallUserCreate::mk()->findOrEmpty($model);
        } elseif ($model instanceof PluginWemallUserCreate) {
            return $model;
        } else {
            throw new Exception('无效参数类型！');
        }
    }
}