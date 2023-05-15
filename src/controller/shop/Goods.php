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

namespace plugin\wemall\controller\shop;

use plugin\payment\service\Payment as PaymentService;
use plugin\wemall\model\PluginWemallConfigDiscount;
use plugin\wemall\model\PluginWemallConfigUpgrade;
use plugin\wemall\model\PluginWemallExpressTemplate;
use plugin\wemall\model\PluginWemallGoods;
use plugin\wemall\model\PluginWemallGoodsCate;
use plugin\wemall\model\PluginWemallGoodsItem;
use plugin\wemall\model\PluginWemallGoodsMark;
use plugin\wemall\model\PluginWemallGoodsStock;
use plugin\wemall\service\GoodsService;
use think\admin\Controller;
use think\admin\extend\CodeExtend;
use think\admin\helper\QueryHelper;
use think\exception\HttpResponseException;

/**
 * 商品数据管理
 * @class Goods
 * @package plugin\wemall\controller\shop
 */
class Goods extends Controller
{
    /**
     * 商品数据管理
     * @auth true
     * @menu true
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index()
    {
        $this->type = $this->request->get('type', 'index');
        PluginWemallGoods::mQuery()->layTable(function () {
            $this->title = '商品数据管理';
            $this->cates = PluginWemallGoodsCate::items();
            $this->marks = PluginWemallGoodsMark::items();
            $this->upgrades = PluginWemallConfigUpgrade::items(true);
            $this->deliverys = PluginWemallExpressTemplate::items(true);
        }, function (QueryHelper $query) {
            $query->withoutField('specs,content');
            $query->like('code|name#name')->like('marks,cates', ',');
            $query->equal('status,level_upgrade,delivery_code,rebate_type');
            $query->where(['status' => intval($this->type === 'index'), 'deleted' => 0]);
        });
    }

    /**
     * 商品选择器
     * @login true
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function select()
    {
        $query = PluginWemallGoods::mQuery();
        $query->equal('status')->like('code,name,marks')->in('cates');
        $query->where(['deleted' => 0])->order('sort desc,id desc')->page();
    }

    /**
     * 添加商品数据
     * @auth true
     */
    public function add()
    {
        $this->mode = 'add';
        $this->title = '添加商品数据';
        PluginWemallGoods::mForm('form', 'code');
    }

    /**
     * 编辑商品数据
     * @auth true
     */
    public function edit()
    {
        $this->mode = 'edit';
        $this->title = '编辑商品数据';
        PluginWemallGoods::mForm('form', 'code');
    }

    /**
     * 复制编辑商品
     * @auth true
     */
    public function copy()
    {
        $this->mode = 'copy';
        $this->title = '复制编辑商品';
        PluginWemallGoods::mForm('form', 'code');
    }

    /**
     * 表单数据处理
     * @param array $data
     */
    protected function _copy_form_filter(array &$data)
    {
        if ($this->request->isPost()) {
            $data['code'] = CodeExtend::uniqidNumber(16, 'G');
        }
    }

    /**
     * 表单数据处理
     * @param array $data
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    protected function _form_filter(array &$data)
    {
        if (empty($data['code'])) {
            $data['code'] = CodeExtend::uniqidNumber(16, 'G');
        }
        if ($this->request->isGet()) {
            $this->marks = PluginWemallGoodsMark::items();
            $this->cates = PluginWemallGoodsCate::items(true);
            $this->payments = PaymentService::items(true);
            $this->upgrades = PluginWemallConfigUpgrade::items(true);
            $this->discounts = PluginWemallConfigDiscount::items(true);
            $this->deliverys = PluginWemallExpressTemplate::items(true);
            $data['marks'] = $data['marks'] ?? [];
            $data['cates'] = $data['cates'] ?? [];
            $data['payment'] = $data['payment'] ?? [];
            $data['delivery_code'] = $data['delivery_code'] ?? 'FREE';
            $data['specs'] = json_encode($data['specs'] ?? [], 64 | 256);
            $data['items'] = PluginWemallGoodsItem::itemsJson($data['code']);
        } elseif ($this->request->isPost()) try {
            if (empty($data['cover'])) $this->error('商品图片不能为空！');
            if (empty($data['slider'])) $this->error('轮播图片不能为空！');
            // 商品规格保存
            [$count, $items] = [0, array_column(json_decode($data['items'], true), 0)];
            foreach ($items as $item) if ($item['status'] > 0) $count++;
            if (empty($count)) $this->error('无效的的商品价格信息！');
            $data['marks'] = arr2str($data['marks'] ?? []);
            $data['payment'] = arr2str(in_array('all', $data['payment'] ?? []) ? ['all'] : ($data['payment'] ?? []));
            $data['price_market'] = min(array_column($items, 'market'));
            $data['price_selling'] = min(array_column($items, 'selling'));
            $this->app->db->transaction(function () use ($data, $items) {
                PluginWemallGoods::mk()->where(['code' => $data['code']])->findOrEmpty()->save($data);
                PluginWemallGoodsItem::mk()->where(['gcode' => $data['code']])->update(['status' => 0]);
                foreach ($items as $item) {
                    $gmap = ['ghash' => md5($data['code'] . $item['key'])];
                    PluginWemallGoodsItem::mk()->where($gmap)->findOrEmpty()->save([
                        'gsku'            => $item['sku'],
                        'ghash'           => $gmap['ghash'],
                        'gspec'           => $item['key'],
                        'gcode'           => $data['code'],
                        'price_market'    => $item['market'],
                        'price_selling'   => $item['selling'],
                        'number_virtual'  => $item['virtual'],
                        'number_express'  => $item['express'],
                        'reward_balance'  => $item['balance'],
                        'reward_integral' => $item['integral'],
                        'status'          => $item['status'] ? 1 : 0,
                    ]);
                }
            });
            // 刷新产品库存
            GoodsService::stock($data['code']);
            $this->success('商品编辑成功！', 'javascript:history.back()');
        } catch (HttpResponseException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }
    }

    /**
     * 商品库存入库
     * @auth true
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function stock()
    {
        $map = $this->_vali(['code.require' => '商品编号不能为空哦！']);
        if ($this->request->isGet()) {
            $this->vo = PluginWemallGoods::mk()->where($map)->with('items')->findOrEmpty()->toArray();
            if (empty($this->vo)) $this->error('无效的商品数据，请稍候再试！');
            $this->fetch();
        } else {
            [$data, $post, $batch] = [[], $this->request->post(), CodeExtend::uniqidDate(12, 'B')];
            if (isset($post['gcode']) && is_array($post['gcode'])) {
                foreach (array_keys($post['gcode']) as $key) if ($post['gstock'][$key] > 0) $data[] = [
                    'gcode'  => $post['gcode'][$key], 'batch_no' => $batch,
                    'ghash'  => md5($post['gcode'][$key] . $post['gspec'][$key]),
                    'gspec'  => $post['gspec'][$key],
                    'gstock' => $post['gstock'][$key],
                ];
                if (!empty($data)) {
                    PluginWemallGoodsStock::mk()->saveAll($data);
                    GoodsService::stock($map['code']);
                    $this->success('商品数据入库成功！');
                }
            }
            $this->error('没有需要商品入库的数据！');
        }
    }

    /**
     * 商品上下架
     * @auth true
     */
    public function state()
    {
        PluginWemallGoods::mSave($this->_vali([
            'status.in:0,1'  => '状态值范围异常！',
            'status.require' => '状态值不能为空！',
        ]), 'code');
    }

    /**
     * 删除商品数据
     * @auth true
     */
    public function remove()
    {
        PluginWemallGoods::mSave($this->_vali([
            'deleted.in:0,1'  => '状态值范围异常！',
            'deleted.require' => '状态值不能为空！',
        ]), 'code');
    }
}