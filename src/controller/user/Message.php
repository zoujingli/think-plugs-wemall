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

use plugin\wemall\model\PluginWemallUserMessage;
use plugin\wemall\service\MessageService;
use think\admin\Controller;

/**
 * 短信发送管理
 * @class Message
 * @package plugin\wemall\controller\user
 */
class Message extends Controller
{
    /**
     * 短信发送管理
     * @auth true
     * @menu true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index()
    {
        $this->title = '短信发送管理';
        $query = PluginWemallUserMessage::mQuery();
        $query->equal('status')->like('phone,content');
        $query->dateBetween('create_at')->order('id desc')->page();
    }

    /**
     * 短信接口配置
     * @auth true
     * @menu true
     * @throws \think\admin\Exception
     */
    public function config()
    {
        if ($this->request->isGet()) {
            $this->title = '短信接口配置';
            $this->result = MessageService::instance()->balance();
            $this->fetch();
        } else {
            $data = $this->request->post();
            foreach ($data as $k => $v) sysconf($k, $v);
            $this->success('配置保存成功！');
        }
    }

    /**
     * 删除短信记录
     * @auth true
     */
    public function remove()
    {
        PluginWemallUserMessage::mDelete();
    }
}