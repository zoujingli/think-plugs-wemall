<?php

namespace plugin\wemall\controller\api;

use plugin\account\controller\api\Auth as AccountAuth;
use plugin\wemall\model\PluginWemallUserRelation;

abstract class Auth extends AccountAuth
{
    protected $relation = ['levelCode' => 0, 'levelName' => ''];

    protected function initialize()
    {
        parent::initialize();
        $this->checkUserStatus();
        $this->withUserRelation();
    }

    /**
     * 初始化当前用户
     */
    protected function withUserRelation()
    {
        $relation = PluginWemallUserRelation::mk()->field('level_code,level_name')->where(['unid' => $this->unid])->findOrEmpty();
        $this->relation['levelCode'] = intval($relation->getAttr('level_code'));
        $this->relation['levelName'] = $relation->getAttr('level_name') ?: '普通用户';
    }
}