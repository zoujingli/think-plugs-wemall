<?php

namespace plugin\wemall\controller\api;

use plugin\account\controller\api\Auth as AccountAuth;
use plugin\wemall\model\PluginWemallUserRelation;

/**
 * 基础授权控制器
 * @class Auth
 * @package plugin\wemall\controller\api
 */
abstract class Auth extends AccountAuth
{
    protected $relation = ['data' => [], 'levelCode' => 0, 'levelName' => ''];
    protected $levelCode;
    protected $levelName;

    /**
     * 控制器初始化
     * @return void
     */
    protected function initialize()
    {
        parent::initialize();
        $this->checkUserStatus();
        $this->withUserRelation();
    }

    /**
     * 初始化当前用户
     * @return static
     */
    protected function withUserRelation(): Auth
    {
        $relation = PluginWemallUserRelation::mk()->where(['unid' => $this->unid])->findOrEmpty();
        $this->relation = $relation->toArray();
        $this->levelCode = intval($relation->getAttr('level_code'));
        $this->levelName = $relation->getAttr('level_name') ?: '普通用户';
        return $this;
    }
}