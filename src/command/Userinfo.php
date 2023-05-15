<?php

namespace plugin\wemall\command;

use plugin\account\model\PluginAccountUser;
use plugin\wemall\model\PluginWemallUserRelation;
use plugin\wemall\service\UserBalanceService;
use plugin\wemall\service\UserRebateService;
use plugin\wemall\service\UserUpgradeService;
use think\admin\Command;
use think\console\Input;
use think\console\Output;

class Userinfo extends Command
{

    public function configure()
    {
        $this->setName('xdata:WemallUserinfo')->setDescription('同步用户关联数据');
    }

    /**
     * 执行指令
     * @param \think\console\Input $input
     * @param \think\console\Output $output
     * @throws \think\admin\Exception
     * @throws \think\db\exception\DbException
     */
    protected function execute(Input $input, Output $output)
    {
        [$total, $count] = [PluginAccountUser::mk()->count(), 0];
        foreach (PluginAccountUser::mk()->field('id')->cursor() as $user) try {
            $this->queue->message($total, ++$count, "刷新用户 [{$user['id']}] 数据...");
            PluginWemallUserRelation::sync($user['id']);
            UserUpgradeService::upgrade($user['id']);
            UserRebateService::amount($user['id']);
            UserBalanceService::amount($user['id']);
            $this->queue->message($total, $count, "刷新用户 [{$user['id']}] 数据成功", 1);
        } catch (\Exception $exception) {
            $this->queue->message($total, $count, "刷新用户 [{$user['id']}] 数据失败, {$exception->getMessage()}", 1);
        }
        $this->setQueueSuccess("此次共处理 {$total} 个刷新操作。");
    }
}