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

use plugin\account\service\Account;
use plugin\wemall\model\PluginWemallConfigLevel;
use plugin\wemall\model\PluginWemallConfigPoster;
use plugin\wemall\service\PosterService;
use think\admin\Controller;
use think\admin\extend\CodeExtend;
use think\admin\helper\QueryHelper;
use think\exception\HttpResponseException;

/**
 * 推广海报管理
 * @class Poster
 * @package plugin\wemall\controller\base
 */
class Poster extends Controller
{
    /**
     * 推广海报管理
     * @auth true
     * @menu true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index()
    {
        $this->type = $this->get['type'] ?? 'index';
        PluginWemallConfigPoster::mQuery()->layTable(function () {
            $this->title = '推广海报管理';
        }, function (QueryHelper $query) {
            $query->like('name,code')->equal('status')->dateBetween('create_time');
            $query->where(['deleted' => 0, 'status' => intval($this->type === 'index')]);
        });
    }

    /**
     * 添加推广海报
     * @auth true
     */
    public function add()
    {
        $this->title = '添加推广海报';
        PluginWemallConfigPoster::mForm('form');
    }

    /**
     * 编辑推广海报
     * @auth true
     */
    public function edit()
    {
        $this->title = '编辑推广海报';
        PluginWemallConfigPoster::mForm('form');
    }

    /**
     * 表单数据处理
     * @param array $data
     * @return void
     */
    protected function _form_filter(array &$data)
    {
        if (empty($data['code'])) {
            $data['code'] = CodeExtend::uniqidNumber(16, 'T');
        }
        if ($this->request->isGet()) {
            $this->levels = PluginWemallConfigLevel::items();
            array_unshift($this->levels, ['name' => '全部', 'number' => '-']);
            $this->devices = array_merge(['-' => ['name' => '全部']], Account::types(1));
        } else {
            $data['levels'] = arr2str($data['levels'] ?? []);
            $data['devices'] = arr2str($data['devices'] ?? []);
        }
    }

    /**
     * 表单结果处理
     * @param bool $result
     * @return void
     */
    protected function _form_result(bool $result)
    {
        if ($result) {
            $this->success('海报保存成功！', 'javascript:history.back()');
        } else {
            $this->error('海报保存失败！');
        }
    }

    /**
     * 预览授权书生成
     * @auth true
     * @return void
     */
    public function show()
    {
        try {
            $data = $this->_vali([
                'image.require' => '图片不能为空！',
                'items.require' => '规则不能为空！',
            ]);
            $items = json_decode($data['items'], true);
            $base64 = PosterService::build($data['image'], $items);
            $this->success('生成证书图片', ['base64' => $base64]);
        } catch (HttpResponseException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }
    }

    /**
     * 修改海报状态
     * @auth true
     */
    public function state()
    {
        PluginWemallConfigPoster::mSave($this->_vali([
            'status.in:0,1'  => '状态值范围异常！',
            'status.require' => '状态值不能为空！',
        ]));
    }

    /**
     * 删除推广海报
     * @auth true
     */
    public function remove()
    {
        PluginWemallConfigPoster::mDelete();
    }
}