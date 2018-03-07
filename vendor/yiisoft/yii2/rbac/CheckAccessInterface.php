<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\rbac;

/**
 * @author Sam Mousa <sam@mousa.nl>
 * @since 2.0.9
 */
interface CheckAccessInterface
{
    /**
     * 检查用户是否有指定的权限。
     * Checks if the user has the specified permission.
     * 用户ID，这应该是一个整数，或者是一个用户的唯一标识符字符串
     * @param string|integer $userId the user ID. This should be either an integer or a string representing
     * the unique identifier of a user. See [[\yii\web\User::id]].
     * 被检查的权限的名称
     * @param string $permissionName the name of the permission to be checked against
     * 将被传递与分配给用户的角色和权限相关规则的键-值对；
     * @param array $params name-value pairs that will be passed to the rules associated with the roles and permissions assigned to the user.
     * @return boolean whether the user has the specified permission.
     * 如果$permissionName不是已存在的权限
     * @throws \yii\base\InvalidParamException if $permissionName does not refer to an existing permission
     */
    public function checkAccess($userId, $permissionName, $params = []);
}
