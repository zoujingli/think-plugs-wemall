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

namespace plugin\wemall\controller\user\rebate;

use plugin\wemall\model\PluginWemallConfigAgent;
use plugin\wemall\model\PluginWemallConfigRebate;
use plugin\wemall\service\UserRebate;
use think\admin\Controller;
use think\admin\extend\CodeExtend;
use think\admin\helper\QueryHelper;

/**
 * 返佣规则配置
 * @class Config
 * @package plugin\wemall\controller\base
 */
class Config extends Controller
{
    /**
     * 返佣规则配置
     * @auth true
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index()
    {
        PluginWemallConfigRebate::mQuery()->layTable(function () {
            $this->title = '返佣规则配置';
            $this->prizes = UserRebate::prizes;
        }, function (QueryHelper $query) {
            $query->equal('type#mtype')->like('name')->dateBetween('create_time');
            $query->where(['deleted' => 0]);
        });
    }

    /**
     * 添加返佣规则
     * @auth true
     */
    public function add()
    {
        $this->title = '添加返佣规则';
        PluginWemallConfigRebate::mForm('form');
    }

    /**
     * 编辑返佣规则
     * @auth true
     */
    public function edit()
    {
        $this->title = '编辑返佣规则';
        PluginWemallConfigRebate::mForm('form');
    }

    /**
     * 表单数据处理
     * @return void
     */
    protected function _form_filter(array &$data)
    {
        if (empty($data['code'])) {
            $data['code'] = CodeExtend::uniqidNumber(16, 'R');
        }
        if ($this->request->isGet()) {
            $this->prizes = UserRebate::prizes;
            $this->levels = PluginWemallConfigAgent::items();
            array_unshift($this->levels, ['name' => '-> 无 <-', 'number' => -2], ['name' => '-> 任意 <-', 'number' => -1]);
        } else {
            $data['path'] = arr2str([$data['p3_level'], $data['p2_level'], $data['p1_level'], $data['p0_level']]);
        }
    }

    /**
     * 修改规则状态
     * @auth true
     */
    public function state()
    {
        PluginWemallConfigRebate::mSave($this->_vali([
            'status.in:0,1'  => '状态值范围异常！',
            'status.require' => '状态值不能为空！',
        ]));
    }

    /**
     * 删除返佣规则
     * @auth true
     */
    public function remove()
    {
        PluginWemallConfigRebate::mDelete();
    }
}