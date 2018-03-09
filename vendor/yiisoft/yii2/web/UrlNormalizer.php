<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\web;

use Yii;
use yii\base\BaseObject;
use yii\base\InvalidConfigException;

/**
 * 为[[UrlManager]] and [[UrlRule]]规范化URL
 * UrlNormalizer normalizes URLs for [[UrlManager]] and [[UrlRule]].
 *
 * @author Robert Korulczyk <robert@korulczyk.pl>
 * @author Cronfy <cronfy@gmail.com>
 * @since 2.0.10
 */
class UrlNormalizer extends BaseObject
{
    /**
     * 表示在路由规范化过程中进行的重定向
     * 301：永久重定向
     * Represents permament redirection during route normalization.
     * @see https://en.wikipedia.org/wiki/HTTP_301
     */
    const ACTION_REDIRECT_PERMANENT = 301;
    /**
     * 表示在路由规范化过程中进行的重定向
     * 302：临时重定向
     * Represents temporary redirection during route normalization.
     * @see https://en.wikipedia.org/wiki/HTTP_302
     */
    const ACTION_REDIRECT_TEMPORARY = 302;
    /**
     * 在路由规范化过程中显示404错误页面
     * Represents showing 404 error page during route normalization.
     * @see https://en.wikipedia.org/wiki/HTTP_404
     */
    const ACTION_NOT_FOUND = 404;

    /**
     * 是否将连续多条斜线折叠为一条，例如`site///index`将被转换为`site/index`
     * @var bool whether slashes should be collapsed, for example `site///index` will be
     * converted into `site/index`
     */
    public $collapseSlashes = true;
    /**
     * 结尾斜杠是否应该按照规则的后缀设置进行规范化
     * @var bool whether trailing slash should be normalized according to the suffix settings
     * of the rule
     */
    public $normalizeTrailingSlash = true;
    /**
     * 在路由规范化过程中执行的操作。
     * @var int|callable|null action to perform during route normalization.
     * 可用的选项有：
     * Available options are:
     *
     * 不需要执行任何特殊的操作
     * - `null` - no special action will be performed
     * 应该使用 永久重定向 将请求 重定向到规范化URL
     * - `301` - the request should be redirected to the normalized URL using
     *   permanent redirection
     * 应该使用 临时重定向 将请求 重定向到规范化URL
     * - `302` - the request should be redirected to the normalized URL using
     *   temporary redirection
     * 将抛出 [[NotFoundHttpException]] 异常
     * - `404` - [[NotFoundHttpException]] will be thrown
     * 用户自定义回调
     * - `callable` - custom user callback, for example:
     *
     *   ```php
     *   function ($route, $normalizer) {
     *       // use custom action for redirections
     *       $route[1]['oldRoute'] = $route[0];
     *       $route[0] = 'site/redirect';
     *       return $route;
     *   }
     *   ```
     */
    public $action = self::ACTION_REDIRECT_PERMANENT;


    /**
     * 为指定的路由$route执行规范化操作
     * Performs normalization action for the specified $route.
     * @param array $route route for normalization
     * @return array normalized route
     * @throws InvalidConfigException if invalid normalization action is used.
     * @throws UrlNormalizerRedirectException if normalization requires redirection.
     * @throws NotFoundHttpException if normalization suggests action matching route does not exist.
     */
    public function normalizeRoute($route)
    {
        if ($this->action === null) {
            // action为空，直接返回路由
            return $route;
        } elseif ($this->action === static::ACTION_REDIRECT_PERMANENT || $this->action === static::ACTION_REDIRECT_TEMPORARY) {
            // 若 action 为 301 或 302，则抛出重定向异常
            throw new UrlNormalizerRedirectException([$route[0]] + $route[1], $this->action);
        } elseif ($this->action === static::ACTION_NOT_FOUND) {
            throw new NotFoundHttpException(Yii::t('yii', 'Page not found.'));
        } elseif (is_callable($this->action)) {
            return call_user_func($this->action, $route, $this);
        }

        throw new InvalidConfigException('Invalid normalizer action.');
    }

    /**
     * 格式化指定的路由
     * Normalizes specified pathInfo.
     *
     * 需要格式化的路由信息
     * @param string $pathInfo pathInfo for normalization
     * 当前规则后缀
     * @param string $suffix current rule suffix
     * 如果指定了该参数，如果$pathInfo在规范化过程中被更改，那么这个变量将被设置为true。
     * @param bool $normalized if specified, this variable will be set to `true` if $pathInfo
     * was changed during normalization
     * @return string normalized pathInfo
     */
    public function normalizePathInfo($pathInfo, $suffix, &$normalized = false)
    {
        // 若$pathInfo为空，直接返回
        if (empty($pathInfo)) {
            return $pathInfo;
        }

        $sourcePathInfo = $pathInfo;
        // 将连续多条斜线折叠为一条,例如`site///index`将被转换为`site/index`
        if ($this->collapseSlashes) {
            $pathInfo = $this->collapseSlashes($pathInfo);
        }

        // 结尾斜杠是否应该按照规则的后缀设置进行规范化
        if ($this->normalizeTrailingSlash === true) {
            // 添加或删除$pathInfo中结尾的斜杠，这取决于$suffix后缀是否有一个斜杠
            $pathInfo = $this->normalizeTrailingSlash($pathInfo, $suffix);
        }

        // 判断$pathInfo是否发生了修改
        $normalized = $sourcePathInfo !== $pathInfo;

        return $pathInfo;
    }

    /**
     * 将连续多条斜线折叠为一条,例如`site///index`将被转换为`site/index`
     * Collapse consecutive slashes in $pathInfo, for example converts `site///index` into `site/index`.
     * @param string $pathInfo raw path info.
     * @return string normalized path info.
     */
    protected function collapseSlashes($pathInfo)
    {
        return ltrim(preg_replace('#/{2,}#', '/', $pathInfo), '/');
    }

    /**
     * 添加或删除$pathInfo中结尾的斜杠，这取决于$suffix后缀是否有一个斜杠
     * Adds or removes trailing slashes from $pathInfo depending on whether the $suffix has a
     * trailing slash or not.
     * @param string $pathInfo raw path info.
     * @param string $suffix
     * @return string normalized path info.
     */
    protected function normalizeTrailingSlash($pathInfo, $suffix)
    {
        // 若 $suffix 结尾是斜杠，而 $pathInfo 结尾不是斜杠，则向 $pathInfo 结尾添加斜杠
        if (substr($suffix, -1) === '/' && substr($pathInfo, -1) !== '/') {
            $pathInfo .= '/';
        // 若 $suffix 结尾不是斜杠，而 $pathInfo 结尾是斜杠，则移除 $pathInfo 结尾的斜杠
        } elseif (substr($suffix, -1) !== '/' && substr($pathInfo, -1) === '/') {
            $pathInfo = rtrim($pathInfo, '/');
        }

        return $pathInfo;
    }
}
