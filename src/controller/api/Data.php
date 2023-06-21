<?php

namespace plugin\wemall\controller\api;

use think\admin\Controller;
use think\admin\model\SystemBase;

class Data extends Controller
{
    /**
     * 获取指定数据
     * @throws \think\admin\Exception
     */
    public function get()
    {
        $data = $this->_vali([
            'name.require' => '数据名称不能为空！'
        ]);
        // 其他数据
        $extra = ['about', 'slider', 'agreement', 'cropper'];
        if (in_array($data['name'], $extra) || isset(SystemBase::items('页面内容')[$data['name']])) {
            $this->success('获取数据对象', sysdata($data['name']));
        } else {
            $this->error('获取数据失败', []);
        }
    }

    /**
     * 获取页面布局
     * @return void
     * @throws \think\admin\Exception
     */
    public function layout()
    {
        // 临时方案，后面会走模板记录
        $data = [
            'layout' => sysdata('plugin.wemall.design')
        ];
        $this->success('获取页面配置', $data);
    }

    /**
     * 图片内容数据
     * @throws \think\admin\Exception
     */
    public function slider()
    {
        $this->keys = input('keys', '首页图片');
        if (isset(SystemBase::items('图片内容')[$this->keys])) {
            $this->success('获取图片内容', sysdata($this->keys));
        } else {
            $this->error('获取图片失败', []);
        }
    }
}