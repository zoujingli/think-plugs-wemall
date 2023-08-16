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
use plugin\wemall\model\PluginWemallUserRelation;
use think\admin\Controller;
use think\admin\helper\QueryHelper;

/**
 * 用户关系管理
 * @class Admin
 * @package plugin\wemall\controller\user
 */
class Admin extends Controller
{
    /**
     * 用户关系管理
     * @auth true
     * @menu true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index()
    {
        $this->type = $this->get['type'] ?? 'index';
        PluginWemallUserRelation::mQuery()->layTable(function () {
            $this->title = '用户关系管理';
            $this->upgrades = PluginWemallConfigLevel::items();
        }, function (QueryHelper $query) {
            $query->with(['user'])->equal('level_code');
            // 用户内容查询
            $uq = PluginAccountUser::mQuery()->equal('status')->dateBetween('create_at');
            $uq->where(['status' => intval($this->type === 'index'), 'deleted' => 0]);
            $uq->like('code,phone,email|username|nickname#username');
            $query->whereRaw("unid in {$uq->db()->field('id')->buildSql()}");
        });
    }

    /**
     * 修改用户状态
     * @auth true
     */
    public function state()
    {
        PluginAccountUser::mSave($this->_vali([
            'status.in:0,1'  => '状态值范围异常！',
            'status.require' => '状态值不能为空！',
        ]));
    }

    /**
     * 删除用户账号
     * @auth true
     */
    public function remove()
    {
        PluginAccountUser::mDelete();
    }
}