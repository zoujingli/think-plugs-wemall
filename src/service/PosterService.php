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

namespace plugin\wemall\service;

use app\wechat\service\MediaService;
use think\admin\Exception;
use think\admin\Service;
use think\admin\Storage;

/**
 * 海报图片生成服务
 * @class PosterService
 * @package plugin\wuma\service
 */
class PosterService extends Service
{

    /**
     * 创建海报内容
     * @param string $target
     * @param array $items
     * @param array $extra
     * @return string
     * @throws \think\admin\Exception
     */
    public static function create(string $target, array $items, array $extra = []): string
    {
        $hash = md5(json_encode(func_get_args(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $name = 'poster/' . substr($hash, 0, 2) . substr($hash, 2, 30) . '.png';
        if (empty($info = Storage::info($name))) {
            self::build($target, $items, $extra, $image);
            $info = Storage::set($name, $image);
        }
        return $info['url'] ?? $target;
    }

    /**
     * 海报图片图片
     * @param string $target
     * @param array $items 元素配置参数
     * @param array $extra 扩展自定内容
     * @param string|null $image 引用图片
     * @return string
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
        $font = __DIR__ . '/extra/font01.ttf';
        $target = imagecreatetruecolor($tw, $th);
        $source = imagecreatefromstring(file_get_contents($file));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $tw, $th, $sw, $wh);
        foreach ($items as $item) if ($item['state']) {
            [$size, $item['value']] = [intval($item['size']), $extra[$item['rule']] ?? $item['value']];
            [$x, $y] = [intval($tw * $item['point']['x'] / 100), intval($th * $item['point']['y'] / 100)];
            if ($item['type'] === 'ximg') {
                $subImage = self::createImage($item, $imgW, $imgH);
                imagecopyresampled($target, $subImage, $x, $y, 0, 0, intval($size * $zoom), intval($size * $zoom), $imgW, $imgH);
                imagedestroy($subImage);
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
        ob_start();
        imagepng($target);
        $image = ob_get_contents();
        ob_end_clean() && imagedestroy($target) && imagedestroy($source);
        return sprintf("data:image/png;base64,%s", base64_encode($image));
    }

    /**
     * 创建其他图形对象
     * @param array $item
     * @param integer|null $sw
     * @param integer|null $sh
     * @return false|\GdImage|resource
     * @throws \think\admin\Exception
     */
    private static function createImage(array $item, ?int &$sw = 0, ?int &$sh = 0)
    {
        if ($item['rule'] === 'user.spreat' || stripos($item['rule'], 'qrcode') !== false) {
            [$sw, $sh] = [330, 330];
            $unid = sysvar('plugin_account_user_unid') ?: 0;
            $qrcode = MediaService::getQrcode("{$item['value']}?from={$unid}");
            return imagecreatefromstring($qrcode->getString());
        } else {
            $file = Storage::down($item['value'] ?: 'https://thinkadmin.top/static/img/logo.png')['file'] ?? '';
            if (empty($file) || !file_exists($file) || filesize($file) < 10) {
                throw new Exception('读取图片内容失败！');
            }
            [$sw, $sh] = getimagesize($file);
            return imagecreatefromstring(file_get_contents($file));
        }
    }
}