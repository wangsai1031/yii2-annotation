<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\filters;

use Yii;
use yii\base\ActionFilter;
use yii\base\Action;

/**
 * HttpCache 通过使用`Last-Modified`和`ETag` HTTP头来实现客户端缓存
 * HttpCache implements client-side caching by utilizing the `Last-Modified` and `ETag` HTTP headers.
 *
 * 它是一个可以被添加到控制器并处理`beforeAction`事件的动作过滤器
 * It is an action filter that can be added to a controller and handles the `beforeAction` event.
 *
 * To use HttpCache, declare it in the `behaviors()` method of your controller class.
 * In the following example the filter will be applied to the `list`-action and
 * the Last-Modified header will contain the date of the last update to the user table in the database.
 *
 * ```php
 * public function behaviors()
 * {
 *     return [
 *         [
 *             'class' => 'yii\filters\HttpCache',
 *             'only' => ['index'],
 *             'lastModified' => function ($action, $params) {
 *                 $q = new \yii\db\Query();
 *                 return $q->from('user')->max('updated_at');
 *             },
 * //            'etagSeed' => function ($action, $params) {
 * //                $post = $this->findModel(\Yii::$app->request->get('id'));
   //                return serialize([$post->title, $post->content]);
 * //            }
 *         ],
 *     ];
 * }
 * ```
 *
 * @author Da:Sourcerer <webmaster@dasourcerer.net>
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class HttpCache extends ActionFilter
{
    /**
     * Last-Modified 头使用时间戳标明页面自上次客户端缓存后是否被修改过。
     *
     * 返回上次修改时间的UNIX时间戳的PHP回调函数
     * @var callable a PHP callback that returns the UNIX timestamp of the last modification time.
     * The callback's signature should be:
     *
     * ```php
     * /**
     * * @param Action $action 当前处理的动作对象
     * * @param array $params “params” 属性的值
     * * @return int 页面修改时的 Unix 时间戳
     * * /
     * function ($action, $params)
     * ```
     *
     * where `$action` is the [[Action]] object that this filter is currently handling;
     * `$params` takes the value of [[params]]. The callback should return a UNIX timestamp.
     *
     * @see http://tools.ietf.org/html/rfc7232#section-2.2
     */
    public $lastModified;
    /**
     * ETag 头使用一个哈希值表示页面内容。
     * 如果页面 被修改过，哈希值也会随之改变。
     * 通过对比客户端的哈希值和服务器端生成的哈 希值，浏览器就能判断页面是否被修改过，进而决定是否应该重新传输内容。
     *
     * ETag 相比 Last-Modified 能实现更复杂和更精确的缓存策略。 例如，当站点切换到另一个主题时可以使 ETag 失效。
     * 复杂的 Etag 生成种子可能会违背使用 HttpCache 的初衷而引起 不必要的性能开销，因为响应每一次请求都需要重新计算 Etag。
     * 请试着找出一个最简单的表达式去触发 Etag 失效。
     *
     * 注意: 为了遵循 RFC 7232（HTTP 1.1 协议），如果同时配置了 ETag 和 Last-Modified 头，HttpCache 将会同时 发送它们。
     * 并且如果客户端同时发送 If-None-Match 头 和 If-Modified-Since 头，则只有前者会被接受。
     *
     * 生成ETag seed 字符串的PHP回调函数
     * @var callable a PHP callback that generates the ETag seed string.
     * The callback's signature should be:
     *
     * ```php
     * /**
     * * @param Action $action 当前处理的动作对象
     * * @param array $params “params” 属性的值
     * * @return string 一段种子字符用来生成 ETag 哈希值
     * * /
     * function ($action, $params)
     * ```
     *
     * where `$action` is the [[Action]] object that this filter is currently handling;
     * `$params` takes the value of [[params]]. The callback should return a string serving
     * as the seed for generating an ETag.
     */
    public $etagSeed;
    /**
     * 是否产生弱的ETags
     * @var bool whether to generate weak ETags.
     *
     * 如果内容应该被认为是语义上等价的，而不是字节相等的，则应该使用弱的ETags。
     * Weak ETags should be used if the content should be considered semantically equivalent, but not byte-equal.
     *
     * @since 2.0.8
     * @see http://tools.ietf.org/html/rfc7232#section-2.3
     */
    public $weakEtag = false;
    /**
     * @var mixed additional parameters that should be passed to the [[lastModified]] and [[etagSeed]] callbacks.
     */
    public $params;
    /**
     * Cache-Control 头指定了页面的常规缓存策略.
     * @var string the value of the `Cache-Control` HTTP header. If null, the header will not be sent.
     * @see http://tools.ietf.org/html/rfc2616#section-14.9
     */
    public $cacheControlHeader = 'public, max-age=3600';
    /**
     * 会话缓存限制器.
     * 当页面使 session 时，PHP 将会按照 PHP.INI 中所设置的session.cache_limiter 值自动发送一些缓存相关的 HTTP 头。
     * 这些 HTTP 头有可能会干扰你原本设置的 HttpCache 或让其失效。
     * 为了避免此问题，默认情况下 HttpCache 禁止 自动发送这些头。
     * 想改变这一行为，可以配置 yii\filters\HttpCache::$sessionCacheLimiter 属性。
     * 该属性接受一个 字符串值，包括 public，private，private_no_expire， 和 nocache。
     * 请参考 PHP 手册中的缓存限制器 [session_cache_limiter()](http://www.php.net/manual/en/function.session-cache-limiter.php) 了解这些值的含义。
     *
     * @var string the name of the cache limiter to be set when [session_cache_limiter()](http://www.php.net/manual/en/function.session-cache-limiter.php)
     * is called. The default value is an empty string, meaning turning off automatic sending of cache headers entirely.
     * You may set this property to be `public`, `private`, `private_no_expire`, and `nocache`.
     * Please refer to [session_cache_limiter()](http://www.php.net/manual/en/function.session-cache-limiter.php)
     * for detailed explanation of these values.
     *
     * If this property is `null`, then `session_cache_limiter()` will not be called. As a result,
     * PHP will send headers according to the `session.cache_limiter` PHP ini setting.
     */
    public $sessionCacheLimiter = '';
    /**
     * @var boolean a value indicating whether this filter should be enabled.
     */
    public $enabled = true;


    /**
     * This method is invoked right before an action is to be executed (after all possible filters.)
     * You may override this method to do last-minute preparation for the action.
     * @param Action $action the action to be executed.
     * @return boolean whether the action should continue to be executed.
     */
    public function beforeAction($action)
    {
        if (!$this->enabled) {
            return true;
        }

        $verb = Yii::$app->getRequest()->getMethod();
        if ($verb !== 'GET' && $verb !== 'HEAD' || $this->lastModified === null && $this->etagSeed === null) {
            return true;
        }

        $lastModified = $etag = null;
        if ($this->lastModified !== null) {
            $lastModified = call_user_func($this->lastModified, $action, $this->params);
        }
        if ($this->etagSeed !== null) {
            $seed = call_user_func($this->etagSeed, $action, $this->params);
            if ($seed !== null) {
                $etag = $this->generateEtag($seed);
            }
        }

        $this->sendCacheControlHeader();

        $response = Yii::$app->getResponse();
        if ($etag !== null) {
            $response->getHeaders()->set('Etag', $etag);
        }

        $cacheValid = $this->validateCache($lastModified, $etag);
        // https://tools.ietf.org/html/rfc7232#section-4.1
        if ($lastModified !== null && (!$cacheValid || ($cacheValid && $etag === null))) {
            $response->getHeaders()->set('Last-Modified', gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
        }
        if ($cacheValid) {
            $response->setStatusCode(304);
            return false;
        }

        return true;
    }

    /**
     * Validates if the HTTP cache contains valid content.
     * If both Last-Modified and ETag are null, returns false.
     * @param integer $lastModified the calculated Last-Modified value in terms of a UNIX timestamp.
     * If null, the Last-Modified header will not be validated.
     * @param string $etag the calculated ETag value. If null, the ETag header will not be validated.
     * @return boolean whether the HTTP cache is still valid.
     */
    protected function validateCache($lastModified, $etag)
    {
        if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
            // HTTP_IF_NONE_MATCH takes precedence over HTTP_IF_MODIFIED_SINCE
            // http://tools.ietf.org/html/rfc7232#section-3.3
            return $etag !== null && in_array($etag, Yii::$app->request->getETags(), true);
        } elseif (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            return $lastModified !== null && @strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $lastModified;
        } else {
            return false;
        }
    }

    /**
     * Sends the cache control header to the client
     * @see cacheControlHeader
     */
    protected function sendCacheControlHeader()
    {
        if ($this->sessionCacheLimiter !== null) {
            if ($this->sessionCacheLimiter === '' && !headers_sent() && Yii::$app->getSession()->getIsActive()) {
                header_remove('Expires');
                header_remove('Cache-Control');
                header_remove('Last-Modified');
                header_remove('Pragma');
            }
            session_cache_limiter($this->sessionCacheLimiter);
        }

        $headers = Yii::$app->getResponse()->getHeaders();

        if ($this->cacheControlHeader !== null) {
            $headers->set('Cache-Control', $this->cacheControlHeader);
        }
    }

    /**
     * Generates an ETag from the given seed string.
     * @param string $seed Seed for the ETag
     * @return string the generated ETag
     */
    protected function generateEtag($seed)
    {
        $etag =  '"' . rtrim(base64_encode(sha1($seed, true)), '=') . '"';
        return $this->weakEtag ? 'W/' . $etag : $etag;
    }
}
