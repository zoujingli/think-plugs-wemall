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
use plugin\wemall\model\PluginWemallUserTransfer;
use plugin\wemall\service\UserTransferService;
use think\admin\Controller;
use think\admin\extend\CodeExtend;
use think\admin\helper\QueryHelper;
use think\admin\service\AdminService;

/**
 * 用户提现管理
 * @class Transfer
 * @package plugin\wemall\controller\user
 */
class Transfer extends Controller
{
    /**
     * 提现转账方案
     * @var array
     */
    protected $types = [];

    protected function initialize()
    {
        $this->types = UserTransferService::types();
    }

    /**
     * 用户提现配置
     * @throws \think\admin\Exception
     */
    public function config()
    {
        $this->skey = 'plugin.wemall.transfer.config';
        $this->title = '用户提现配置';
        $this->_sysdata();
    }

    /**
     * 微信转账配置
     * @throws \think\admin\Exception
     */
    public function payment()
    {
        $this->skey = 'plugin.wemall.transfer.wxpay';
        $this->title = '微信提现配置';
        $this->_sysdata();
    }

    /**
     * 配置数据处理
     * @throws \think\admin\Exception
     */
    private function _sysdata()
    {
        if ($this->request->isGet()) {
            $this->data = sysdata($this->skey);
            $this->fetch('');
        } else {
            sysdata($this->skey, $this->request->post());
            $this->success('配置修改成功');
        }
    }

    /**
     * 用户提现管理
     * @menu true
     * @auth true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index()
    {
        PluginWemallUserTransfer::mQuery()->layTable(function () {
            $this->title = '用户提现管理';
            $this->transfer = UserTransferService::amount(0);
        }, function (QueryHelper $query) {
            // 用户条件搜索
            $db = PluginAccountUser::mQuery()->like('phone,username|nickname#nickname')->db();
            if ($db->getOptions('where')) $query->whereRaw("unid in {$db->field('id')->buildSql()}");
            // 数据列表处理
            $query->equal('type,status')->dateBetween('create_at');
        });
    }

    /**
     * 数据列表处理
     * @param array $data
     */
    protected function _page_filter(array &$data)
    {
        foreach ($data as &$vo) {
            $vo['type_name'] = UserTransferService::types($vo['type']);
        }
    }

    /**
     * 提现审核操作
     * @auth true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function auditStatus()
    {
        $this->_audit();
    }

    /**
     * 提现打款操作
     * @auth true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function auditPayment()
    {
        $this->_audit();
    }

    /**
     * 提现审核打款
     * @auth true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    private function _audit()
    {
        if ($this->request->isGet()) {
            PluginWemallUserTransfer::mForm('audit', 'code');
        } else {
            $data = $this->_vali([
                'code.require'        => '打款单号不能为空！',
                'status.require'      => '交易审核操作类型！',
                'status.in:0,1,2,3,4' => '交易审核操作类型！',
                'remark.default'      => '',
            ]);
            $map = ['code' => $data['code']];
            $find = PluginWemallUserTransfer::mk()->where($map)->find();
            if (empty($find)) $this->error('不允许操作审核！');
            // 提现状态(0已拒绝, 1待审核, 2已审核, 3打款中, 4已打款, 5已收款)
            if (in_array($data['status'], [0, 1, 2, 3])) {
                $data['last_at'] = date('Y-m-d H:i:s');
            } elseif ($data['status'] == 4) {
                $data['trade_no'] = CodeExtend::uniqidDate(20);
                $data['trade_time'] = date('Y-m-d H:i:s');
                $data['change_time'] = date('Y-m-d H:i:s');
                $data['change_desc'] = ($data['remark'] ?: '线下打款成功') . ' By ' . AdminService::getUserName();
            }
            if (PluginWemallUserTransfer::mk()->strict(false)->where($map)->update($data) !== false) {
                $this->success('操作成功');
            } else {
                $this->error('操作失败！');
            }
        }
    }

    /**
     * 后台打款服务
     * @auth true
     */
    public function sync()
    {
        $this->_queue('提现到微信余额定时处理', 'xdata:UserTransfer', 0, [], 0, 50);
    }
}