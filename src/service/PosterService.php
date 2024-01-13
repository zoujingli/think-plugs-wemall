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

namespace plugin\wemall\service;

use app\wechat\service\MediaService;
use app\wechat\service\WechatService;
use plugin\account\service\Account;
use think\admin\Exception;
use think\admin\Library;
use think\admin\Service;
use think\admin\Storage;
use WeMini\Qrcode;

/**
 * 海报图片生成服务
 * @class PosterService
 * @package plugin\wuma\service
 */
class PosterService extends Service
{

    /**
     * 获取字体路径
     * @return string
     */
    public static function font(): string
    {
        return __DIR__ . '/extra/font01.ttf';
    }

    /**
     * 创建海报内容
     * @param string $target
     * @param array $items
     * @param array $extra
     * @return string
     * @throws \WeChat\Exceptions\InvalidResponseException
     * @throws \WeChat\Exceptions\LocalCacheException
     * @throws \think\admin\Exception
     */
    public static function create(string $target, array $items, array $extra = []): string
    {
        $name = Storage::name(json_encode(func_get_args(), 64 | 256), 'png', 'poster');
        if (empty($info = Storage::info($name))) {
            self::build($target, $items, $extra, $image);
            $info = Storage::set($name, $image);
        }
        return $info['url'] ?? $target;
    }

    /**
     * 海报图片图片
     * @param string $target 背景图片
     * @param array $items 配置参数
     * @param array $extra 自定内容
     * @param string|null $image 合成图片
     * @return string
     * @throws \WeChat\Exceptions\InvalidResponseException
     * @throws \WeChat\Exceptions\LocalCacheException
     * @throws \think\admin\Exception
     */
    public static function build(string $target, array $items, array $extra = [], ?string &$image = null): string
    {
        $zoom = 1.5;
        $file = Storage::down($target)['file'] ?? '';
        if (empty($file) || !file_exists($file) || filesize($file) < 10) {
            throw new Exception('读取背景图片失败！');
        }
        // 加载背景图
        [$sw, $wh] = getimagesize($file);
        [$tw, $th] = [intval(504 * $zoom), intval(713 * $zoom)];
        $font = self::font();
        $target = imagecreatetruecolor($tw, $th);
        $source = imagecreatefromstring(file_get_contents($file));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $tw, $th, $sw, $wh);
        foreach ($items as $item) if ($item['state']) {
            [$size, $item['value']] = [intval($item['size']), $extra[$item['rule']] ?? $item['value']];
            [$x, $y] = [intval($tw * $item['point']['x'] / 100), intval($th * $item['point']['y'] / 100)];
            if ($item['type'] === 'ximg') {
                $simg = self::createImage($item, $extra);
                imagecopyresampled($target, $simg, $x, $y, 0, 0, intval($size * $zoom), intval($size * $zoom), imagesx($simg), imagesy($simg));
                imagedestroy($simg);
            } else {
                if (preg_match('|^rgba\(\s*([\d.]+),\s*([\d.]+),\s*([\d.]+),\s*([\d.]+)\)$|', $item['color'], $matchs)) {
                    [, $r, $g, $b, $a] = $matchs;
                    $black = imagecolorallocatealpha($target, intval($r), intval($g), intval($b), (1 - $a) * 127);
                } else {
                    $black = imagecolorallocate($target, 0x00, 0x00, 0x00);
                }
                imagefttext($target, $size, 0, $x, intval($y + $size / 2 + 16), $black, $font, $item['value']);
            }
        }
        ob_start() && imagepng($target) && ($image = ob_get_contents());
        ob_end_clean() && imagedestroy($target) && imagedestroy($source);
        return sprintf("data:image/png;base64,%s", base64_encode($image));
    }

    /**
     * 创建其他图形对象
     * @param array $item
     * @param array $extra
     * @return false|\GdImage|resource
     * @throws \WeChat\Exceptions\InvalidResponseException
     * @throws \WeChat\Exceptions\LocalCacheException
     * @throws \think\admin\Exception
     */
    private static function createImage(array $item, array $extra = [])
    {
        if ($item['rule'] === 'user.spreat' || stripos($item['rule'], 'qrcode') !== false) {
            // 当前访问终端
            $type = sysvar('plugin_account_user_type');
            // 动态计算推荐链接
            $link = $item['value'] ?: ($extra['user.spreat'] ?: '/pages/home/index?from=UNID');
            if (stripos($link, 'from=') === false) $link .= (strpos($link, '?') === false ? '?' : '&') . 'from=UNID';
            $link = str_replace('UNID', strval(intval(sysvar('plugin_account_user_unid'))), $link);
            // 根据环境生成二维码
            if ($type === Account::WXAPP) {
                // 微信小程序二维码
                $qrcode = Qrcode::instance(WechatService::getWxconf())->createMiniPath($link);
            } elseif (in_array($type, [Account::WAP, Account::WEB, Account::WECHAT])) {
                // 生成网页访问二维码
                $link = rtrim(ConfigService::get('domain'), '\\/') . $link;
            }
            // 动态读取二维码内容
            if (!empty($qrcode) || !empty($extra['user.qrcode']) && !empty($qrcode = Library::$sapp->cache->get($extra['user.qrcode']))) {
                return imagecreatefromstring($qrcode);
            } else {
                return imagecreatefromstring(MediaService::getQrcode($link)->getString());
            }
        } else {
            $file = Storage::down($item['value'] ?: Account::headimg())['file'] ?? '';
            if (empty($file) || !is_file($file) || filesize($file) < 10) {
                throw new Exception('读取图片内容失败！');
            }
            return imagecreatefromstring(file_get_contents($file));
        }
    }
}