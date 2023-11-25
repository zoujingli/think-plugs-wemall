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

use think\admin\Model;

/**
 * 基于抽象类模型
 * @class Abs
 * @package plugin\wemall\model
 */
abstract class Abs extends Model
{
    /**
     * 时间读取格式处理
     * @param mixed $value
     * @return string
     */
    public function getCreateTimeAttr($value): string
    {
        return format_datetime($value);
    }

    /**
     * 时间写入格式处理
     * @param mixed $value
     * @return array|string|string[]
     */
    public function setCreateTimeAttr($value)
    {
        if (is_string($value)) {
            return str_replace(['年', '月', '日'], ['-', '-', ''], $value);
        } else {
            return $value;
        }
    }

    /**
     * 时间读取格式处理
     * @param mixed $value
     * @return string
     */
    public function getUpdateTimeAttr($value): string
    {
        return $this->getCreateTimeAttr($value);
    }

    /**
     * 时间写入格式处理
     * @param mixed $value
     * @return array|string|string[]
     */
    public function setUpdateTimeAttr($value)
    {
        return $this->setCreateTimeAttr($value);
    }
}