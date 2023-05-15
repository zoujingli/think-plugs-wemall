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

/**
 * 快递模板数据模型
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
        $express = [];
        static $companys = [];
        if (empty($companys)) $companys = PluginWemallExpressCompany::items();
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
        return is_string($value) ? json_decode($value, true) : $value;
    }

    /**
     * 格式化规则数据
     * @param mixed $value
     * @return array
     */
    public function getContentAttr($value): array
    {
        return is_string($value) ? json_decode($value, true) : $value;
    }
}