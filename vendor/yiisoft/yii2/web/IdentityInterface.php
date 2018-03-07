<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\web;

/**
 * IdentityInterface is the interface that should be implemented by a class providing identity information.
 * 认证接口，应该被提供身份信息的类实现
 *
 * This interface can typically be implemented by a user model class. For example, the following
 * code shows how to implement this interface by a User ActiveRecord class:
 * 这个接口通常可以被用户模型类实现。
 *
 * 用不到的方法可以空着，例如，你的项目只是一个 无状态的 RESTful 应用，
 * 只需实现 yii\web\IdentityInterface::findIdentityByAccessToken()
 * 和 yii\web\IdentityInterface::getId() ，其他的方法的函数体留空即可。
 *
 * ```php
 * class User extends ActiveRecord implements IdentityInterface
 * {
 *      public static function tableName()
 *      {
 *          return 'user';
 *      }
 *
 *
 *     根据给到的ID查询身份。
 *     @param string|integer $id 被查询的ID
 *     @return IdentityInterface|null 通过ID匹配到的身份对象
 *
 *     public static function findIdentity($id)
 *     {
 *         return static::findOne($id);
 *     }
 *
 *     根据 token 查询身份。
 *     @param string $token 被查询的 token
 *     @return IdentityInterface|null 通过 token 得到的身份对象
 *
 *     public static function findIdentityByAccessToken($token, $type = null)
 *     {
 *         return static::findOne(['access_token' => $token]);
 *     }
 *
 *     @return int|string 当前用户ID
 *
 *     public function getId()
 *     {
 *         return $this->id;
 *     }
 *
 *     @return string 当前用户的（cookie）认证密钥
 *
 *     public function getAuthKey()
 *     {
 *         return $this->authKey;
 *     }
 *
 *     public function validateAuthKey($authKey)
 *     {
 *         return $this->authKey === $authKey;
 *     }
 *
 *
 *     如上所述，如果你的应用利用 cookie 登录，
 *     你只需要实现 getAuthKey() 和 validateAuthKey() 方法。
 *     这样的话，你可以使用下面的代码在 user 表中生成和存储每个用户的认证密钥。
 *
 *      public function beforeSave($insert)
        {
            if (parent::beforeSave($insert)) {
                if ($this->isNewRecord) {
                    $this->auth_key = \Yii::$app->security->generateRandomString();
                }
                return true;
            }
            return false;
        }
 * }
 * ```
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
interface IdentityInterface
{
    /**
     * Finds an identity by the given ID.
     * @param string|integer $id the ID to be looked for
     * @return IdentityInterface the identity object that matches the given ID.
     * Null should be returned if such an identity cannot be found
     * or the identity is not in an active state (disabled, deleted, etc.)
     *
     * 根据指定的用户ID查找 认证模型类的实例，当你需要使用session来维持登录状态的时候会用到这个方法。
     * 返回 null 的话 通常是因为未找到该用户，或者用户处在非活跃状态（禁用。冻结）
     */
    public static function findIdentity($id);

    /**
     * Finds an identity by the given token.
     * @param mixed $token the token to be looked for
     * 令牌类型。此参数的值取决于方法实现。
     * 例如，[[\yii\filters\auth\HttpBearerAuth]]将把这个参数设置为`yii\filters\auth\HttpBearerAuth`。
     * @param mixed $type the type of the token. The value of this parameter depends on the implementation.
     * For example, [[\yii\filters\auth\HttpBearerAuth]] will set this parameter to be `yii\filters\auth\HttpBearerAuth`.
     * 返回与给定令牌匹配的身份对象。
     * 如果无法找到该用户，或者用户不处于活动状态(禁用、删除等)，则应该返回Null。
     * @return IdentityInterface the identity object that matches the given token.
     * Null should be returned if such an identity cannot be found or the identity is not in an active state (disabled, deleted, etc.)
     *
     * 根据指定的存取令牌查找 用户身份认证模型类的实例，该方法用于 通过单个加密令牌认证用户的时候（比如无状态的RESTful应用）。
     */
    public static function findIdentityByAccessToken($token, $type = null);

    /**
     * Returns an ID that can uniquely identify a user identity.
     * @return string|integer an ID that uniquely identifies a user identity.
     *
     * 获取该认证实例表示的用户的ID。
     */
    public function getId();

    /**
     * Returns a key that can be used to check the validity of a given identity ID.
     *
     * The key should be unique for each individual user, and should be persistent
     * so that it can be used to check the validity of the user identity.
     *
     * The space of such keys should be big enough to defeat potential identity attacks.
     *
     * This is required if [[User::enableAutoLogin]] is enabled.
     * @return string a key that is used to check the validity of a given identity ID.
     * @see validateAuthKey()
     *
     * 获取基于 cookie 登录时使用的认证密钥。 认证密钥储存在 cookie 里并且将来会与服务端的版本进行比较以确保 cookie的有效性。
     */
    public function getAuthKey();

    /**
     * Validates the given auth key.
     *
     * This is required if [[User::enableAutoLogin]] is enabled.
     * @param string $authKey the given auth key
     * @return boolean whether the given auth key is valid.
     * @see getAuthKey()
     *
     * 是基于 cookie 登录密钥的 验证的逻辑的实现。
     */
    public function validateAuthKey($authKey);
}
