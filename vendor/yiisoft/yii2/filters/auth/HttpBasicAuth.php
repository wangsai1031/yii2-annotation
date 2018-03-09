<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\filters\auth;

/**
 * HttpBasicAuth 是一个支持HTTP Basic身份验证方法的操作过滤器
 * HttpBasicAuth is an action filter that supports the HTTP Basic authentication method.
 *
 * You may use HttpBasicAuth by attaching it as a behavior to a controller or module, like the following:
 *
 * ```php
 * public function behaviors()
 * {
 *     return [
 *         'basicAuth' => [
 *             'class' => \yii\filters\auth\HttpBasicAuth::className(),
 *         ],
 *     ];
 * }
 * ```
 * HttpBasicAuth的默认实现使用了`user`应用程序组件的[[\yii\web\User::loginByAccessToken()|loginByAccessToken()]]方法并且只传递用户名
 * The default implementation of HttpBasicAuth uses the [[\yii\web\User::loginByAccessToken()|loginByAccessToken()]] method of the `user` application component and only passes the user name.
 * This implementation is used for authenticating API clients.
 *
 * The default implementation of HttpBasicAuth uses the [[\yii\web\User::loginByAccessToken()|loginByAccessToken()]]
 * method of the `user` application component and only passes the user name. This implementation is used
 * for authenticating API clients.
 *
 * 如果您想使用用户名和密码对用户进行身份验证，那么应该按照如下方法设置[[auth]]属性
 * If you want to authenticate users using username and password, you should provide the [[auth]] function for example like the following:
 *
 * ```php
 * public function behaviors()
 * {
 *     return [
 *         'basicAuth' => [
 *             'class' => \yii\filters\auth\HttpBasicAuth::className(),
 *             'auth' => function ($username, $password) {
 *                 $user = User::find()->where(['username' => $username])->one();
 *                 if ($user->verifyPassword($password)) {
 *                     return $user;
 *                 }
 *                 return null;
 *             },
 *         ],
 *     ];
 * }
 * ```
 *
 * > Tip: In case authentication does not work like expected, make sure your web server passes
 * username and password to `$_SERVER['PHP_AUTH_USER']` and `$_SERVER['PHP_AUTH_PW']` variables.
 * If you are using Apache with PHP-CGI, you might need to add this line to your `.htaccess` file:
 * ```
 * RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization},L]
 * ```
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class HttpBasicAuth extends AuthMethod
{
    /**
     * HTTP身份验证realm
     * @var string the HTTP authentication realm
     */
    public $realm = 'api';
    /**
     * 使用HTTP基本身份验证信息对用户进行身份验证的PHP回调函数。
     * 回调函数接收用户名和密码作为参数。它应该返回一个匹配用户名和密码的用户身份对象。
     * 如果没有这个用户，就应该返回Null。
     * @var callable a PHP callable that will authenticate the user with the HTTP basic auth information.
     * The callable receives a username and a password as its parameters. It should return an identity object
     * that matches the username and password. Null should be returned if there is no such identity.
     * The callable will be called only if current user is not authenticated.
     *
     * 下面的代码是这个回调函数的典型实现
     * The following code is a typical implementation of this callable:
     *
     * ```php
     * function ($username, $password) {
     *     return \app\models\User::findOne([
     *         'username' => $username,
     *         'password' => $password,
     *     ]);
     * }
     * ```
     *
     * 如果没有设置此属性，则用户名信息将被视为访问令牌（AccessToken），而密码信息将被忽略。
     * [[\yii\web\User::loginByAccessToken()]]方法将被调用，来验证和登录用户。
     * If this property is not set, the username information will be considered as an access token
     * while the password information will be ignored. The [[\yii\web\User::loginByAccessToken()]]
     * method will be called to authenticate and login the user.
     */
    public $auth;


    /**
     * {@inheritdoc}
     */
    public function authenticate($user, $request, $response)
    {
        // 通过HTTP认证发送的用户名，如果用户名没有被发送，将返回null
        // 通过HTTP认证发送的密码，如果没有给出密码，则返回null
        list($username, $password) = $request->getAuthCredentials();

        if ($this->auth) {
            // 如果自定义验证方法，使用自定义方法验证登录
            if ($username !== null || $password !== null) {
                $identity = $user->getIdentity() ?: call_user_func($this->auth, $username, $password);

                if ($identity === null) {
                    $this->handleFailure($response);
                } elseif ($user->getIdentity(false) !== $identity) {
                    $user->switchIdentity($identity);
                }

                return $identity;
            }
        } elseif ($username !== null) {
            // 通过Access Token 验证登录
            $identity = $user->loginByAccessToken($username, get_class($this));
            if ($identity === null) {
                $this->handleFailure($response);
            }

            return $identity;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function challenge($response)
    {
        $response->getHeaders()->set('WWW-Authenticate', "Basic realm=\"{$this->realm}\"");
    }
}
