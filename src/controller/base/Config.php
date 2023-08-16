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

use think\admin\Controller;

/**
 * 应用参数配置
 * @class Config
 * @package plugin\wemall\controller\base
 */
class Config extends Controller
{

    /**
     * 微信小程序配置
     * @auth true
     * @menu true
     * @throws \think\admin\Exception
     */
//    public function wxapp()
//    {
//        $this->skey = 'wxapp';
//        $this->title = '微信小程序配置';
//        $this->__sysdata('wxapp');
//    }

    /**
     * 邀请二维码设置
     * @auth true
     * @menu true
     * @throws \think\admin\Exception
     */
    public function cropper()
    {
        $this->skey = 'plugin.wemall.cropper';
        $this->title = '邀请二维码设置';
        $this->__sysdata('cropper');
    }

    /**
     * 显示并保存数据
     * @param string $template 模板文件
     * @throws \think\admin\Exception
     */
    private function __sysdata(string $template)
    {
        if ($this->request->isGet()) {
            $this->data = sysdata($this->skey);
            $this->fetch($template);
        } elseif ($this->request->isPost()) {
            if (is_string(input('data'))) {
                $data = json_decode(input('data'), true) ?: [];
            } else {
                $data = $this->request->post();
            }
            if (sysdata($this->skey, $data) !== false) {
                $this->success('内容保存成功！');
            } else {
                $this->error('内容保存失败，请稍候再试!');
            }
        }
    }
}