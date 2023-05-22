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

namespace plugin\wemall\controller\user;

use plugin\account\model\PluginAccountUser;
use plugin\wemall\model\PluginWemallConfigLevel;
use plugin\wemall\model\PluginWemallOrderItem;
use plugin\wemall\model\PluginWemallUserRebate;
use plugin\wemall\service\UserRebateService;
use think\admin\Controller;
use think\admin\helper\QueryHelper;

/**
 * 用户返利管理
 * @class Rebate
 * @package plugin\wemall\controller\user
 */
class Rebate extends Controller
{
    /**
     * 用户返利管理
     * @auth true
     * @menu true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index()
    {
        PluginWemallUserRebate::mQuery()->layTable(function () {
            $this->title = '用户返利管理';
            $this->types = UserRebateService::prizes;
            $this->rebate = UserRebateService::amount(0);
        }, function (QueryHelper $query) {
            $query->equal('type')->like('name,order_no')->dateBetween('create_at');
            // 会员条件查询
            $db = PluginAccountUser::mQuery()->like('nickname#order_nickname,phone#order_phone')->db();
            if ($db->getOptions('where')) $query->whereRaw("order_unid in {$db->field('id')->buildSql()}");
            // 代理条件查询
            $db = PluginAccountUser::mQuery()->like('nickname#agent_nickname,phone#agent_phone')->db();
            if ($db->getOptions('where')) $query->whereRaw("unid in {$db->field('id')->buildSql()}");
        });
    }

    /**
     * 商城订单列表处理
     * @param array $data
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    protected function _index_page_filter(array &$data)
    {
        $uids = array_merge(array_column($data, 'unid'), array_column($data, 'order_unid'));
        $userItem = PluginAccountUser::mk()->whereIn('id', array_unique($uids))->select();
        $goodsItem = PluginWemallOrderItem::mk()->whereIn('order_no', array_unique(array_column($data, 'order_no')))->select();
        foreach ($data as &$vo) {
            $vo['type'] = UserRebateService::name($vo['type']);
            [$vo['user'], $vo['agent'], $vo['list']] = [[], [], []];
            foreach ($userItem as $user) {
                if ($user['id'] === $vo['unid']) $vo['agent'] = $user;
                if ($user['id'] === $vo['order_unid']) $vo['user'] = $user;
            }
            foreach ($goodsItem as $goods) {
                if ($goods['order_no'] === $vo['order_no']) {
                    $vo['list'][] = $goods;
                }
            }
        }
    }

    /**
     * 用户返利配置
     * @auth true
     * @throws \think\admin\Exception
     */
    public function config()
    {
        $this->skey = 'plugin.wemall.rebate.rule';
        $this->title = '用户返利配置';
        if ($this->request->isGet()) {
            $this->data = sysdata($this->skey);
            $this->levels = PluginWemallConfigLevel::items();
            $this->fetch();
        } else {
            sysdata($this->skey, $this->request->post());
            $this->success('奖励修改成功');
        }
    }
}