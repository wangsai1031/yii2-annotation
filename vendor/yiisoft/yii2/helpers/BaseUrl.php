<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\helpers;

use Yii;
use yii\base\InvalidArgumentException;

/**
 * BaseUrl provides concrete implementation for [[Url]].
 *
 * Do not use BaseUrl. Use [[Url]] instead.
 *
 * @author Alexander Makarov <sam@rmcreative.ru>
 * @since 2.0
 */
class BaseUrl
{
    /**
     * 用于创建URL的URL管理器
     * @var \yii\web\UrlManager URL manager to use for creating URLs
     * @since 2.0.8
     */
    public static $urlManager;


    /**
     * 为给定的路由创建URL
     * Creates a URL for the given route.
     *
     * 这个方法将使用[[\yii\web\UrlManager]]创建一个URL
     * This method will use [[\yii\web\UrlManager]] to create a URL.
     *
     * 您可以将该路径指定为字符串，`site/index`
     * 也可以使用数组创建附加查询参数的URL。
     * You may specify the route as a string, e.g., `site/index`. You may also use an array
     * if you want to specify additional query parameters for the URL being created. The
     * array format must be:
     *
     * ```php
     * // generates: /index.php?r=site/index&param1=value1&param2=value2
     * ['site/index', 'param1' => 'value1', 'param2' => 'value2']
     * ```
     *
     * 如果您想要创建一个带有锚的URL，您可以使用带有参数 ‘#’ 的数组格式
     * If you want to create a URL with an anchor, you can use the array format with a `#` parameter.
     * For example,
     *
     * ```php
     * // generates: /index.php?r=site/index&param1=value1#name
     * ['site/index', 'param1' => 'value1', '#' => 'name']
     * ```
     *
     * 一个路由既可能是绝对的又可能是相对的。
     * 一个绝对的路由以前导斜杠开头（如： /site/index），
     * 而一个相对的路由则没有（比如： site/index 或者 index）。
     *
     * A route may be either absolute or relative. An absolute route has a leading slash (e.g. `/site/index`),
     * while a relative route has none (e.g. `site/index` or `index`). A relative route will be converted
     * into an absolute one by the following rules:
     *
     * 相对路线将被按照以下规则转换为绝对的路线
     * A relative route will be converted into an absolute one by the following rules:
     *
     * - 如果这个路由是一个空的字符串，将会使用当前 @see \yii\web\Controller::route 作为路由
     * - 如果这个路由不带任何斜杠（比如 index ），它会被认为是当前控制器的一个 action ID，
     *   然后将会把 @see \yii\web\Controller::uniqueId 插入到路由前面.
     * - 如果这个路由不带前导斜杠（比如： site/index ），它会被认为是相对当前模块（module）的路由，
     *   然后将会把 @see \yii\base\Module::uniqueId 插入到路由前面。
     *
     * - If the route is an empty string, the current [[\yii\web\Controller::route|route]] will be used;
     * - If the route contains no slashes at all (e.g. `index`), it is considered to be an action ID
     *   of the current controller and will be prepended with [[\yii\web\Controller::uniqueId]];
     * - If the route has no leading slash (e.g. `site/index`), it is considered to be a route relative
     *   to the current module and will be prepended with the module's [[\yii\base\Module::uniqueId|uniqueId]].
     *
     * 从2.0.2版本开始，也可以将路由指定为别名
     * Starting from version 2.0.2, a route can also be specified as an alias. In this case, the alias
     * will be converted into the actual route first before conducting the above transformation steps.
     *
     * 下面是使用该方法的一些示例
     * Below are some examples of using this method:
     *
     * ```php
     * // /index.php?r=site%2Findex
     * echo Url::toRoute('site/index');
     *
     * // /index.php?r=site%2Findex&src=ref1#name
     * echo Url::toRoute(['site/index', 'src' => 'ref1', '#' => 'name']);
     *
     * // http://www.example.com/index.php?r=site%2Findex
     * echo Url::toRoute('site/index', true);
     *
     * // https://www.example.com/index.php?r=site%2Findex
     * echo Url::toRoute('site/index', 'https');
     *
     * // /index.php?r=post%2Findex     assume the alias "@posts" is defined as "post/index"
     * echo Url::toRoute('@posts');
     * ```
     *
     * 使用字符串来表示路由 或者 数组来表示一个带查询参数的路由
     * @param string|array $route use a string to represent a route (e.g. `index`, `site/index`),
     * or an array to represent a route with query parameters (e.g. `['site/index', 'param1' => 'value1']`).
     * 
     * 生成Url时使用的方案
     * @param bool|string $scheme the URI scheme to use in the generated URL:
     *
     * 生成相对URL
     * - `false` (default): generating a relative URL.
     * 返回一个其方案与[[\yii\web\UrlManager::hostInfo]]相同的绝对的基本URL
     * - `true`: returning an absolute base URL whose scheme is the same as that in [[\yii\web\UrlManager::$hostInfo]].
     * 使用指定的方案生成一个绝对URL，`http` or `https`
     * - string: generating an absolute URL with the specified scheme (either `http`, `https` or empty string
     *   for protocol-relative URL).
     *
     * @return string the generated URL
     * @throws InvalidArgumentException a relative route is given while there is no active controller
     */
    public static function toRoute($route, $scheme = false)
    {
        $route = (array) $route;
        // 返回一个左侧不包含 '/' 的绝对路由。
        // 规范化路由，使之适和UrlManager
        $route[0] = static::normalizeRoute($route[0]);

        if ($scheme !== false) {
            // 创建一个绝对URL
            return static::getUrlManager()->createAbsoluteUrl($route, is_string($scheme) ? $scheme : null);
        }

        //　创建一个相对Url
        return static::getUrlManager()->createUrl($route);
    }

    /**
     * 返回一个左侧不包含 '/' 的绝对路由
     * 
     * Normalizes route and makes it suitable for UrlManager. Absolute routes are staying as is
     * while relative routes are converted to absolute ones.
     *
     * 规范化路由，使之适和UrlManager
     * Normalizes route and makes it suitable for UrlManager.
     * 绝对路径保持原样，而相对路径则转变为绝对路径
     * Absolute routes are staying as is while relative routes are converted to absolute ones.
     *
     * 相对路径是指不是以斜线开头的路径
     * A relative route is a route without a leading slash, such as "view", "post/view".
     *
     * 如果路径是空字符串，会使用当前的路径
     * 如果路径不包含斜杠(e.g. `index`)，则他将会被视为当前控制器的action,并且将使用[[\yii\web\Controller::uniqueId]]前缀
     * @see \yii\web\Controller::uniqueId
     * 如果路径没有以斜杠开头(e.g. `site/index`)，则他将会被视为当前模块的相对路径
     * @see \yii\base\Module::uniqueId
     *
     * - If the route is an empty string, the current [[\yii\web\Controller::route|route]] will be used;
     * - If the route contains no slashes at all, it is considered to be an action ID
     *   of the current controller and will be prepended with [[\yii\web\Controller::uniqueId]];
     * - If the route has no leading slash, it is considered to be a route relative
     *   to the current module and will be prepended with the module's uniqueId.
     *
     * 从2.0.2版本开始，也可以将路由指定为别名
     * Starting from version 2.0.2, a route can also be specified as an alias. In this case, the alias
     * will be converted into the actual route first before conducting the above transformation steps.
     *
     * @param string $route the route. This can be either an absolute route or a relative route.
     * @return string normalized route suitable for UrlManager
     * @throws InvalidArgumentException a relative route is given while there is no active controller
     */
    protected static function normalizeRoute($route)
    {
        // 如果是别名，则返回别名，如果不是别名，则直接返回原字符串
        $route = Yii::getAlias((string) $route);
        /**
         * strncmp， 二进制安全方式比较前N个字符
         * 判断第一个字符是否为 '/',若是，则为绝对路径，去掉最左侧的'/'并返回
         */
        if (strncmp($route, '/', 1) === 0) {
            // 绝对路径
            // absolute route
            return ltrim($route, '/');
        }

        // relative route
        // 相对路径，若没有加载控制器，则报错
        if (Yii::$app->controller === null) {
            throw new InvalidArgumentException("Unable to resolve the relative route: $route. No active controller is available.");
        }

        // 查看字符串中是否包含 '/',若不包含则为actionID 或空
        if (strpos($route, '/') === false) {
            // empty or an action ID
            // 若为空字符串，则直接返回当前路由，否则与控制器ID进行拼接
            return $route === '' ? Yii::$app->controller->getRoute() : Yii::$app->controller->getUniqueId() . '/' . $route;
        }

        // 若包含'/',则是相对于模块的路径
        // relative to module
        return ltrim(Yii::$app->controller->module->getUniqueId() . '/' . $route, '/');
    }

    /**
     * 根据给定的参数创建一个URL
     * Creates a URL based on the given parameters.
     *
     * 这个方法和 toRoute() 非常相似。 唯一的区别是这个方法要求一个路由必须用数组来指定。
     * 如果传的参数为字符串，它将会被直接当做 URL 。
     * This method is very similar to [[toRoute()]]. The only difference is that this method
     * requires a route to be specified as an array only. If a string is given, it will be treated as a URL.
     * In particular, if `$url` is
     *
     * - 数组：将会调用 toRoute() 来生成URL。比如： ['site/index'], ['post/index', 'page' => 2] 。 详细用法请参考 toRoute() 。
     * - 带前导 @ 的字符串：它将会被当做别名， 对应的别名字符串将会返回。
     * - 空的字符串：当前请求的 URL 将会被返回；
     * - 普通的字符串：返回本身。
     *
     * - an array: [[toRoute()]] will be called to generate the URL. For example:
     *   `['site/index']`, `['post/index', 'page' => 2]`. Please refer to [[toRoute()]] for more details
     *   on how to specify a route.
     * - a string with a leading `@`: it is treated as an alias, and the corresponding aliased string
     *   will be returned.
     * - an empty string: the currently requested URL will be returned;
     * - a normal string: it will be returned as is.
     *
     * 当 $scheme 指定了（无论是字符串还是 true ），
     * 一个带主机信息（通过 @see \yii\web\UrlManager::hostInfo 获得） 的绝对 URL 将会被返回。
     * 如果 $url 已经是绝对 URL 了， 它的协议信息将会被替换为指定的（ https 或者 http ）。
     * 
     * When `$scheme` is specified (either a string or `true`), an absolute URL with host info (obtained from
     * [[\yii\web\UrlManager::$hostInfo]]) will be returned. If `$url` is already an absolute URL, its scheme
     * will be replaced with the specified one.
     *
     * Below are some examples of using this method:
     * 下面是使用该方法的一些示例
     *
     * ```php
     * // /index.php?r=site%2Findex
     * echo Url::to(['site/index']);
     *
     * // /index.php?r=site%2Findex&src=ref1#name
     * echo Url::to(['site/index', 'src' => 'ref1', '#' => 'name']);
     *
     * // /index.php?r=post%2Findex     assume the alias "@posts" is defined as "/post/index"
     * echo Url::to(['@posts']);
     *
     * // the currently requested URL
     * echo Url::to();
     *
     * // /images/logo.gif
     * echo Url::to('@web/images/logo.gif');
     *
     * // images/logo.gif
     * echo Url::to('images/logo.gif');
     *
     * // http://www.example.com/images/logo.gif
     * echo Url::to('@web/images/logo.gif', true);
     *
     * // https://www.example.com/images/logo.gif
     * echo Url::to('@web/images/logo.gif', 'https');
     *
     * // //www.example.com/images/logo.gif
     * echo Url::to('@web/images/logo.gif', '');
     * ```
     *
     *
     * @param array|string $url the parameter to be used to generate a valid URL
     * @param bool|string $scheme the URI scheme to use in the generated URL:
     *
     * 如果没有传任何参数，这个方法将会生成相对 URL 。
     * 你可以传 true 来获得一个针对当前协议的绝对 URL；
     * 或者，你可以明确的指定具体的协议类型（ https , http ）
     * - `false` (default): generating a relative URL.
     * - `true`: returning an absolute base URL whose scheme is the same as that in [[\yii\web\UrlManager::$hostInfo]].
     * - string: generating an absolute URL with the specified scheme (either `http`, `https` or empty string
     *   for protocol-relative URL).
     *
     * @return string the generated URL
     * @throws InvalidArgumentException a relative route is given while there is no active controller
     */
    public static function to($url = '', $scheme = false)
    {
        //如果是数组，则直接交给 toRoute() 解决
        if (is_array($url)) {
            return static::toRoute($url, $scheme);
        }

        // 获取别名
        $url = Yii::getAlias($url);
        if ($url === '') {
            // 如果是空字符串，则指定为当前请求的相对URL
            $url = Yii::$app->getRequest()->getUrl();
        }

        // 如果没有指定 http协议,则直接返回该URL
        if ($scheme === false) {
            return $url;
        }

        if (static::isRelative($url)) {
            // turn relative URL into absolute
            $url = static::getUrlManager()->getHostInfo() . '/' . ltrim($url, '/');
        }

        return static::ensureScheme($url, $scheme);
    }

    /**
     * Normalize URL by ensuring that it use specified scheme.
     *
     * If URL is relative or scheme is not string, normalization is skipped.
     *
     * @param string $url the URL to process
     * @param string $scheme the URI scheme used in URL (e.g. `http` or `https`). Use empty string to
     * create protocol-relative URL (e.g. `//example.com/path`)
     * @return string the processed URL
     * @since 2.0.11
     */
    public static function ensureScheme($url, $scheme)
    {
        if (static::isRelative($url) || !is_string($scheme)) {
            return $url;
        }

        // 如果url 以 // 开头e.g. //hostname/path/to/resource
        if (substr($url, 0, 2) === '//') {
            // e.g. //example.com/path/to/resource
            // 若$scheme 是 http 或 https，则拼接到 $url 前面，否则直接返回该url
            return $scheme === '' ? $url : "$scheme:$url";
        }

        // 如果协议存在
        if (($pos = strpos($url, '://')) !== false) {
            if ($scheme === '') {
                $url = substr($url, $pos + 1);
            } else {
                // 使用指定的协议替换掉$url 的协议
                // replace the scheme with the specified one
                $url = $scheme . substr($url, $pos);
            }
        }

        return $url;
    }

    /**
     * 返回当前请求的基本URL
     * Returns the base URL of the current request.
     * @param bool|string $scheme the URI scheme to use in the returned base URL:
     *
     * 返回基本URL，没有主机信息
     * - `false` (default): returning the base URL without host info.
     * 返回一个绝对的基本URL,其协议与 \yii\web\UrlManager::hostInfo 相同
     * - `true`: returning an absolute base URL whose scheme is the same as that in [[\yii\web\UrlManager::$hostInfo]].
     * 使用指定的协议返回一个绝对的基本URL
     * - string: returning an absolute base URL with the specified scheme (either `http`, `https` or empty string
     *   for protocol-relative URL).
     * @return string
     */
    public static function base($scheme = false)
    {
        $url = static::getUrlManager()->getBaseUrl();
        if ($scheme !== false) {
            $url = static::getUrlManager()->getHostInfo() . $url;
            $url = static::ensureScheme($url, $scheme);
        }

        return $url;
    }

    /**
     * 记住指定的URL，以便以后可以被[[previous()]]取回
     * Remembers the specified URL so that it can be later fetched back by [[previous()]].
     *
     * 要记住的URL，请参阅[[to()]]可接受的格式。
     * 如果没有指定该参数，则将使用当前请求的URL
     * @param string|array $url the URL to remember. Please refer to [[to()]] for acceptable formats.
     * If this parameter is not specified, the currently requested URL will be used.
     * 与要记住的URL相关联的名称，这个名称将会被[[previous()]]使用，如果没有设置，他将使用[[@see \yii\web\User::returnUrlParam]]
     * @param string $name the name associated with the URL to be remembered. This can be used
     * later by [[previous()]]. If not set, [[\yii\web\User::setReturnUrl()]] will be used with passed URL.
     * @see previous()
     * @see \yii\web\User::setReturnUrl()
     */
    public static function remember($url = '', $name = null)
    {
        // 根据给定的参数创建一个URL
        $url = static::to($url);

        if ($name === null) {
            // 如果没有设置名称，则默认使用 @see \yii\web\User::returnUrlParam 作为名称
            Yii::$app->getUser()->setReturnUrl($url);
        } else {
            // 如果没有设置名称，则直接使用该名称，并将URL存放到session中
            Yii::$app->getSession()->set($name, $url);
        }
    }

    /**
     * 返回之前remember()记住的url
     * Returns the URL previously [[remember()|remembered]].
     *
     * 与要记住的URL相关联的名称，这个名称将会被[[previous()]]使用，如果没有设置，他将使用[[@see \yii\web\User::returnUrlParam]]
     * @param string $name the named associated with the URL that was remembered previously.
     * If not set, [[\yii\web\User::getReturnUrl()]] will be used to obtain remembered URL.
     * 返回先前记住的URL。如果通过给定的名称没有找到对应的URL,则返回Null.
     * @return string|null the URL previously remembered. Null is returned if no URL was remembered with the given name
     * and `$name` is not specified.
     * @see remember()
     * @see \yii\web\User::getReturnUrl()
     */
    public static function previous($name = null)
    {
        if ($name === null) {
            return Yii::$app->getUser()->getReturnUrl();
        }

        return Yii::$app->getSession()->get($name);
    }

    /**
     * 返回当前请求页面的规范URL
     * Returns the canonical URL of the currently requested page.
     *
     * 规范化URL是构造一个使用当前控制器的[[\yii\web\Controller::route]]和[[\yii\web\Controller::actionParams]]，
     * 你可以在layout视图中使用以下代码添加一个链接标签
     * The canonical URL is constructed using the current controller's [[\yii\web\Controller::route]] and
     * [[\yii\web\Controller::actionParams]]. You may use the following code in the layout view to add a link tag
     * about canonical URL:
     *
     * ```php
     * $this->registerLinkTag(['rel' => 'canonical', 'href' => Url::canonical()]);
     * ```
     *
     * @return string the canonical URL of the currently requested page
     */
    public static function canonical()
    {
        $params = Yii::$app->controller->actionParams;
        $params[0] = Yii::$app->controller->getRoute();

        return static::getUrlManager()->createAbsoluteUrl($params);
    }

    /**
     * 返回网站首页URL
     * Returns the home URL.
     * 用于返回的URL的URI协议
     *
     * @param bool|string $scheme the URI scheme to use for the returned URL:
     *
     * 如果没有传任何参数，这个方法将会生成相对 URL 。
     * 你可以传 true 来获得一个针对当前协议的绝对 URL；
     * 或者，你可以明确的指定具体的协议类型（ https , http ）。
     * - `false` (default): returning a relative URL.
     * - `true`: returning an absolute base URL whose scheme is the same as that in [[\yii\web\UrlManager::$hostInfo]].
     * - string: returning an absolute URL with the specified scheme (either `http`, `https` or empty string
     *   for protocol-relative URL).
     *
     * @return string home URL
     */
    public static function home($scheme = false)
    {
        $url = Yii::$app->getHomeUrl();

        if ($scheme !== false) {
            // 将网站协议和主机地址拼接到相对URL前面
            $url = static::getUrlManager()->getHostInfo() . $url;
            $url = static::ensureScheme($url, $scheme);
        }

        return $url;
    }

    /**
     * 检查一个URL是否是相对的
     * Returns a value indicating whether a URL is relative.
     * 一个相对URL没有主机信息部分
     * A relative URL does not have host info part.
     * @param string $url the URL to be checked
     * @return bool whether the URL is relative
     */
    public static function isRelative($url)
    {
        return strncmp($url, '//', 2) && strpos($url, '://') === false;
    }

    /**
     * 通过使用当前路由和GET参数创建一个URL
     * Creates a URL by using the current route and the GET parameters.
     *
     * 你可以通过传递一个 $params 给这个方法来添加或者删除 GET 参数.
     * 如果你将某个参数设为空，这个参数将会被删除。
     * $params中指定的所有其他参数将与现有的GET参数合并
     * You may modify or remove some of the GET parameters, or add additional query parameters through
     * the `$params` parameter. In particular, if you specify a parameter to be null, then this parameter
     * will be removed from the existing GET parameters; all other parameters specified in `$params` will
     * be merged with the existing GET parameters. For example,
     *
     * ```php
     * // assume $_GET = ['id' => 123, 'src' => 'google'], current route is "post/view"
     *
     * // /index.php?r=post%2Fview&id=123&src=google
     * echo Url::current();
     *
     * // /index.php?r=post%2Fview&id=123
     * echo Url::current(['src' => null]);
     *
     * // /index.php?r=post%2Fview&id=100&src=google
     * echo Url::current(['id' => 100]);
     * ```
     *
     * Note that if you're replacing array parameters with `[]` at the end you should specify `$params` as nested arrays.
     * For a `PostSearchForm` model where parameter names are `PostSearchForm[id]` and `PostSearchForm[src]` the syntax
     * would be the following:
     *
     * ```php
     * // index.php?r=post%2Findex&PostSearchForm%5Bid%5D=100&PostSearchForm%5Bsrc%5D=google
     * echo Url::current([
     *     $postSearch->formName() => ['id' => 100, 'src' => 'google'],
     * ]);
     * ```
     *
     * @param array $params an associative array of parameters that will be merged with the current GET parameters.
     * If a parameter value is null, the corresponding GET parameter will be removed.
     * @param bool|string $scheme the URI scheme to use in the generated URL:
     *
     * - `false` (default): generating a relative URL.
     * - `true`: returning an absolute base URL whose scheme is the same as that in [[\yii\web\UrlManager::$hostInfo]].
     * - string: generating an absolute URL with the specified scheme (either `http`, `https` or empty string
     *   for protocol-relative URL).
     *
     * @return string the generated URL
     * @since 2.0.3
     */
    public static function current(array $params = [], $scheme = false)
    {
        $currentParams = Yii::$app->getRequest()->getQueryParams();
        $currentParams[0] = '/' . Yii::$app->controller->getRoute();
        $route = array_replace_recursive($currentParams, $params);
        return static::toRoute($route, $scheme);
    }

    /**
     * 用于创建URL的URL管理器
     * @return \yii\web\UrlManager URL manager used to create URLs
     * @since 2.0.8
     */
    protected static function getUrlManager()
    {
        return static::$urlManager ?: Yii::$app->getUrlManager();
    }
}
