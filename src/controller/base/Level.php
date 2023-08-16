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

namespace plugin\wemall\controller\base;

use plugin\wemall\model\PluginWemallConfigLevel;
use plugin\wemall\service\UserRebateService;
use think\admin\Controller;
use think\admin\helper\QueryHelper;

/**
 * 用户等级管理
 * @class Level
 * @package plugin\wemall\controller\base
 */
class Level extends Controller
{
    /**
     * 用户等级管理
     * @auth true
     * @menu true
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index()
    {
        PluginWemallConfigLevel::mQuery()->layTable(function () {
            $this->title = '用户等级管理';
        }, static function (QueryHelper $query) {
            $query->like('name')->equal('status')->dateBetween('create_at');
        });
    }

    /**
     * 添加用户等级
     * @auth true
     * @return void
     * @throws \think\db\exception\DbException
     */
    public function add()
    {
        $this->max = PluginWemallConfigLevel::maxNumber() + 1;
        PluginWemallConfigLevel::mForm('form');
    }

    /**
     * 编辑用户等级
     * @auth true
     * @return void
     * @throws \think\db\exception\DbException
     */
    public function edit()
    {
        $this->max = PluginWemallConfigLevel::maxNumber();
        PluginWemallConfigLevel::mForm('form');
    }

    /**
     * 表单数据处理
     * @param array $vo
     * @throws \think\db\exception\DbException
     */
    protected function _form_filter(array &$vo)
    {
        if ($this->request->isGet()) {
            $this->prizes = UserRebateService::prizes;
            $vo['number'] = $vo['number'] ?? PluginWemallConfigLevel::maxNumber();
        } else {
            $vo['utime'] = time();
            // 用户升级条件开关
            $vo['enter_vip_status'] = isset($vo['enter_vip_status']) ? 1 : 0;
            $vo['teams_users_status'] = isset($vo['teams_users_status']) ? 1 : 0;
            $vo['teams_direct_status'] = isset($vo['teams_direct_status']) ? 1 : 0;
            $vo['teams_indirect_status'] = isset($vo['teams_indirect_status']) ? 1 : 0;
            $vo['order_amount_status'] = isset($vo['order_amount_status']) ? 1 : 0;
            // 根据数量判断状态
            $vo['teams_users_status'] = intval($vo['teams_users_status'] && $vo['teams_users_number'] > 0);
            $vo['teams_direct_status'] = intval($vo['teams_direct_status'] && $vo['teams_direct_number'] > 0);
            $vo['teams_indirect_status'] = intval($vo['teams_indirect_status'] && $vo['teams_indirect_number'] > 0);
            $vo['order_amount_status'] = intval($vo['order_amount_status'] && $vo['order_amount_number'] > 0);
            // 检查升级条件配置
            $count = 0;
            foreach ($vo as $k => $v) if (is_numeric(stripos($k, '_status'))) $count += $v;
            if (empty($count) && $vo['number'] > 0) $this->error('升级条件不能为空！');
        }
    }

    /**
     * 表单结果处理
     * @param boolean $state
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function _form_result(bool $state)
    {
        if ($state) {
            $isasc = input('old_number', 0) <= input('number', 0);
            $order = $isasc ? 'number asc,utime asc' : 'number asc,utime desc';
            foreach (PluginWemallConfigLevel::mk()->order($order)->select() as $number => $upgrade) {
                $upgrade->save(['number' => $number]);
            }
        }
    }

    /**
     * 修改等级状态
     * @auth true
     */
    public function state()
    {
        PluginWemallConfigLevel::mSave();
    }

    /**
     * 删除用户等级
     * @auth true
     */
    public function remove()
    {
        PluginWemallConfigLevel::mDelete();
    }

    /**
     * 状态变更处理
     * @auth true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    protected function _save_result()
    {
        $this->_form_result(true);
    }

    /**
     * 删除结果处理
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    protected function _delete_result()
    {
        $this->_form_result(true);
    }
}