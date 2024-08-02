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

namespace plugin\wemall\controller\base;

use plugin\wemall\model\PluginWemallConfigLevel;
use think\admin\Controller;
use think\admin\helper\QueryHelper;

/**
 * 会员等级管理
 * @class Level
 * @package plugin\wemall\controller\base
 */
class Level extends Controller
{
    /**
     * 会员等级管理
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
            $this->title = '会员等级管理';
        }, static function (QueryHelper $query) {
            $query->like('name')->equal('status')->dateBetween('create_time');
        });
    }

    /**
     * 添加会员等级
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
     * 编辑会员等级
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
        if (empty($vo['extra'])) $vo['extra'] = [];
        if ($this->request->isGet()) {
            $vo['number'] = $vo['number'] ?? PluginWemallConfigLevel::maxNumber();
        } else {
            $vo['utime'] = time();
            if (empty($vo['number'])) {
                $vo['extra'] = ['enter_vip_status' => 0, 'order_amount_status' => 0, 'order_amount_number' => 0];
            } else {
                $count = $vo['extra']['enter_vip_status'] = empty($vo['extra']['enter_vip_status']) ? 0 : 1;
                if (empty($vo['extra']['order_amount_status']) || floatval($vo['extra']['order_amount_number']) <= 0) {
                    $vo['extra'] = array_merge($vo['extra'], ['order_amount_status' => 0, 'order_amount_number' => 0]);
                } else {
                    $count += 1;
                    $vo['extra']['order_amount_status'] = 1;
                }
                if (empty($count)) $this->error('升级条件不能为空！');
            }
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
     * 删除会员等级
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