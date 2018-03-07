<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\filters\auth;

use Yii;
use yii\base\InvalidConfigException;

/**
 * 复合认证是一种支持多种身份验证方法的动作过滤器
 * CompositeAuth is an action filter that supports multiple authentication methods at the same time.
 *
 * 复合认证所包含的认证方法是通过[[authMethods]]配置的,这是一个支持身份验证类的配置的列表
 * The authentication methods contained by CompositeAuth are configured via [[authMethods]],
 * which is a list of supported authentication class configurations.
 *
 * 下面的示例展示了如何支持三种身份验证方法:
 * The following example shows how to support three authentication methods:
 *
 * ```php
 * public function behaviors()
 * {
 *     return [
 *         'compositeAuth' => [
 *             'class' => \yii\filters\auth\CompositeAuth::className(),
 *             'authMethods' => [
 *                  //Http Basic 验证
 *                 \yii\filters\auth\HttpBasicAuth::className(),
 *                  //通过查询参数传递的访问令牌来身份验证
 *                 \yii\filters\auth\QueryParamAuth::className(),
 *             ],
 *         ],
 *     ];
 * }
 * ```
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class CompositeAuth extends AuthMethod
{
    /**
     * 支持的身份验证方法。
     * 该属性应该获取 支持的身份验证方法 的列表，每个元素都由一个身份验证类或配置表示
     * @var array the supported authentication methods. This property should take a list of supported
     * authentication methods, each represented by an authentication class or configuration.
     *
     * 如果该属性为空，则不执行身份验证
     * If this property is empty, no authentication will be performed.
     * 注意，auth方法类必须实现[[\yii\filters\auth\AuthInterface]]接口
     * Note that an auth method class must implement the [[\yii\filters\auth\AuthInterface]] interface.
     */
    public $authMethods = [];


    /**
     * {@inheritdoc}
     */
    public function beforeAction($action)
    {
        // 如果该属性为空，则不执行身份验证
        return empty($this->authMethods) ? true : parent::beforeAction($action);
    }

    /**
     * 身份验证
     * {@inheritdoc}
     */
    public function authenticate($user, $request, $response)
    {
        // 遍历 身份验证方法
        foreach ($this->authMethods as $i => $auth) {
            // 如果身份验证方法非继承自 AuthInterface，则可能是配置
            if (!$auth instanceof AuthInterface) {
                // 创建该身份验证对象
                $this->authMethods[$i] = $auth = Yii::createObject($auth);
                if (!$auth instanceof AuthInterface) {
                    throw new InvalidConfigException(get_class($auth) . ' must implement yii\filters\auth\AuthInterface');
                }
            }

            // 使用创建的对象进行身份验证
            $identity = $auth->authenticate($user, $request, $response);
            if ($identity !== null) {
                // 任意一个验证成功，立即返回用户登录身份信息
                return $identity;
            }
        }

        // 都没有验证成功，则返回null
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function challenge($response)
    {
        foreach ($this->authMethods as $method) {
            /* @var $method AuthInterface */
            $method->challenge($response);
        }
    }
}
