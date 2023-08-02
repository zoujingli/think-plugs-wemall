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

namespace plugin\wemall\controller\api;

use plugin\wemall\model\PluginWemallGoods;
use plugin\wemall\model\PluginWemallGoodsCate;
use plugin\wemall\model\PluginWemallGoodsMark;
use plugin\wemall\service\ExpressService;
use think\admin\Controller;
use think\admin\helper\QueryHelper;

class Goods extends Controller
{

    /**
     * 获取商品列表或详情
     * @return void
     */
    public function get()
    {
        PluginWemallGoods::mQuery(null, function (QueryHelper $query) {
            $query->equal('code')->like('name')->like('marks,cates', ',');
            if (!empty($code = input('code'))) {
                $query->with('items');
                PluginWemallGoods::mk()->where(['code' => $code])->inc('num_read')->update([]);
            } else {
                $query->withoutField('content');
            }
            $query->where(['status' => 1, 'deleted' => 0])->order('sort desc,id desc');
            $this->success('获取商品数据', $query->page(intval(input('page', 1)), false, false, 10));
        });
    }

    /**
     * 获取商品分类及标签
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function cate()
    {
        $this->success('获取分类成功', [
            'mark' => PluginWemallGoodsMark::items(),
            'cate' => PluginWemallGoodsCate::treeData(),
        ]);
    }

    /**
     * 获取物流配送区域
     * @return void
     * @throws \think\admin\Exception
     */
    public function region()
    {
        $this->success('获取配送区域', ExpressService::region(3, 1));
    }
}