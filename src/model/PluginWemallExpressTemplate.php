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

namespace plugin\wemall\model;

use plugin\account\model\Abs;

/**
 * 商城快递模板数据
 *
 * @property array $company 快递公司
 * @property array $content 模板规则
 * @property array $normal 默认规则
 * @property int $deleted 删除状态(1已删,0未删)
 * @property int $id
 * @property int $sort 排序权重
 * @property int $status 激活状态(0无效,1有效)
 * @property string $code 模板编号
 * @property string $create_time 创建时间
 * @property string $name 模板名称
 * @property string $update_time 更新时间
 * @class PluginWemallExpressTemplate
 * @package plugin\wemall\model
 */
class PluginWemallExpressTemplate extends Abs
{

    /**
     * 获取快递模板数据
     * @param boolean $allow
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function items(bool $allow = false): array
    {
        $items = $allow ? [
            'NONE' => ['code' => 'NONE', 'name' => '无需发货', 'normal' => '', 'content' => '[]', 'company' => ['_' => '虚拟产品']],
            'FREE' => ['code' => 'FREE', 'name' => '免费包邮', 'normal' => '', 'content' => '[]', 'company' => ['ALL' => '发货时选快递公司']],
        ] : [];
        $query = self::mk()->where(['status' => 1, 'deleted' => 0])->order('sort desc,id desc');
        foreach ($query->field('code,name,normal,content,company')->cursor() as $tmpl) $items[$tmpl->getAttr('code')] = $tmpl->toArray();
        return $items;
    }

    /**
     * 写入快递公司
     * @param mixed $value
     * @return string
     */
    public function setCompanyAttr($value): string
    {
        return is_array($value) ? arr2str($value) : $value;
    }

    /**
     * 快递公司处理
     * @param mixed $value
     * @return array
     */
    public function getCompanyAttr($value): array
    {
        [$express, $skey] = [[], 'plugin.wemall.companys'];
        $companys = sysvar($skey) ?: sysvar($skey, PluginWemallExpressCompany::items());
        foreach (is_string($value) ? str2arr($value) : (array)$value as $key) {
            if (isset($companys[$key])) $express[$key] = $companys[$key];
        }
        return $express;
    }

    /**
     * 格式化规则数据
     * @param mixed $value
     * @return array
     */
    public function getNormalAttr($value): array
    {
        return $this->getExtraAttr($value);
    }

    public function setNormalAttr($value): string
    {
        return $this->setExtraAttr($value);
    }

    /**
     * 格式化规则数据
     * @param mixed $value
     * @return array
     */
    public function getContentAttr($value): array
    {
        return $this->getExtraAttr($value);
    }

    public function setContentAttr($value): string
    {
        return $this->setExtraAttr($value);
    }
}