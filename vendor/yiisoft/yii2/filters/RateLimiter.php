<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\filters;

use Yii;
use yii\base\ActionFilter;
use yii\web\Request;
use yii\web\Response;
use yii\web\TooManyRequestsHttpException;

/**
 *RateLimiter实现了基于 漏桶算法 的速率限制算法。
 * RateLimiter implements a rate limiting algorithm based on the [leaky bucket algorithm](http://en.wikipedia.org/wiki/Leaky_bucket).
 *
 * You may use RateLimiter by attaching it as a behavior to a controller or module, like the following,
 * 您可以将RateLimiter作为一个控制器或模块的行为附加到控制器或模块中，如下所作
 *
 * ```php
 * public function behaviors()
 * {
 *     return [
 *         'rateLimiter' => [
 *             'class' => \yii\filters\RateLimiter::className(),
 *         ],
 *     ];
 * }
 * ```
 *
 * 当用户已经超过他的速率限制,RateLimiter将抛出一个[[TooManyRequestsHttpException]]异常。
 * When the user has exceeded his rate limit, RateLimiter will throw a [[TooManyRequestsHttpException]] exception.
 *
 * 注意，RateLimiter要求[[user]]实现 [[RateLimitInterface]]接口。
 * 如果[[user]]没有实现 [[RateLimitInterface]]接口，则RateLimiter什么也做不了。
 * Note that RateLimiter requires [[user]] to implement the [[RateLimitInterface]]. RateLimiter will
 * do nothing if [[user]] is not set or does not implement [[RateLimitInterface]].
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class RateLimiter extends ActionFilter
{
    /**
     * 是否在响应中包含速率限制头
     * @var bool whether to include rate limit headers in the response
     */
    public $enableRateLimitHeaders = true;
    /**
     * 当速率限制超过时要显示的消息
     * @var string the message to be displayed when rate limit exceeds
     */
    public $errorMessage = 'Rate limit exceeded.';
    /**
     * 实现RateLimitInterface接口的用户对象。
     * 如果不设置，它将使用`Yii::$app->user->getIdentity(false)`的值
     * @var RateLimitInterface the user object that implements the RateLimitInterface.
     * If not set, it will take the value of `Yii::$app->user->getIdentity(false)`.
     */
    public $user;
    /**
     * 当前请求，如果不设置，将使用 `request`应用程序组件
     * @var Request the current request. If not set, the `request` application component will be used.
     */
    public $request;
    /**
     * 发送的响应。如果不设置，将使用`response`应用程序组件。
     * @var Response the response to be sent. If not set, the `response` application component will be used.
     */
    public $response;


    /**
     * {@inheritdoc}
     */
    public function init()
    {
        if ($this->request === null) {
            $this->request = Yii::$app->getRequest();
        }
        if ($this->response === null) {
            $this->response = Yii::$app->getResponse();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function beforeAction($action)
    {
        // $this->user 实现RateLimitInterface接口的用户对象。
        // 如果不设置，它将使用`Yii::$app->user->getIdentity(false)`的值
        if ($this->user === null && Yii::$app->getUser()) {
            $this->user = Yii::$app->getUser()->getIdentity(false);
        }

        // 判断 $user 是否继承了 RateLimitInterface
        if ($this->user instanceof RateLimitInterface) {
            Yii::debug('Check rate limit', __METHOD__);
            $this->checkRateLimit($this->user, $this->request, $this->response, $action);
        } elseif ($this->user) {
            Yii::info('Rate limit skipped: "user" does not implement RateLimitInterface.', __METHOD__);
        } else {
            Yii::info('Rate limit skipped: user not logged in.', __METHOD__);
        }

        return true;
    }

    /**
     * 检查用户是否超过访问速率限制
     * Checks whether the rate limit exceeds.
     * @param RateLimitInterface $user the current user
     * @param Request $request
     * @param Response $response
     * @param \yii\base\Action $action the action to be executed
     * @throws TooManyRequestsHttpException if rate limit exceeds
     */
    public function checkRateLimit($user, $request, $response, $action)
    {
        /** 返回允许的请求的最大数目及时间，例如，[100, 600] 表示在600秒内最多100次的API调用。 */
        list($limit, $window) = $user->getRateLimit($request, $action);
        /** 从存储（数据库等）中加载剩余允许的请求数量和相应的时间戳。 */
        list($allowance, $timestamp) = $user->loadAllowance($request, $action);

        $current = time();

        $allowance += (int) (($current - $timestamp) * $limit / $window);
        if ($allowance > $limit) {
            $allowance = $limit;
        }

        if ($allowance < 1) {
            // 如果小于1，则超过了访问频率，存储并抛出异常
            $user->saveAllowance($request, $action, 0, $current);
            $this->addRateLimitHeaders($response, $limit, 0, $window);
            throw new TooManyRequestsHttpException($this->errorMessage);
        }

        // 如果不小于1，允许继续访问，保存允许剩余的请求数和当前的UNIX时间戳。
        $user->saveAllowance($request, $action, $allowance - 1, $current);
        // 添加 RateLimit http 头
        $this->addRateLimitHeaders($response, $limit, $allowance - 1, (int) (($limit - $allowance + 1) * $window / $limit));
    }

    /**
     * 将速率限制HTTP头添加到响应中
     * Adds the rate limit headers to the response.
     * @param Response $response
     * @param int $limit the maximum number of allowed requests during a period
     * @param int $remaining the remaining number of allowed requests within the current period
     * @param int $reset the number of seconds to wait before having maximum number of allowed requests again
     */
    public function addRateLimitHeaders($response, $limit, $remaining, $reset)
    {
        if ($this->enableRateLimitHeaders) {
            $response->getHeaders()
                ->set('X-Rate-Limit-Limit', $limit)
                ->set('X-Rate-Limit-Remaining', $remaining)
                ->set('X-Rate-Limit-Reset', $reset);
        }
    }
}
