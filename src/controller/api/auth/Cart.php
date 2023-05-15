<?php

namespace plugin\wemall\controller\api\auth;

use plugin\wemall\controller\api\Auth;
use plugin\wemall\model\PluginWemallGoods;
use plugin\wemall\model\PluginWemallGoodsItem;
use plugin\wemall\model\PluginWemallOrderCart;
use think\admin\helper\QueryHelper;
use think\db\Query;

/**
 * 购物车接口
 * @class Cart
 * @package plugin\wemall\controller\api\auth
 */
class Cart extends Auth
{
    /**
     * 获取购买车数据
     * @return void
     */
    public function get()
    {
        PluginWemallOrderCart::mQuery(null, function (QueryHelper $query) {
            $query->equal('ghash')->where(['unid' => $this->unid])->with([
                'goods' => function (Query $query) {
                    $query->with('items');
                },
                'specs' => function (Query $query) {
                    $query->withoutField('id,create_time,update_time');
                },
            ]);
            $this->success('获取购买车数据！', $query->order('id desc')->page(false, false));
        });
    }

    /**
     * 修改购买车数据
     * @return void
     */
    public function set()
    {
        $data = $this->_vali([
            'unid.value'     => $this->unid,
            'ghash.require'  => '商品不能为空！',
            'number.require' => '数量不能为空！',
        ]);
        // 清理数量0的记录
        $map = ['unid' => $this->unid, 'ghash' => $data['ghash']];
        if ($data['number'] < 1) {
            PluginWemallOrderCart::mk()->where($map)->delete();
            $this->success('移除成功！');
        }
        // 检查商品是否存在
        $gspec = PluginWemallGoodsItem::mk()->where(['ghash' => $data['ghash']])->findOrEmpty();
        $goods = PluginWemallGoods::mk()->where(['code' => $gspec->getAttr('gcode')])->findOrEmpty();
        if ($goods->isEmpty() || $gspec->isEmpty()) $this->error('商品不存在！');
        // 保存商品数据
        $data += ['gcode' => $gspec['gcode'], 'gspec' => $gspec['gspec']];
        if (($cart = PluginWemallOrderCart::mk()->where($map)->findOrEmpty())->save($data)) {
            $this->success('保存成功！', $cart->refresh()->toArray());
        } else {
            $this->error('保存失败！');
        }
    }
}