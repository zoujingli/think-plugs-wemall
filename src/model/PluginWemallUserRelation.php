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

namespace plugin\wemall\model;

use plugin\account\model\PluginAccountUser;
use think\model\relation\HasOne;

/**
 * 用户关系数据
 * @class PluginWemallUserRelation
 * @package plugin\wemall\model
 */
class PluginWemallUserRelation extends Abs
{
    /**
     * 关联当前用户
     * @return \think\model\relation\HasOne
     */
    public function user(): HasOne
    {
        return $this->hasOne(PluginAccountUser::class, 'id', 'unid');
    }

    /**
     * 关联上1级用户
     * @return \think\model\relation\HasOne
     */
    public function user1(): HasOne
    {
        return $this->hasOne(PluginAccountUser::class, 'id', 'puid1');
    }

    /**
     * 关联上2级用户
     * @return \think\model\relation\HasOne
     */
    public function user2(): HasOne
    {
        return $this->hasOne(PluginAccountUser::class, 'id', 'puid2');
    }

    public function setExtraAttr($value): string
    {
        return is_array($value) ? json_encode($value, 64 | 256) : (string)$value;
    }

    public function getExtraAttr($value): array
    {
        return is_string($value) ? json_decode($value, true) : [];
    }

    /**
     * 同步更新数据
     * @param integer $unid 用户编号
     * @param integer $from 上级用户
     * @return bool|string
     */
    public static function sync(int $unid, int $from = 0)
    {
        $user = PluginAccountUser::mk()->findOrEmpty($unid);
        if ($user->isEmpty()) return '无效的用户信息';
        $data = ['unid' => $unid, 'path' => ',,'];
        if ($from > 0) {
            $parent = static::mk()->where(['unid' => $from])->findOrEmpty();
            if ($parent->isEmpty()) return '无效的上级用户';
            $data['path'] = arr2str(str2arr("{$from},{$parent->getAttr('path')}"));
            $data['puid1'] = $parent->getAttr('unid');
            $data['puid2'] = $parent->getAttr('puid1');
        }
        return static::mk()->where(['unid' => $unid])->findOrEmpty()->save($data);
    }
}