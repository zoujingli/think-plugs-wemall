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

use plugin\wemall\model\PluginWemallConfigLevel;
use plugin\wemall\model\PluginWemallOrder;
use plugin\wemall\model\PluginWemallOrderItem;
use plugin\wemall\model\PluginWemallUserRelation;
use think\admin\Exception;
use think\admin\Library;
use think\admin\Service;

/**
 * 用户等级升级服务
 * @class UserUpgradeService
 * @package plugin\wemall\service
 */
class UserUpgradeService extends Service
{

    /**
     * 读取用户代理编号
     * @param integer $unid
     * @param integer $agent
     * @param array|null $relation
     * @return array
     * @throws \think\admin\Exception
     */
    public static function withAgent(int $unid, int $agent, ?array $relation = null): array
    {
        if (empty($relation)) {
            $relation = PluginWemallUserRelation::mk()->where(['unid' => $unid])->findOrEmpty()->toArray();
            if ($relation) throw new Exception("无效的关联信息");
        }
        // 绑定代理数据
        $puid0 = $relation['puid0'] ?? 0; // 临时绑定
        $puid1 = $relation['puid1'] ?? 0; // 上1级代理
        $puid2 = $relation['puid2'] ?? 0; // 上2级代理
        if (empty($agent) && empty($puid1) && $puid0 > 0) {
            $puid1 = $puid0;
            $puid2 = intval(PluginWemallUserRelation::mk()->findOrEmpty($puid0)->getAttr('puid1'));
        } elseif ($agent > 0 && empty($puid1)) {
            $puid1 = $agent;
            $puid2 = self::bindAgent($unid, $puid1, 0)['puid1'] ?? 0;
        }
        return ['unid' => $unid, 'puid1' => $puid1, 'puid2' => $puid2];
    }

    /**
     * 尝试绑定上级代理
     * @param integer $unid 用户 UNID
     * @param integer $puid 代理 UNID
     * @param integer $mode 操作类型（0临时绑定, 1永久绑定, 2强行绑定）
     * @return array
     * @throws \think\admin\Exception
     */
    public static function bindAgent(int $unid, int $puid = 0, int $mode = 1): array
    {
        try {
            $user = PluginWemallUserRelation::mk()->where(['unid' => $unid])->findOrEmpty();
            if ($user->isEmpty()) throw new Exception('查询用户失败');
            if ($user->getAttr('puid1') && $mode !== 2) throw new Exception('已经绑定代理');
            // 检查代理用户
            if (empty($puid)) $puid = $user->getAttr('puid0');
            if (empty($puid)) throw new Exception('代理不存在');
            if ($unid == $puid) throw new Exception('不能绑定自己');
            // 检查代理资格
            $agent = PluginWemallUserRelation::mk()->where(['unid' => $puid])->findOrEmpty();
            if ($agent->isEmpty()) throw new Exception('代理无推荐资格');
            if (strpos($agent->getAttr('path'), ",{$unid},") !== false) throw new Exception('不能绑定下级');
            Library::$sapp->db->transaction(function () use ($user, $agent, $mode) {
                // 更新用户代理
                $path1 = rtrim($agent['path'] ?: ',', ',') . ",{$agent['id']},";
                $user->save(['puid0' => $agent['id'], 'puid1' => $agent['id'], 'puid2' => $agent['puid1'], 'pids' => $mode > 0 ? 1 : 0, 'path' => $path1, 'layer' => substr_count($path1, ',')]);
                // 更新下级代理
                $path2 = ",{$user['path']}{$user['id']},";
                if (PluginWemallUserRelation::mk()->whereLike('path', "{$path2}%")->count() > 0) {
                    foreach (PluginWemallUserRelation::mk()->whereLike('path', "{$path2}%")->order('layer desc')->select() as $item) {
                        $attr = array_reverse(str2arr($path3 = preg_replace("#^{$path2}#", "{$path1}{$user['id']},", $item['path'])));
                        $item->save(['puid0' => $attr[0] ?? 0, 'puid1' => $attr[0] ?? 0, 'puid2' => $attr[1] ?? 0, 'path' => $path3, 'layer' => substr_count($path3, '-')]);
                    }
                }
            });
            static::upgrade($user['id']);
            return $agent->toArray();
        } catch (Exception $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new Exception("绑定代理失败, {$exception->getMessage()}");
        }
    }

    /**
     * 同步计算用户等级
     * @param integer $unid 指定用户UID
     * @param boolean $parent 同步计算上级
     * @param ?string $orderNo 升级触发订单
     * @return boolean
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function upgrade(int $unid, bool $parent = true, ?string $orderNo = null): bool
    {
        $relation = PluginWemallUserRelation::mk()->where(['unid' => $unid])->findOrEmpty();
        if ($relation->isEmpty()) return true;
        // 初始化等级参数
        $levels = PluginWemallConfigLevel::mk()->where(['status' => 1])->select()->toArray();
        [$levelName, $levelCode, $levelTeams] = [$levels[0]['name'] ?? '普通用户', 0, []];
        // 统计用户数据
        foreach ($levels as $level => $vo) if ($vo['upgrade_team'] === 1) $levelTeams[] = $level;
        $orderAmount = PluginWemallOrder::mk()->where("unid={$unid} and status>=4")->sum('amount_total');
        $teamsDirect = PluginWemallUserRelation::mk()->where(['puid1' => $unid])->whereIn('level_code', $levelTeams)->count();
        $teamsIndirect = PluginWemallUserRelation::mk()->where(['puid2' => $unid])->whereIn('level_code', $levelTeams)->count();
        $teamsUsers = $teamsDirect + $teamsIndirect;
        // 动态计算用户等级
        foreach (array_reverse($levels) as $item) {
            $l1 = empty($item['enter_vip_status']) || $relation['buy_vip_entry'] > 0;
            $l2 = empty($item['teams_users_status']) || $item['teams_users_number'] <= $teamsUsers;
            $l3 = empty($item['order_amount_status']) || $item['order_amount_number'] <= $orderAmount;
            $l4 = empty($item['teams_direct_status']) || $item['teams_direct_number'] <= $teamsDirect;
            $l5 = empty($item['teams_indirect_status']) || $item['teams_indirect_number'] <= $teamsIndirect;
            if (
                ($item['upgrade_type'] == 0 && ($l1 || $l2 || $l3 || $l4 || $l5)) /* 满足任何条件 */
                ||
                ($item['upgrade_type'] == 1 && ($l1 && $l2 && $l3 && $l4 && $l5)) /* 满足所有条件 */
            ) {
                [$levelName, $levelCode] = [$item['name'], $item['number']];
                break;
            }
        }
        // 购买入会商品升级
        $query = PluginWemallOrderItem::mk()->alias('b')->join([PluginWemallOrder::mk()->getTable() => 'a'], 'b.order_no=a.order_no');
        $tmpCode = $query->whereRaw("a.unid={$unid} and a.payment_status=1 and a.status>=4 and b.level_upgrade>-1")->max('b.level_upgrade');
        if ($tmpCode > $levelCode && isset($levels[$tmpCode])) {
            [$levelName, $levelCode] = [$levels[$tmpCode]['name'], $levels[$tmpCode]['number']];
        } else {
            $orderNo = null;
        }
        // 后台余额充值升级
//        $tmpCode = DataUserBalance::mk()->where(['unid' => $unid, 'deleted' => 0])->max('upgrade');
//        if ($tmpCode > $levelCode && isset($levels[$tmpCode])) {
//            [$levelName, $levelCode] = [$levels[$tmpCode]['name'], $levels[$tmpCode]['number']];
//        }
        // 统计用户订单金额
        $orderAmountTotal = PluginWemallOrder::mk()->whereRaw("unid={$unid} and status>=4")->sum('amount_goods');
        $teamsAmountDirect = PluginWemallOrder::mk()->whereRaw("puid1={$unid} and status>=4")->sum('amount_goods');
        $teamsAmountIndirect = PluginWemallOrder::mk()->whereRaw("puid2={$unid} and status>=4")->sum('amount_goods');
        // 更新用户团队数据
        $data = [
            'level_name' => $levelName,
            'level_code' => $levelCode,
            'extra'      => [
                'teams_users_total'     => $teamsUsers,
                'teams_users_direct'    => $teamsDirect,
                'teams_users_indirect'  => $teamsIndirect,
                'teams_amount_total'    => $teamsAmountDirect + $teamsAmountIndirect,
                'teams_amount_direct'   => $teamsAmountDirect,
                'teams_amount_indirect' => $teamsAmountIndirect,
                'order_amount_total'    => $orderAmountTotal,
            ]
        ];
        if (!empty($orderNo)) $data['extra']['level_order'] = $orderNo;
        if ($data['level_code'] !== $relation['level_code']) $data['extra']['level_time'] = date('Y-m-d H:i:s');
        if ($relation->save($data) && $relation['level_code'] < $levelCode) Library::$sapp->event->trigger('PluginWemallUpgradeLevel', [
            'unid' => $relation['unid'], 'order_no' => $orderNo, 'level_code_old' => $relation['level_code'], 'level_code_new' => $levelCode,
        ]);
        return !($parent && $relation['puid1'] > 0) || static::upgrade($relation['puid1'], false);
    }
}