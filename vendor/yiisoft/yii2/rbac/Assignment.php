<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\rbac;

use Yii;
use yii\base\Object;

/**
 *Assignment代表给一个用户分配角色
 * Assignment represents an assignment of a role to a user.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Alexander Kochetov <creocoder@gmail.com>
 * @since 2.0
 */
class Assignment extends Object
{
    /**
     * @var string|integer user ID (see [[\yii\web\User::id]])
     */
    public $userId;
    /**
     * 角色名称
     * @var string the role name
     */
    public $roleName;
    /**
     * 创建时间的UNIX时间戳
     * @var integer UNIX timestamp representing the assignment creation time
     */
    public $createdAt;
}
