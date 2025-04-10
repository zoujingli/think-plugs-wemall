<?php

// +----------------------------------------------------------------------
// | WeMall Plugin for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2025 ThinkAdmin [ thinkadmin.top ]
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

namespace plugin\wemall\model;

use think\model\relation\HasOne;

/**
 * 用户评论数据模型
 *
 * @property array $images 评论图片
 * @property float $rate 评论分数
 * @property int $deleted 删除状态(0未删,1已删)
 * @property int $id
 * @property int $ssid 所属商家
 * @property int $status 评论状态(0隐藏,1显示)
 * @property int $unid 用户编号
 * @property string $code 评论编号
 * @property string $content 评论内容
 * @property string $create_time 创建时间
 * @property string $gcode 商品编号
 * @property string $ghash 商品哈希
 * @property string $order_no 订单单号
 * @property string $update_time 更新时间
 * @property-read \plugin\wemall\model\PluginWemallGoods $bind_goods
 * @property-read \plugin\wemall\model\PluginWemallGoods $goods
 * @property-read \plugin\wemall\model\PluginWemallOrder $orderinfo
 * @class PluginWemallUserActionComment
 * @package plugin\wemall\model
 */
class PluginWemallUserActionComment extends AbsUser
{
    /**
     * 关联商品信息
     * @return \think\model\relation\HasOne
     */
    public function goods(): HasOne
    {
        return $this->hasOne(PluginWemallGoods::class, 'code', 'gcode');
    }

    /**
     * 关联订单数据
     * @return \think\model\relation\HasOne
     */
    public function orderinfo(): HasOne
    {
        return $this->hasOne(PluginWemallOrder::class, 'order_no', 'order_no');
    }

    /**
     * 绑定商品信息
     * @return \think\model\relation\HasOne
     */
    public function bindGoods(): HasOne
    {
        return $this->goods()->bind([
            'goods_name' => 'name',
            'goods_code' => 'code',
        ]);
    }

    /**
     * 格式化图片格式
     * @param mixed $value
     * @return array
     */
    public function getImagesAttr($value): array
    {
        return str2arr($value ?? '', '|');
    }
}