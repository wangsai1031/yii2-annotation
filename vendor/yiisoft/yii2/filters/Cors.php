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

/**
 * Cors : 跨域资源共享[Cross Origin Resource Sharing]
 *
 * 跨域资源共享 CORS 机制允许一个网页的许多资源（例如字体、JavaScript等） 这些资源可以通过其他域名访问获取。
 * 特别是JavaScript's AJAX 调用可使用 XMLHttpRequest 机制， 由于同源安全策略该跨域请求会被网页浏览器禁止.
 * CORS定义浏览器和服务器交互时哪些跨域请求允许和禁止。
 *
 * Cors filter implements [Cross Origin Resource Sharing](http://en.wikipedia.org/wiki/Cross-origin_resource_sharing).
 * 一定要仔细阅读CORS能做的和不能做的,CORS不保护您的API，但是允许开发人员授予对第三方代码的访问权(来自外部域的ajax调用)
 * Make sure to read carefully what CORS does and does not.
 * CORS do not secure your API, but allow the developer to grant access to third party code (ajax calls from external domain).
 *
 * 您可以使用CORS过滤器，将其作为一个控制器或模块的行为附加到一个控制器或模块，如下
 * You may use CORS filter by attaching it as a behavior to a controller or module, like the following,
 *
 * ```php
 * public function behaviors()
 * {
 *     return [
 *         'corsFilter' => [
 *             'class' => \yii\filters\Cors::className(),
 *         ],
 *     ];
 * }
 * ```
 *
 * CORS筛选器可以专门用于限制参数，比如这个
 * The CORS filter can be specialized to restrict parameters, like this,
 * [MDN CORS Information](https://developer.mozilla.org/en-US/docs/Web/HTTP/Access_control_CORS)
 *
 * ```php
 * public function behaviors()
 * {
 *     return [
 *         'corsFilter' => [
 *             'class' => \yii\filters\Cors::className(),
 *             'cors' => [
 *                 // restrict access to
 *                 'Origin' => ['http://www.myserver.com', 'https://www.myserver.com'],
 *                 'Access-Control-Request-Method' => ['POST', 'PUT'],
 *                 // Allow only POST and PUT methods
 *                 'Access-Control-Request-Headers' => ['X-Wsse'],
 *                 // Allow only headers 'X-Wsse'
 *                 'Access-Control-Allow-Credentials' => true,
 *                 // Allow OPTIONS caching
 *                 'Access-Control-Max-Age' => 3600,
 *                 // Allow the X-Pagination-Current-Page header to be exposed to the browser.
 *                 'Access-Control-Expose-Headers' => ['X-Pagination-Current-Page'],
 *             ],
 *
 *         ],
 *     ];
 * }
 * ```
 * 有关如何将CORS过滤器添加到控制器的更多信息，请参阅[Guide on REST controllers](guide:rest-controllers#cors)。
 * For more information on how to add the CORS filter to a controller, see the [Guide on REST controllers](guide:rest-controllers#cors).
 *
 * 例如，允许来源为 http://www.myserver.net 和方式为 GET, HEAD 和 OPTIONS 的CORS如下：
    ```
    public function behaviors()
    {
        return ArrayHelper::merge([
            [
                'class' => Cors::className(),
                'cors' => [
                    'Origin' => ['http://www.myserver.net'],
                    'Access-Control-Request-Method' => ['GET', 'HEAD', 'OPTIONS'],
                ],
            ],
        ], parent::behaviors());
    }
 * ```
    可以覆盖默认参数为每个动作调整CORS 头部。例如， 为login动作增加Access-Control-Allow-Credentials参数如下所示：
    ```
    public function behaviors()
    {
        return ArrayHelper::merge([
        [
            'class' => Cors::className(),
            'cors' => [
                'Origin' => ['http://www.myserver.net'],
                    'Access-Control-Request-Method' => ['GET', 'HEAD', 'OPTIONS'],
                ],
                'actions' => [
                    'login' => [
                        'Access-Control-Allow-Credentials' => true,
                    ]
                ]
            ],
        ], parent::behaviors());
    }
 *  ```
 * @author Philippe Gaultier <pgaultier@gmail.com>
 * @since 2.0
 */
class Cors extends ActionFilter
{
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
     * 为特定的actions定义特定的CORS规则
     * @var array define specific CORS rules for specific actions
     */
    public $actions = [];
    /**
     * CORS请求处理的基本消息头
     * @var array Basic headers handled for the CORS requests.
     */
    public $cors = [
        // cors['Origin']: 定义允许来源的数组，可为['*'] (任何用户)
        // 或 ['http://www.myserver.net', 'http://www.myotherserver.com']. 默认为 ['*'].
        'Origin' => ['*'],
        // cors['Access-Control-Request-Method']: 允许动作数组如 ['GET', 'OPTIONS', 'HEAD'].
        // 默认为 ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'].
        'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'],
        // cors['Access-Control-Request-Headers']: 允许请求头部数组，
        // 可为 ['*'] 所有类型头部 或 ['X-Request-With'] 指定类型头部. 默认为 ['*'].
        'Access-Control-Request-Headers' => ['*'],
        // cors['Access-Control-Allow-Credentials']: 定义当前请求是否使用证书，
        // 可为 true, false 或 null (不设置). 默认为 null.
        'Access-Control-Allow-Credentials' => null,
        // cors['Access-Control-Max-Age']: 定义请求的有效时间，默认为 86400.
        'Access-Control-Max-Age' => 86400,
        'Access-Control-Expose-Headers' => [],
    ];


    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        $this->request = $this->request ?: Yii::$app->getRequest();
        $this->response = $this->response ?: Yii::$app->getResponse();

        // 覆盖特定action的设置信息
        $this->overrideDefaultSettings($action);

        // 从请求中提取CORS HTTP头
        $requestCorsHeaders = $this->extractHeaders();
        // 为每个CORS HTTP头都创建特定的响应
        $responseCorsHeaders = $this->prepareHeaders($requestCorsHeaders);
        // 将CORS头添加到响应中
        $this->addCorsHeaders($this->response, $responseCorsHeaders);

        return true;
    }

    /**
     * 覆盖特定action的设置信息
     * Override settings for specific action
     * @param \yii\base\Action $action the action settings to override
     */
    public function overrideDefaultSettings($action)
    {
        if (isset($this->actions[$action->id])) {
            $actionParams = $this->actions[$action->id];
            $actionParamsKeys = array_keys($actionParams);
            foreach ($this->cors as $headerField => $headerValue) {
                if (in_array($headerField, $actionParamsKeys)) {
                    $this->cors[$headerField] = $actionParams[$headerField];
                }
            }
        }
    }

    /**
     * 从请求中提取CORS HTTP头
     * Extract CORS headers from the request
     * @return array CORS headers to handle
     */
    public function extractHeaders()
    {
        $headers = [];
        $requestHeaders = array_keys($this->cors);
        foreach ($requestHeaders as $headerField) {
            $serverField = $this->headerizeToPhp($headerField);
            $headerData = isset($_SERVER[$serverField]) ? $_SERVER[$serverField] : null;
            if ($headerData !== null) {
                $headers[$headerField] = $headerData;
            }
        }
        return $headers;
    }

    /**
     * 为每个CORS HTTP头创建特定的响应
     * For each CORS headers create the specific response
     * @param array $requestHeaders CORS headers we have detected 我们已经侦测到的CORS头信息
     * @return array CORS headers ready to be sent 准备发送的CORS头
     */
    public function prepareHeaders($requestHeaders)
    {
        $responseHeaders = [];
        // handle Origin
        if (isset($requestHeaders['Origin'], $this->cors['Origin'])) {
            if (in_array('*', $this->cors['Origin']) || in_array($requestHeaders['Origin'], $this->cors['Origin'])) {
                $responseHeaders['Access-Control-Allow-Origin'] = $requestHeaders['Origin'];
            }
        }

        $this->prepareAllowHeaders('Headers', $requestHeaders, $responseHeaders);

        if (isset($requestHeaders['Access-Control-Request-Method'])) {
            $responseHeaders['Access-Control-Allow-Methods'] = implode(', ', $this->cors['Access-Control-Request-Method']);
        }

        if (isset($this->cors['Access-Control-Allow-Credentials'])) {
            $responseHeaders['Access-Control-Allow-Credentials'] = $this->cors['Access-Control-Allow-Credentials'] ? 'true' : 'false';
        }

        if (isset($this->cors['Access-Control-Max-Age']) && Yii::$app->getRequest()->getIsOptions()) {
            $responseHeaders['Access-Control-Max-Age'] = $this->cors['Access-Control-Max-Age'];
        }

        if (isset($this->cors['Access-Control-Expose-Headers'])) {
            $responseHeaders['Access-Control-Expose-Headers'] = implode(', ', $this->cors['Access-Control-Expose-Headers']);
        }

        return $responseHeaders;
    }

    /**
     * 处理经典的CORS请求以避免重复的代码
     * Handle classic CORS request to avoid duplicate code
     * 我们要处理的头信息
     * @param string $type the kind of headers we would handle
     * 客户端的CORS 请求头
     * @param array $requestHeaders CORS headers request by client
     * 发送给客户端的CORS响应头
     * @param array $responseHeaders CORS response headers sent to the client
     */
    protected function prepareAllowHeaders($type, $requestHeaders, &$responseHeaders)
    {
        $requestHeaderField = 'Access-Control-Request-' . $type;
        $responseHeaderField = 'Access-Control-Allow-' . $type;
        if (!isset($requestHeaders[$requestHeaderField], $this->cors[$requestHeaderField])) {
            return;
        }
        if (in_array('*', $this->cors[$requestHeaderField])) {
            $responseHeaders[$responseHeaderField] = $this->headerize($requestHeaders[$requestHeaderField]);
        } else {
            $requestedData = preg_split("/[\\s,]+/", $requestHeaders[$requestHeaderField], -1, PREG_SPLIT_NO_EMPTY);
            $acceptedData = array_uintersect($requestedData, $this->cors[$requestHeaderField], 'strcasecmp');
            if (!empty($acceptedData)) {
                $responseHeaders[$responseHeaderField] = implode(', ', $acceptedData);
            }
        }
    }

    /**
     * 将CORS头添加到响应中
     * Adds the CORS headers to the response
     * @param Response $response
     * @param array CORS headers which have been computed
     */
    public function addCorsHeaders($response, $headers)
    {
        if (empty($headers) === false) {
            $responseHeaders = $response->getHeaders();
            foreach ($headers as $field => $value) {
                $responseHeaders->set($field, $value);
            }
        }
    }

    /**
     * 将任何字符串(包括带有HTTP前缀的php头)转换为HTTP 头格式。
     * 返回 浏览器中 "header" 格式
     *
     * Convert any string (including php headers with HTTP prefix) to header format like :
     *  * X-PINGOTHER -> X-Pingother
     *  * X_PINGOTHER -> X-Pingother
     * @param string $string string to convert
     * @return string the result in "header" format
     */
    protected function headerize($string)
    {
        $headers = preg_split("/[\\s,]+/", $string, -1, PREG_SPLIT_NO_EMPTY);
        $headers = array_map(function ($element) {
            return str_replace(' ', '-', ucwords(strtolower(str_replace(['_', '-'], [' ', ' '], $element))));
        }, $headers);
        return implode(', ', $headers);
    }

    /**
     * 将任何字符串(包括带有HTTP前缀的php头)转换为 HTTP 头格式
     *
     * 返回 PHP $_SERVER['header'] 中的格式
     * Convert any string (including php headers with HTTP prefix) to header format like :
     *  * X-Pingother -> HTTP_X_PINGOTHER
     *  * X PINGOTHER -> HTTP_X_PINGOTHER
     * @param string $string string to convert
     * @return string the result in "php $_SERVER header" format
     */
    protected function headerizeToPhp($string)
    {
        return 'HTTP_' . strtoupper(str_replace([' ', '-'], ['_', '_'], $string));
    }
}
