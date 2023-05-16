<?php

namespace plugin\wemall\controller\base;

use plugin\wemall\model\PluginWemallBasePage;
use think\admin\Controller;
use think\admin\extend\CodeExtend;
use think\admin\helper\QueryHelper;

class Page extends Controller
{
    /**
     * 文章内容管理
     * @auth true
     * @menu true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index()
    {
        $this->type = $this->get['type'] ?? 'index';
        PluginWemallBasePage::mQuery()->layTable(function () {
            $this->title = '文章内容管理';
        }, function (QueryHelper $query) {
            $query->like('code,name')->like('mark', ',')->dateBetween('create_at');
            $query->where(['status' => intval($this->type === 'index'), 'deleted' => 0]);
        });
    }

    /**
     * 添加文章内容
     * @auth true
     */
    public function add()
    {
        $this->title = '添加文章内容';
        PluginWemallBasePage::mForm('form');
    }

    /**
     * 编辑文章内容
     * @auth true
     */
    public function edit()
    {
        $this->title = '编辑文章内容';
        PluginWemallBasePage::mForm('form');
    }

    /**
     * 表单数据处理
     * @param array $data
     */
    protected function _form_filter(array &$data)
    {
        if (empty($data['code'])) {
            $data['code'] = CodeExtend::uniqidNumber(16, 'A');
        }
    }

    /**
     * 表单结果处理
     * @param boolean $state
     */
    protected function _form_result(bool $state)
    {
        if ($state) {
            $this->success('文章保存成功！', 'javascript:history.back()');
        }
    }

    /**
     * 修改文章状态
     * @auth true
     */
    public function state()
    {
        PluginWemallBasePage::mSave($this->_vali([
            'status.in:0,1'  => '状态值范围异常！',
            'status.require' => '状态值不能为空！',
        ]));
    }

    /**
     * 删除文章内容
     * @auth true
     */
    public function remove()
    {
        PluginWemallBasePage::mDelete();
    }

    /**
     * 文章内容选择
     * @login true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function select()
    {
        $this->get['status'] = 1;
        $this->index();
    }
}