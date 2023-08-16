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

namespace plugin\wemall\service;

use think\admin\Exception;
use think\admin\Storage;
use think\admin\storage\LocalStorage;

/**
 * 媒体保存处理服务
 * @class ImageService
 * @package plugin\wemall\service
 */
class ImageService
{
    /**
     * 图片数据存储
     * @param string $base64 图片内容
     * @param string $prefix 文件保存前缀
     * @param boolean $safemode 是否安全模式
     * @return array
     * @throws \think\admin\Exception
     */
    public static function save(string $base64, string $prefix = 'image', bool $safemode = false): array
    {
        if (preg_match('|^data:image/(.*?);base64,|i', $base64)) {
            [$ext, $image] = explode('|||', preg_replace('|^data:image/(.*?);base64,|i', '$1|||', $base64));
            if (empty($ext) || !in_array(strtolower($ext), ['png', 'jpg', 'jpeg'])) {
                throw new Exception('内容格式异常！');
            }
            if ($safemode) {
                $name = Storage::name($image, $ext, "{$prefix}/");
                return LocalStorage::instance()->set($name, base64_decode($image), true);
            } else {
                $name = Storage::name($image, $ext, "upload/{$prefix}/");
                return Storage::instance()->set($name, base64_decode($image));
            }
        } else {
            return ['url' => $base64];
        }
    }
}