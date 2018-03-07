<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\rbac;

/**
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
interface ManagerInterface extends CheckAccessInterface
{
    /**
     * 创建一个新的角色对象。
     * 注意，新创建的角色并没有添加到RBAC系统中。
     * 您必须填充所需的数据，并调用[[add()]]将其添加到系统中
     * Creates a new Role object.
     * Note that the newly created role is not added to the RBAC system yet.
     * You must fill in the needed data and call [[add()]] to add it to the system.
     * @param string $name the role name
     * @return Role the new Role object
     */
    public function createRole($name);

    /**
     * 创建一个新的权限对象。
     * 注意，新创建的权限对象并没有添加到RBAC系统中。
     * 您必须填充所需的数据，并调用[[add()]]将其添加到系统中
     * Creates a new Permission object.
     * Note that the newly created permission is not added to the RBAC system yet.
     * You must fill in the needed data and call [[add()]] to add it to the system.
     * @param string $name the permission name
     * @return Permission the new Permission object
     */
    public function createPermission($name);

    /**
     * 在RBAC系统中添加一个角色、权限或规则
     * Adds a role, permission or rule to the RBAC system.
     * @param Role|Permission|Rule $object
     * @return boolean whether the role, permission or rule is successfully added to the system
     * @throws \Exception if data validation or saving fails (such as the name of the role or permission is not unique)
     */
    public function add($object);

    /**
     * 从RBAC系统中删除一个角色、权限或规则。
     * Removes a role, permission or rule from the RBAC system.
     * @param Role|Permission|Rule $object
     * @return boolean whether the role, permission or rule is successfully removed
     */
    public function remove($object);

    /**
     * 更新系统中指定的角色、权限或规则。
     * Updates the specified role, permission or rule in the system.
     * @param string $name the old name of the role, permission or rule
     * @param Role|Permission|Rule $object
     * @return boolean whether the update is successful
     * @throws \Exception if data validation or saving fails (such as the name of the role or permission is not unique)
     */
    public function update($name, $object);

    /**
     * 返回指定的角色
     * Returns the named role.
     * @param string $name the role name.
     * @return null|Role the role corresponding to the specified name. Null is returned if no such role.
     */
    public function getRole($name);

    /**
     * 返回系统中的所有角色
     * Returns all roles in the system.
     * @return Role[] all roles in the system. The array is indexed by the role names.
     */
    public function getRoles();

    /**
     * 返回通过[[assign()]]分配给用户的角色
     * Returns the roles that are assigned to the user via [[assign()]].
     * Note that child roles that are not assigned directly to the user will not be returned.
     * @param string|integer $userId the user ID (see [[\yii\web\User::id]])
     * @return Role[] all roles directly assigned to the user. The array is indexed by the role names.
     */
    public function getRolesByUser($userId);

    /**
     * 返回指定的权限
     * Returns the named permission.
     * @param string $name the permission name.
     * @return null|Permission the permission corresponding to the specified name. Null is returned if no such permission.
     */
    public function getPermission($name);

    /**
     * 返回系统中的所有权限
     * Returns all permissions in the system.
     * @return Permission[] all permissions in the system. The array is indexed by the permission names.
     */
    public function getPermissions();

    /**
     * 返回指定角色包含的所有权限
     * Returns all permissions that the specified role represents.
     * @param string $roleName the role name
     * @return Permission[] all permissions that the role represents. The array is indexed by the permission names.
     */
    public function getPermissionsByRole($roleName);

    /**
     * 返回用户拥有的所有权限
     * Returns all permissions that the user has.
     * @param string|integer $userId the user ID (see [[\yii\web\User::id]])
     * @return Permission[] all permissions that the user has. The array is indexed by the permission names.
     */
    public function getPermissionsByUser($userId);

    /**
     * 返回指定名称的规则。
     * Returns the rule of the specified name.
     * @param string $name the rule name
     * @return null|Rule the rule object, or null if the specified name does not correspond to a rule.
     */
    public function getRule($name);

    /**
     * 返回系统中所有可用的规则
     * Returns all rules available in the system.
     * @return Rule[] the rules indexed by the rule names
     */
    public function getRules();

    /**
     * 检查指定子元素是否可以添加子到指定父元素
     * Checks the possibility of adding a child to parent
     * @param Item $parent the parent item
     * @param Item $child the child item to be added to the hierarchy
     * @return boolean possibility of adding
     *
     * @since 2.0.8
     */
    public function canAddChild($parent, $child);

    /**
     * 添加一个授权项作为另一个授权项的子元素。
     * Adds an item as a child of another item.
     * @param Item $parent
     * @param Item $child
     * @return boolean whether the child successfully added
     * @throws \yii\base\Exception if the parent-child relationship already exists or if a loop has been detected.
     */
    public function addChild($parent, $child);

    /**
     * 从父类中删除一个子元素。
     * 注意，子项并没有被删除。只有父-子关系被删除。
     * Removes a child from its parent.
     * Note, the child item is not deleted. Only the parent-child relationship is removed.
     * @param Item $parent
     * @param Item $child
     * @return boolean whether the removal is successful
     */
    public function removeChild($parent, $child);

    /**
     * 删除父元素的所有子元素
     * 注意，子项并没有被删除。只有父-子关系被删除。
     * Removed all children form their parent.
     * Note, the children items are not deleted. Only the parent-child relationships are removed.
     * @param Item $parent
     * @return boolean whether the removal is successful
     */
    public function removeChildren($parent);

    /**
     * 返回一个值，表明这个子权限是否已经存在于父权限中
     * Returns a value indicating whether the child already exists for the parent.
     * @param Item $parent
     * @param Item $child
     * @return boolean whether `$child` is already a child of `$parent`
     */
    public function hasChild($parent, $child);

    /**
     * 返回子权限和/或角色。
     * Returns the child permissions and/or roles.
     * @param string $name the parent name
     * @return Item[] the child permissions and/or roles
     */
    public function getChildren($name);

    /**
     * 给用户分配一个角色
     * Assigns a role to a user.
     *
     * @param Role $role
     * @param string|integer $userId the user ID (see [[\yii\web\User::id]])
     * @return Assignment the role assignment information.
     * @throws \Exception if the role has already been assigned to the user
     */
    public function assign($role, $userId);

    /**
     * 为用户删除指定角色
     * Revokes a role from a user.
     * @param Role $role
     * @param string|integer $userId the user ID (see [[\yii\web\User::id]])
     * @return boolean whether the revoking is successful
     */
    public function revoke($role, $userId);

    /**
     * 删除用户的所有角色
     * Revokes all roles from a user.
     * @param mixed $userId the user ID (see [[\yii\web\User::id]])
     * @return boolean whether the revoking is successful
     */
    public function revokeAll($userId);

    /**
     * 返回角色和用户的分配关系信息
     * Returns the assignment information regarding a role and a user.
     * @param string $roleName the role name
     * @param string|integer $userId the user ID (see [[\yii\web\User::id]])
     * @return null|Assignment the assignment information. Null is returned if
     * the role is not assigned to the user.
     */
    public function getAssignment($roleName, $userId);

    /**
     * 返回指定用户的所有角色分配信息
     * Returns all role assignment information for the specified user.
     * @param string|integer $userId the user ID (see [[\yii\web\User::id]])
     * @return Assignment[] the assignments indexed by role names. An empty array will be
     * returned if there is no role assigned to the user.
     */
    public function getAssignments($userId);

    /**
     * 返回拥有指定角色的所有用户id
     * Returns all user IDs assigned to the role specified.
     * @param string $roleName
     * @return array array of user ID strings
     * @since 2.0.7
     */
    public function getUserIdsByRole($roleName);

    /**
     * 删除所有授权数据，包括角色、权限、规则和分配关系
     * Removes all authorization data, including roles, permissions, rules, and assignments.
     */
    public function removeAll();

    /**
     * 删除所有权限
     * 所有的父子关系都将相应调整
     * Removes all permissions.
     * All parent child relations will be adjusted accordingly.
     */
    public function removeAllPermissions();

    /**
     * 删除所有角色。
     * 所有的父子关系都将相应调整
     * Removes all roles.
     * All parent child relations will be adjusted accordingly.
     */
    public function removeAllRoles();

    /**
     * 删除所有规则。
     * 所有使用该规则的角色和权限都将相应调整
     * Removes all rules.
     * All roles and permissions which have rules will be adjusted accordingly.
     */
    public function removeAllRules();

    /**
     * 删除所有角色分配
     * Removes all role assignments.
     */
    public function removeAllAssignments();
}
