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

declare (strict_types=1);

namespace plugin\wemall\model;

use think\db\Query;

/**
 * 推广海报数据模型
 * @class PluginWemallConfigPoster
 * @package plugin\wemall\model
 */
class PluginWemallConfigPoster extends Abs
{

    /**
     * 指定用户等级获取配置
     * @param integer $level
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function items(int $level): array
    {
        $query = self::mk()->where(static function (Query $query) use ($level) {
            $query->whereOr([['levels', 'like', "%,{$level},%"], ['levels', 'like', '%,-,%']]);
        })->where(['status' => 1, 'deleted' => 0])->order('sort desc,id desc');
        return $query->withoutField('sort,status,deleted')->select()->toArray();
    }

    /**
     * 获取用户等级数据
     * @param mixed $value
     * @return array
     */
    public function getLevelsAttr($value): array
    {
        return is_string($value) ? str2arr($value) : [];
    }

    /**
     * 格式化定位数据
     * @param mixed $value
     * @return mixed
     */
    public function getContentAttr($value)
    {
        return json_decode($value ?: '[]', true);
    }
}