<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\web;

use Yii;
use yii\base\InvalidRouteException;
use yii\helpers\Url;

/**
 * Application is the base class for all web application classes.
 * Application是所有web应用类的基类
 *
 * For more details and usage information on Application, see the [guide article on applications](guide:structure-applications).
 *
 * @property ErrorHandler $errorHandler The error handler application component. This property is read-only.
 * 属性 $errorHandler 错误处理应用组件。该属性只读
 * @property string $homeUrl The homepage URL.
 * 属性 $homeUrl 首页的链接
 * @property Request $request The request component. This property is read-only.
 * 属性 $request 请求组件 该属性只读
 * @property Response $response The response component. This property is read-only.
 * 属性 $response 响应组件，该属性只读
 * @property Session $session The session component. This property is read-only.
 * 属性 $session 会话组件，该属性只读
 * @property User $user The user component. This property is read-only.
 * 属性 $user 用户组件 该属性只读
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Application extends \yii\base\Application
{
    /**
     * @var string the default route of this application. Defaults to 'site'.
     * 参数 字符串 该应用的默认路由，默认为site
     */
    public $defaultRoute = 'site';
    /**
     * @var array the configuration specifying a controller action which should handle
     * all user requests. This is mainly used when the application is in maintenance mode
     * and needs to handle all incoming requests via a single action.
     * 参数 数组 指定处理所有用户请求的控制器动作的配置项，主要用于处于维护模式并且需要通过一个单独的动作处理所有请求的应用
     * The configuration is an array whose first element specifies the route of the action.
     * The rest of the array elements (key-value pairs) specify the parameters to be bound
     * to the action. For example,
     * 该配置是一个数组，第一个元素指定了动作的路由，剩余的元素（键值对）指定列绑定到该动作的参数，例如：
     *
     * ```php
     * [
     *     'offline/notice',
     *     'param1' => 'value1',
     *     'param2' => 'value2',
     * ]
     * ```
     *
     * Defaults to null, meaning catch-all is not used.
     * 默认是null，意味着不适用catch-all
     */
    public $catchAll;
    /**
     * @var Controller the currently active controller instance
     * 参数 当前激活的控制器实例
     */
    public $controller;


    /**
     * 定义了 @webroot 和 @web 2个别名
     *
     * @webroot 就是入口脚本 index.php 所在的目录
     * 而 @web 则是URL别名，表示当前应用的根URL地址
     *
     * {@inheritdoc}
     */
    protected function bootstrap()
    {
        $request = $this->getRequest();
        Yii::setAlias('@webroot', dirname($request->getScriptFile()));
        Yii::setAlias('@web', $request->getBaseUrl());

        parent::bootstrap();
    }

    /**
     * Handles the specified request.
     * 处理指定的请求
     * @param Request $request the request to be handled
     * 参数 将要被处理的请求
     * @return Response the resulting response
     * 返回值 响应结果
     * @throws NotFoundHttpException if the requested route is invalid
     * 当请求路由不合法的时候抛出，没有找到对应页面异常
     */
    public function handleRequest($request)
    {
        if (empty($this->catchAll)) {
            try {
                //将当前请求解析为一个路由和相关的参数
                list($route, $params) = $request->resolve();
            } catch (UrlNormalizerRedirectException $e) {
                // 捕获异常
                $url = $e->url;
                if (is_array($url)) {
                    if (isset($url[0])) {
                        // ensure the route is absolute
                        // 确保是一个绝对路由
                        $url[0] = '/' . ltrim($url[0], '/');
                    }
                    $url += $request->getQueryParams();
                }

                return $this->getResponse()->redirect(Url::to($url, $e->scheme), $e->statusCode);
            }
        } else {
            $route = $this->catchAll[0];
            $params = $this->catchAll;
            unset($params[0]);
        }
        try {
            Yii::debug("Route requested: '$route'", __METHOD__);
            // 将路由赋值给 requestedRoute当前请求的路由
            $this->requestedRoute = $route;
            //运行控制器中的Action
            $result = $this->runAction($route, $params);
            if ($result instanceof Response) {
                return $result;
            }

            //这个是加载yii\base\Response类，在外部可以
            //Yii::$app->get('response')、
            //Yii::$app->getResponse()、
            //Yii::$app->response
            //等等方式来加载response类，
            //主要用来加载http状态，及头信息，
            //如301,302，404，ajax头等等的获取
            $response = $this->getResponse();
            if ($result !== null) {
                $response->data = $result;
            }

            return $response;
        } catch (InvalidRouteException $e) {
            throw new NotFoundHttpException(Yii::t('yii', 'Page not found.'), $e->getCode(), $e);
        }
    }

    private $_homeUrl;

    /**
     * @return string the homepage URL
     * 返回值 字符串 主页的链接
     */
    public function getHomeUrl()
    {
        // 若已经通过 setHomeUrl($value) 显式地设置了 _homeUrl，则直接返回该 _homeUrl
        if ($this->_homeUrl === null) {
            // 若 链接 保留显示入口脚本，则返回当前入口脚本的相对路径
            if ($this->getUrlManager()->showScriptName) {
                return $this->getRequest()->getScriptUrl();
            }

            // 返回应用程序的相对 URL
            return $this->getRequest()->getBaseUrl() . '/';
        }

        return $this->_homeUrl;
    }

    /**
     * @param string $value the homepage URL
     * 参数 字符串 首页的url
     */
    public function setHomeUrl($value)
    {
        $this->_homeUrl = $value;
    }

    /**
     * Returns the error handler component.
     * 返回错误处理组件
     * @return ErrorHandler the error handler application component.
     * 返回值 错误处理应用组件
     */
    public function getErrorHandler()
    {
        return $this->get('errorHandler');
    }

    /**
     * Returns the request component.
     * 返回请求组件
     * @return Request the request component.
     * 返回值 请求组件
     */
    public function getRequest()
    {
        return $this->get('request');
    }

    /**
     * Returns the response component.
     * 返回响应组件
     * @return Response the response component.
     * 返回值 响应组件
     */
    public function getResponse()
    {
        return $this->get('response');
    }

    /**
     * Returns the session component.
     * 返回session组件
     * @return Session the session component.
     * 返回值 session组件
     */
    public function getSession()
    {
        return $this->get('session');
    }

    /**
     * Returns the user component.
     * 返回用户组件
     * @return User the user component.
     * 返回值 用户组件
     */
    public function getUser()
    {
        return $this->get('user');
    }

    /**
     * Web核心组件
     * {@inheritdoc}
     */
    public function coreComponents()
    {
        return array_merge(parent::coreComponents(), [
            // 请求
            'request' => ['class' => 'yii\web\Request'],
            // 响应
            'response' => ['class' => 'yii\web\Response'],
            // 会话
            'session' => ['class' => 'yii\web\Session'],
            // 用户
            'user' => ['class' => 'yii\web\User'],
            // 错误处理
            'errorHandler' => ['class' => 'yii\web\ErrorHandler'],
        ]);
    }
}
