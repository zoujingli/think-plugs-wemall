# ThinkPlugsWemall for ThinkAdmin

[![Latest Stable Version](https://poser.pugx.org/zoujingli/think-plugs-wemall/v/stable)](https://packagist.org/packages/zoujingli/think-plugs-wemall)
[![Total Downloads](https://poser.pugx.org/zoujingli/think-plugs-wemall/downloads)](https://packagist.org/packages/zoujingli/think-plugs-wemall)
[![Monthly Downloads](https://poser.pugx.org/zoujingli/think-plugs-wemall/d/monthly)](https://packagist.org/packages/zoujingli/think-plugs-wemall)
[![Daily Downloads](https://poser.pugx.org/zoujingli/think-plugs-wemall/d/daily)](https://packagist.org/packages/zoujingli/think-plugs-wemall)
[![PHP Version](https://doc.thinkadmin.top/static/icon/php-7.1.svg)](https://thinkadmin.top)
[![License](https://doc.thinkadmin.top/static/icon/license-vip.svg)](https://thinkadmin.top/vip-introduce)

**注意：** 该插件测试版有数据库结构变化，未生成升级补丁，每次更新需要全新安装！

多终端微商城系统，此插件为[会员尊享插件](https://thinkadmin.top/vip-introduce)，未授权不可商用。

代码主仓库放在 **Gitee**，**Github** 仅为镜像仓库用于发布 **Composer** 包。

### 依赖插件

* 插件服务管理中心：[ThinkPlugsCenter](https://doc.thinkadmin.top/think-plugs-center)
* 多终端用户管理插件：[ThinkPlugsAccount](https://doc.thinkadmin.top/vip-plugs-account)
* 多终端支付管理插件：[ThinkPlugsPayment](https://doc.thinkadmin.top/vip-plugs-payment)

### 接口文档

接口文档：https://documenter.getpostman.com/view/4518676/2s93eeRpDr

### 安装插件

```shell
### 安装前建议尝试更新所有组件
composer update --optimize-autoloader

### 安装稳定版本 ( 插件仅支持在 ThinkAdmin v6.1 中使用 )
composer require zoujingli/think-plugs-wemall --optimize-autoloader

### 安装测试版本（ 插件仅支持在 ThinkAdmin v6.1 中使用 ）
composer require zoujingli/think-plugs-wemall dev-master --optimize-autoloader
```

### 卸载插件

```shell
composer remove zoujingli/think-plugs-wemall
```

### 插件数据

本插件涉及数据表有：--

### 版权说明

**ThinkPlugsWemall** 为 **ThinkAdmin** 会员插件，未授权不可商用，了解商用授权请阅读 [《会员尊享介绍》](https://thinkadmin.top/vip-introduce)。