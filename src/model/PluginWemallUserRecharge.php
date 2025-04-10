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

/**
 * 用户余额充值模型
 *
 * @property float $amount 操作金额
 * @property int $create_by 系统用户
 * @property int $deleted 删除状态(0未删除,1已删除)
 * @property int $deleted_by 系统用户
 * @property int $id
 * @property int $unid 账号编号
 * @property string $code 操作编号
 * @property string $create_time 创建时间
 * @property string $deleted_time 删除时间
 * @property string $name 操作名称
 * @property string $remark 操作备注
 * @property string $update_time 更新时间
 * @class PluginWemallUserRecharge
 * @package plugin\wemall\model
 */
class PluginWemallUserRecharge extends AbsUser
{
}