<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\web;

use Yii;
use yii\base\InvalidConfigException;
use yii\validators\IpValidator;

/**
 * web Request类表示HTTP请求
 * The web Request class represents an HTTP request.
 *
 *  请求URLs
 *  request 组件提供了许多方式来检测当前请求的URL。

    假设被请求的URL是 http://example.com/admin/index.php/product?id=100， 你可以像下面描述的那样获取URL的各个部分：

    yii\web\Request::url：返回 /admin/index.php/product?id=100, 此URL不包括host info部分。
    yii\web\Request::absoluteUrl：返回 http://example.com/admin/index.php/product?id=100, 包含host infode的整个URL。
    yii\web\Request::hostInfo：返回 http://example.com, 只有host info部分。
    yii\web\Request::pathInfo：返回 /product， 这个是入口脚本之后，问号之前（查询字符串）的部分。
    yii\web\Request::queryString：返回 id=100,问号之后的部分。
    yii\web\Request::baseUrl：返回 /admin, host info之后， 入口脚本之前的部分。
    yii\web\Request::scriptUrl：返回 /admin/index.php, 没有path info和查询字符串部分。
    yii\web\Request::serverName：返回 example.com, URL中的host name。
    yii\web\Request::serverPort：返回 80, 这是web服务中使用的端口。
 *
 *  HTTP头
    你可以通过 yii\web\Request::headers 属性返回的 header collection 获取HTTP头信息。 例如，

 * ```
    // $headers 是一个 yii\web\HeaderCollection 对象
    $headers = Yii::$app->request->headers;

    // 返回 Accept header 值
    $accept = $headers->get('Accept');

    if ($headers->has('User-Agent')) { /* 这是一个 User-Agent 头 * / }
 * ```
    请求组件也提供了支持快速访问常用头的方法，包括：

    yii\web\Request::userAgent：返回 User-Agent 头。
    yii\web\Request::contentType：返回 Content-Type 头的值， Content-Type 是请求体中MIME类型数据。
    yii\web\Request::acceptableContentTypes：返回用户可接受的内容MIME类型。 返回的类型是按照他们的质量得分来排序的。得分最高的类型将被最先返回。
    yii\web\Request::acceptableLanguages：返回用户可接受的语言。 返回的语言是按照他们的偏好层次来排序的。第一个参数代表最优先的语言。

 * 假如你的应用支持多语言，并且你想在终端用户最喜欢的语言中显示页面， 那么你可以使用语言协商方法 yii\web\Request::getPreferredLanguage()。
 * 这个方法通过 yii\web\Request::acceptableLanguages 在你的应用中所支持的语言列表里进行比较筛选，返回最适合的语言。

   提示: 你也可以使用 ContentNegotiator 过滤器进行动态确定哪些内容类型和语言应该在响应中使用。
 * 这个过滤器实现了上面介绍的内容协商的属性和方法。

 * 客户端信息
    你可以通过 yii\web\Request::userHost 和 yii\web\Request::userIP 分别获取host name和客户机的IP地址， 例如，

 * ```
    $userHost = Yii::$app->request->userHost;
    $userIP = Yii::$app->request->userIP;
 * ```
 *
 * It encapsulates the $_SERVER variable and resolves its inconsistency among different Web servers.
 * Also it provides an interface to retrieve request parameters from $_POST, $_GET, $_COOKIES and REST
 * parameters sent via other HTTP methods like PUT or DELETE.
 *
 * Request is configured as an application component in [[\yii\web\Application]] by default.
 * You can access that instance via `Yii::$app->request`.
 *
 * For more details and usage information on Request, see the [guide article on requests](guide:runtime-requests).
 *
 * @property string $absoluteUrl The currently requested absolute URL. This property is read-only.
 * @property array $acceptableContentTypes The content types ordered by the quality score. Types with the
 * highest scores will be returned first. The array keys are the content types, while the array values are the
 * corresponding quality score and other parameters as given in the header.
 * @property array $acceptableLanguages The languages ordered by the preference level. The first element
 * represents the most preferred language.
 * @property array $authCredentials That contains exactly two elements: - 0: the username sent via HTTP
 * authentication, `null` if the username is not given - 1: the password sent via HTTP authentication, `null` if
 * the password is not given. This property is read-only.
 * @property string|null $authPassword The password sent via HTTP authentication, `null` if the password is
 * not given. This property is read-only.
 * @property string|null $authUser The username sent via HTTP authentication, `null` if the username is not
 * given. This property is read-only.
 * @property string $baseUrl The relative URL for the application.
 * @property array $bodyParams The request parameters given in the request body.
 * @property string $contentType Request content-type. Null is returned if this information is not available.
 * This property is read-only.
 * @property CookieCollection $cookies The cookie collection. This property is read-only.
 * @property string $csrfToken The token used to perform CSRF validation. This property is read-only.
 * @property string $csrfTokenFromHeader The CSRF token sent via [[CSRF_HEADER]] by browser. Null is returned
 * if no such header is sent. This property is read-only.
 * @property array $eTags The entity tags. This property is read-only.
 * @property HeaderCollection $headers The header collection. This property is read-only.
 * @property string|null $hostInfo Schema and hostname part (with port number if needed) of the request URL
 * (e.g. `http://www.yiiframework.com`), null if can't be obtained from `$_SERVER` and wasn't set. See
 * [[getHostInfo()]] for security related notes on this property.
 * @property string|null $hostName Hostname part of the request URL (e.g. `www.yiiframework.com`). This
 * property is read-only.
 * 
 * 该请求是一个 AJAX 请求
 * @property bool $isAjax Whether this is an AJAX (XMLHttpRequest) request. This property is read-only.
 * @property bool $isDelete Whether this is a DELETE request. This property is read-only.
 * @property bool $isFlash Whether this is an Adobe Flash or Adobe Flex request. This property is read-only.
 * @property bool $isGet Whether this is a GET request. This property is read-only.
 * @property bool $isHead Whether this is a HEAD request. This property is read-only.
 * @property bool $isOptions Whether this is a OPTIONS request. This property is read-only.
 * @property bool $isPatch Whether this is a PATCH request. This property is read-only.
 * @property bool $isPjax Whether this is a PJAX request. This property is read-only.
 * @property bool $isPost Whether this is a POST request. This property is read-only.
 * @property bool $isPut Whether this is a PUT request. This property is read-only.
 * @property bool $isSecureConnection If the request is sent via secure channel (https). This property is
 * read-only.
 * @property string $method Request method, such as GET, POST, HEAD, PUT, PATCH, DELETE. The value returned is
 * turned into upper case. This property is read-only.
 * @property string|null $origin URL origin of a CORS request, `null` if not available. This property is
 * read-only.
 * @property string $pathInfo Part of the request URL that is after the entry script and before the question
 * mark. Note, the returned path info is already URL-decoded.
 * @property int $port Port number for insecure requests.
 * @property array $queryParams The request GET parameter values.
 * @property string $queryString Part of the request URL that is after the question mark. This property is
 * read-only.
 * @property string $rawBody The request body.
 * @property string|null $referrer URL referrer, null if not available. This property is read-only.
 * @property string|null $remoteHost Remote host name, `null` if not available. This property is read-only.
 * @property string|null $remoteIP Remote IP address, `null` if not available. This property is read-only.
 * @property string $scriptFile The entry script file path.
 * @property string $scriptUrl The relative URL of the entry script.
 * @property int $securePort Port number for secure requests.
 * @property string $serverName Server name, null if not available. This property is read-only.
 * @property int|null $serverPort Server port number, null if not available. This property is read-only.
 * @property string $url The currently requested relative URL. Note that the URI returned may be URL-encoded
 * depending on the client.
 * @property string|null $userAgent User agent, null if not available. This property is read-only.
 * @property string|null $userHost User host name, null if not available. This property is read-only.
 * @property string|null $userIP User IP address, null if not available. This property is read-only.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 * @SuppressWarnings(PHPMD.SuperGlobals)
 */
class Request extends \yii\base\Request
{
    /**
     * The name of the HTTP header for sending CSRF token.
     *
     * 发送CSRF token时使用的http头
     */
    const CSRF_HEADER = 'X-CSRF-Token';
    /**
     * CSRF令牌掩码的长度。
     * The length of the CSRF token mask.
     * @deprecated since 2.0.12. The mask length is now equal to the token length.
     */
    const CSRF_MASK_LENGTH = 8;

    /**
     * 是否开启csrf(跨站请求伪造)验证,默认为true
     * 当启用CSRF验证时，提交到Yii Web应用程序的表单必须来自相同的应用程序。
     * 如果不是，将会抛出400 HTTP异常。
     *
     * @var bool whether to enable CSRF (Cross-Site Request Forgery) validation. Defaults to true.
     * When CSRF validation is enabled, forms submitted to an Yii Web application must be originated
     * from the same application. If not, a 400 HTTP exception will be raised.
     *
     * 注意，该特性要求用户客户端接受cookie。
     * 另外，要使用这个功能，通过POST方法提交的表单必须包含一个隐藏的输入，它的名字是由[[csrfParam]]指定的。
     * 您可以使用[[\yii\helpers\Html::beginForm()]]来生成他隐藏的输入。
     *
     * Note, this feature requires that the user client accepts cookie. Also, to use this feature,
     * forms submitted via POST method must contain a hidden input whose name is specified by [[csrfParam]].
     * You may use [[\yii\helpers\Html::beginForm()]] to generate his hidden input.
     *
     * 在JavaScript中，你可以分别通过 `yii.getCsrfParam()` and `yii.getCsrfToken()`
     * 获取 [[csrfParam]] and [[csrfToken]]的值。
     * 必须注册 [[\yii\web\YiiAsset]]。
     * 您还需要使用[[\yii\helpers\Html::csrfMetaTags()]]在页面中添加CSRF元标签。
     * In JavaScript, you may get the values of [[csrfParam]] and [[csrfToken]] via `yii.getCsrfParam()` and
     * `yii.getCsrfToken()`, respectively. The [[\yii\web\YiiAsset]] asset must be registered.
     * You also need to include CSRF meta tags in your pages by using [[\yii\helpers\Html::csrfMetaTags()]].
     *
     * @see Controller::enableCsrfValidation
     * @see http://en.wikipedia.org/wiki/Cross-site_request_forgery
     */
    public $enableCsrfValidation = true;
    /**
     * @var string the name of the token used to prevent CSRF. Defaults to '_csrf'.
     * This property is used only when [[enableCsrfValidation]] is true.
     *
     * csrf 参数名
     */
    public $csrfParam = '_csrf';
    /**
     * 创建CSRF Cookie[[Cookie|cookie]]对象时的 Cookie的配置。
     * 该属性仅当 [[enableCsrfValidation]] and [[enableCsrfCookie]]都启用时才会使用。
     * @var array the configuration for creating the CSRF [[Cookie|cookie]]. This property is used only when
     * both [[enableCsrfValidation]] and [[enableCsrfCookie]] are true.
     * 
     * 'httpOnly' 用于阻止客户端脚本访问cookie，防止 XSS 攻击
     */
    public $csrfCookie = ['httpOnly' => true];
    /**
     * @var bool whether to use cookie to persist CSRF token. If false, CSRF token will be stored
     * in session under the name of [[csrfParam]]. Note that while storing CSRF tokens in session increases
     * security, it requires starting a session for every page, which will degrade your site performance.
     * 
     * 是否使用cookie来存储CSRF令牌。
     * 如果设置为false，csrf token将会存储在 session中.
     * 注意，在会话中存储CSRF令牌会增加安全性,但是它需要为每一个页面启动一个会话，这将降低的站点性能。
     */
    public $enableCsrfCookie = true;
    /**
     * @var bool whether cookies should be validated to ensure they are not tampered. Defaults to true.
     * 是否对cookie进行验证，以确保它们没有被篡改
     */
    public $enableCookieValidation = true;
    /**
     * @var string a secret key used for cookie validation. This property must be set if [[enableCookieValidation]] is true.
     * 用于cookie验证的秘密密钥， 如果设置 $enableCookieValidation = true， 则该项必须设置。
     */
    public $cookieValidationKey;
    /**
     * 使用POST模拟 PUT, PATCH or DELETE 请求时的 POST参数的名称。默认为“_method”。
     * @var string the name of the POST parameter that is used to indicate if a request is a PUT, PATCH or DELETE
     * request tunneled through POST. Defaults to '_method'.
     * @see getMethod()
     * @see getBodyParams()
     */
    public $methodParam = '_method';
    /**
     * 将原始HTTP请求主体转换为[[bodyParams]]的解析器。
     * @var array the parsers for converting the raw HTTP request body into [[bodyParams]].
     *
     * 数组的键是请求内容类型`Content-Types`，
     * 数组值是创建解析器对象 [[Yii::createObject|creating the parser objects]]的相应配置。
     * 解析器必须实现接口[[RequestParserInterface]].
     * The array keys are the request `Content-Types`, and the array values are the
     * corresponding configurations for [[Yii::createObject|creating the parser objects]].
     * A parser must implement the [[RequestParserInterface]].
     *
     * 为了支持对JSON请求的解析，您可以使用[[JsonParser]] 类。实例如下：
     * To enable parsing for JSON requests you can use the [[JsonParser]] class like in the following example:
     *
     * ```
     * [
     *     'application/json' => 'yii\web\JsonParser',
     * ]
     * ```
     *
     * 要注册解析所有请求类型的解析器，可以使用'*'作为数组键。
     * 在没有其他类型匹配的情况下，这一项将被用作后备。
     * To register a parser for parsing all request types you can use `'*'` as the array key.
     * This one will be used as a fallback in case no other types match.
     *
     * @see getBodyParams()
     */
    public $parsers = [];
    /**
     * @var array the configuration for trusted security related headers.
     *
     * An array key is an IPv4 or IPv6 IP address in CIDR notation for matching a client.
     *
     * An array value is a list of headers to trust. These will be matched against
     * [[secureHeaders]] to determine which headers are allowed to be sent by a specified host.
     * The case of the header names must be the same as specified in [[secureHeaders]].
     *
     * For example, to trust all headers listed in [[secureHeaders]] for IP addresses
     * in range `192.168.0.0-192.168.0.254` write the following:
     *
     * ```php
     * [
     *     '192.168.0.0/24',
     * ]
     * ```
     *
     * To trust just the `X-Forwarded-For` header from `10.0.0.1`, use:
     *
     * ```
     * [
     *     '10.0.0.1' => ['X-Forwarded-For']
     * ]
     * ```
     *
     * Default is to trust all headers except those listed in [[secureHeaders]] from all hosts.
     * Matches are tried in order and searching is stopped when IP matches.
     *
     * > Info: Matching is performed using [[IpValidator]].
     * See [[IpValidator::::setRanges()|IpValidator::setRanges()]]
     * and [[IpValidator::networks]] for advanced matching.
     *
     * @see $secureHeaders
     * @since 2.0.13
     */
    public $trustedHosts = [];
    /**
     * @var array lists of headers that are, by default, subject to the trusted host configuration.
     * These headers will be filtered unless explicitly allowed in [[trustedHosts]].
     * The match of header names is case-insensitive.
     * @see https://en.wikipedia.org/wiki/List_of_HTTP_header_fields
     * @see $trustedHosts
     * @since 2.0.13
     */
    public $secureHeaders = [
        // Common:
        'X-Forwarded-For',
        'X-Forwarded-Host',
        'X-Forwarded-Proto',

        // Microsoft:
        'Front-End-Https',
        'X-Rewrite-Url',
    ];
    /**
     * @var string[] List of headers where proxies store the real client IP.
     * It's not advisable to put insecure headers here.
     * The match of header names is case-insensitive.
     * @see $trustedHosts
     * @see $secureHeaders
     * @since 2.0.13
     */
    public $ipHeaders = [
        'X-Forwarded-For', // Common
    ];
    /**
     * @var array list of headers to check for determining whether the connection is made via HTTPS.
     * The array keys are header names and the array value is a list of header values that indicate a secure connection.
     * The match of header names and values is case-insensitive.
     * It's not advisable to put insecure headers here.
     * @see $trustedHosts
     * @see $secureHeaders
     * @since 2.0.13
     */
    public $secureProtocolHeaders = [
        'X-Forwarded-Proto' => ['https'], // Common
        'Front-End-Https' => ['on'], // Microsoft
    ];

    /**
     * cookie集合
     * @var CookieCollection Collection of request cookies.
     */
    private $_cookies;
    /**
     * 请求头的集合
     * @var HeaderCollection Collection of request headers.
     */
    private $_headers;


    /**
     * 将当前请求解析为一个路由和相关的参数
     *
     * Resolves the current request into a route and the associated parameters.
     * @return array the first element is the route, and the second is the associated parameters.
     *
     * 返回一个数组；第一个元素是路由，第二个元素是相关的参数
     *
     * @throws NotFoundHttpException if the request cannot be resolved.
     */
    public function resolve()
    {
        // 使用urlManager来解析请求
        $result = Yii::$app->getUrlManager()->parseRequest($this);
        if ($result !== false) {
            list($route, $params) = $result;
            if ($this->_queryParams === null) {
                
                /**
                 * 数组相加与array_merge()的区别：
                 * 当键名是字符串：
                 * 数组相加：如果键名相同，数组相加会将最先出现的值作为结果。
                 * array_merge()：如果键名相同，array_merge()后面数组元素值会覆盖前面数组元素值。
                 * 当键名是数字：
                 * 数组相加：如果键名相同，数组相加会将最先出现的值作为结果。
                 * array_merge()：如果键名为数字，array_merge()不会进行覆盖，且会重新生成数字键名索引。
                 *
                 * 将解析出来的参数与 $_GET 参数进行合并，并将参数复制给全局变量 $_GET
                 */
                $_GET = $params + $_GET; // preserve numeric keys
                // 保护数字键名不被改变，键名相同时，$params 会覆盖 $_GET中的参数
            } else {
                // $this->_queryParams不为 null,将查询参数赋值给$this->_queryParams
                $this->_queryParams = $params + $this->_queryParams;
            }

            // 通过 getQueryParams() 获取参数。
            return [$route, $this->getQueryParams()];
        }

        throw new NotFoundHttpException(Yii::t('yii', 'Page not found.'));
    }

    /**
     * Filters headers according to the [[trustedHosts]].
     * @param HeaderCollection $headerCollection
     * @since 2.0.13
     */
    protected function filterHeaders(HeaderCollection $headerCollection)
    {
        // do not trust any of the [[secureHeaders]] by default
        $trustedHeaders = [];

        // check if the client is a trusted host
        if (!empty($this->trustedHosts)) {
            $validator = $this->getIpValidator();
            $ip = $this->getRemoteIP();
            foreach ($this->trustedHosts as $cidr => $headers) {
                if (!is_array($headers)) {
                    $cidr = $headers;
                    $headers = $this->secureHeaders;
                }
                $validator->setRanges($cidr);
                if ($validator->validate($ip)) {
                    $trustedHeaders = $headers;
                    break;
                }
            }
        }

        // filter all secure headers unless they are trusted
        foreach ($this->secureHeaders as $secureHeader) {
            if (!in_array($secureHeader, $trustedHeaders)) {
                $headerCollection->remove($secureHeader);
            }
        }
    }

    /**
     * Creates instance of [[IpValidator]].
     * You can override this method to adjust validator or implement different matching strategy.
     *
     * @return IpValidator
     * @since 2.0.13
     */
    protected function getIpValidator()
    {
        return new IpValidator();
    }

    /**
     * Returns the header collection.
     * The header collection contains incoming HTTP headers.
     * @return HeaderCollection the header collection
     */
    public function getHeaders()
    {
        if ($this->_headers === null) {
            // 实例化为一个HeaderCollection
            $this->_headers = new HeaderCollection();
            if (function_exists('getallheaders')) {
                // 使用 getallheaders() 获取请求头部，以数组形式返回
                // getallheaders() ，这个方法仅在将PHP作为Apache的一个模块运行时有效。
                $headers = getallheaders();
                foreach ($headers as $name => $value) {
                    // 将数组形式的请求头部变成集合的元素
                    $this->_headers->add($name, $value);
                }
                
            // 使用 http_get_request_headers() 获取请求头部，以数组形式返回
            // http_get_request_headers() ，要求PHP启用HTTP扩展
            } elseif (function_exists('http_get_request_headers')) {
                $headers = http_get_request_headers();
                foreach ($headers as $name => $value) {
                    // 将数组形式的请求头部变成集合的元素
                    $this->_headers->add($name, $value);
                }
            // 使用 $_SERVER 数组获取头部
            // $_SERVER 数组的方法，需要遍历整个数组，并将所有以 HTTP_* 元素加入到集合中去。
            // 并且，要将所有 HTTP_HEADER_NAME 转换成 Header-Name 的形式。
            } else {
                foreach ($_SERVER as $name => $value) {
                    // 针对所有 $_SERVER['HTTP_*'] 元素
                    if (strncmp($name, 'HTTP_', 5) === 0) {
                        // 将 HTTP_HEADER_NAME 转换成 Header-Name 的形式
                        $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                        $this->_headers->add($name, $value);
                    }
                }
            }
            $this->filterHeaders($this->_headers);
        }

        return $this->_headers;
    }

    /**
     * 返回当前请求的方法，请留意方法名称是大小写敏感的，按规范应转换为大写字母
     *
     * Returns the method of the current request (e.g. GET, POST, HEAD, PUT, PATCH, DELETE).
     * 返回当前请求的方式（例如GET, POST, HEAD, PUT, PATCH, DELETE）
     * @return string request method, such as GET, POST, HEAD, PUT, PATCH, DELETE.
     * 返回值 字符串 请求的方式，例如GET, POST, HEAD, PUT, PATCH, DELETE.
     * The value returned is turned into upper case.
     * 该值会转化为大写字母
     */
    public function getMethod()
    {
        // $this->methodParam 默认值为 '_method'
        // 如果指定 $_POST['_method'] ，表示使用POST请求来模拟其他方法的请求。
        // 此时 $_POST['_method'] 即为所模拟的请求类型。
        if (isset($_POST[$this->methodParam])) {
            return strtoupper($_POST[$this->methodParam]);
        }

        // 或者使用 $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] 的值作为方法名。
        if ($this->headers->has('X-Http-Method-Override')) {
            return strtoupper($this->headers->get('X-Http-Method-Override'));
        }

        // 或者使用 $_SERVER['REQUEST_METHOD'] 作为方法名
        if (isset($_SERVER['REQUEST_METHOD'])) {
            return strtoupper($_SERVER['REQUEST_METHOD']);
        }

        // 未指定时，默认为 GET 方法
        return 'GET';
    }

    /**
     * 返回是否是GET请求
     * Returns whether this is a GET request.
     * @return bool whether this is a GET request.
     */
    public function getIsGet()
    {
        return $this->getMethod() === 'GET';
    }

    /**
     * Returns whether this is an OPTIONS request.
     * @return bool whether this is a OPTIONS request.
     */
    public function getIsOptions()
    {
        return $this->getMethod() === 'OPTIONS';
    }

    /**
     * Returns whether this is a HEAD request.
     * @return bool whether this is a HEAD request.
     */
    public function getIsHead()
    {
        return $this->getMethod() === 'HEAD';
    }

    /**
     * Returns whether this is a POST request.
     * @return bool whether this is a POST request.
     */
    public function getIsPost()
    {
        return $this->getMethod() === 'POST';
    }

    /**
     * Returns whether this is a DELETE request.
     * @return bool whether this is a DELETE request.
     */
    public function getIsDelete()
    {
        return $this->getMethod() === 'DELETE';
    }

    /**
     * Returns whether this is a PUT request.
     * @return bool whether this is a PUT request.
     */
    public function getIsPut()
    {
        return $this->getMethod() === 'PUT';
    }

    /**
     * Returns whether this is a PATCH request.
     * @return bool whether this is a PATCH request.
     */
    public function getIsPatch()
    {
        return $this->getMethod() === 'PATCH';
    }

    /**
     * AJAX请求是通过 X_REQUESTED_WITH 消息头来判断的
     *
     * Returns whether this is an AJAX (XMLHttpRequest) request.
     *
     * Note that jQuery doesn't set the header in case of cross domain
     * requests: https://stackoverflow.com/questions/8163703/cross-domain-ajax-doesnt-send-x-requested-with-header
     *
     * @return bool whether this is an AJAX (XMLHttpRequest) request.
     */
    public function getIsAjax()
    {
        return $this->headers->get('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * PJAX请求是AJAX请求的一种，增加了X_PJAX消息头的定义
     * Returns whether this is a PJAX request.
     * @return bool whether this is a PJAX request
     */
    public function getIsPjax()
    {
        return $this->getIsAjax() && $this->headers->has('X-Pjax');
    }

    /**
     * HTTP_USER_AGENT消息头中包含 'Shockwave' 或 'Flash' 字眼的（不区分大小写），就认为是FLASH请求
     *
     * Returns whether this is an Adobe Flash or Flex request.
     * @return bool whether this is an Adobe Flash or Adobe Flex request.
     */
    public function getIsFlash()
    {
        $userAgent = $this->headers->get('User-Agent', '');
        return stripos($userAgent, 'Shockwave') !== false
            || stripos($userAgent, 'Flash') !== false;
    }

    private $_rawBody;

    /**
     * php://input 是个只读流，用于获取请求体。
     *
     * php://input 是返回整个HTTP请求中，除去HTTP头部的全部原始内容， 而不管是什么Content Type（或称为编码方式）。
     * 相比较之下， $_POST 只支持 application/x-www-form-urlencoded 和 multipart/form-data-encoded 两种Content Type。
     * 其中前一种就是简单的HTML表单以 method="post" 提交时的形式， 后一种主要是用于上传文档。
     * 因此，对于诸如 application/json 等Content Type，这往往是在AJAX场景下使用， 那么使用 $_POST 得到的是空的内容，这时就必须使用 php://input 。
     *
     * 相比较于 $HTTP_RAW_POST_DATA ， php://input 无需额外地在php.ini中 激活 always-populate-raw-post-data ，而且对于内存的压力也比较小。
     *
     * 当编码方式为 multipart/form-data-encoded 时， php://input 是无效的。
     * 这种情况一般为上传文档。
     * 这种情况可以使用传统的 $_FILES 或者 yii\web\UploadedFile 。
     *
     * Returns the raw HTTP request body.
     * @return string the request body
     */
    public function getRawBody()
    {
        if ($this->_rawBody === null) {
            $this->_rawBody = file_get_contents('php://input');
        }

        return $this->_rawBody;
    }

    /**
     * Sets the raw HTTP request body, this method is mainly used by test scripts to simulate raw HTTP requests.
     * @param string $rawBody the request body
     */
    public function setRawBody($rawBody)
    {
        $this->_rawBody = $rawBody;
    }

    private $_bodyParams;

    /**
     * 获取所有POST参数，所有POST参数保存在 $this->_bodyParams 中
     *
     * Returns the request parameters given in the request body.
     *
     * Request parameters are determined using the parsers configured in [[parsers]] property.
     * If no parsers are configured for the current [[contentType]] it uses the PHP function `mb_parse_str()`
     * to parse the [[rawBody|request body]].
     * @return array the request parameters given in the request body.
     * @throws \yii\base\InvalidConfigException if a registered parser does not implement the [[RequestParserInterface]].
     * @see getMethod()
     * @see getBodyParam()
     * @see setBodyParams()
     */
    public function getBodyParams()
    {
        if ($this->_bodyParams === null) {
            // 如果是使用 POST 请求模拟其他请求的
            if (isset($_POST[$this->methodParam])) {
                $this->_bodyParams = $_POST;

                // 将 $_POST['_method'] 删掉，剩余的$_POST就是了
                unset($this->_bodyParams[$this->methodParam]);
                return $this->_bodyParams;
            }

            // 获取Content Type
            // 对于 'application/json; charset=UTF-8'，得到的是 'application/json'
            $rawContentType = $this->getContentType();
            if (($pos = strpos($rawContentType, ';')) !== false) {
                // e.g. text/html; charset=UTF-8
                $contentType = substr($rawContentType, 0, $pos);
            } else {
                $contentType = $rawContentType;
            }

            // 根据Content Type 选择相应的解析器对请求体进行解析
            if (isset($this->parsers[$contentType])) {
                // 创建解析器实例
                $parser = Yii::createObject($this->parsers[$contentType]);
                if (!($parser instanceof RequestParserInterface)) {
                    throw new InvalidConfigException("The '$contentType' request parser is invalid. It must implement the yii\\web\\RequestParserInterface.");
                }
                // 将请求体解析到 $this->_bodyParams
                $this->_bodyParams = $parser->parse($this->getRawBody(), $rawContentType);

            // 如果没有与Content Type对应的解析器，使用通用解析器
            } elseif (isset($this->parsers['*'])) {
                $parser = Yii::createObject($this->parsers['*']);
                if (!($parser instanceof RequestParserInterface)) {
                    throw new InvalidConfigException('The fallback request parser is invalid. It must implement the yii\\web\\RequestParserInterface.');
                }
                $this->_bodyParams = $parser->parse($this->getRawBody(), $rawContentType);

            // 连通用解析器也没有,看看是不是POST请求，如果是，PHP已经将请求参数放到$_POST中了，直接用就OK了
            } elseif ($this->getMethod() === 'POST') {
                // PHP has already parsed the body so we have all params in $_POST
                $this->_bodyParams = $_POST;
            } else {
                // 以上情况都不是，那就使用PHP的 mb_parse_str() 进行解析
                $this->_bodyParams = [];
                mb_parse_str($this->getRawBody(), $this->_bodyParams);
            }
        }

        return $this->_bodyParams;
    }

    /**
     * 设置请求体参数
     * Sets the request body parameters.
     * @param array $values the request body parameters (name-value pairs)
     * @see getBodyParam()
     * @see getBodyParams()
     */
    public function setBodyParams($values)
    {
        $this->_bodyParams = $values;
    }

    /**
     * 根据参数名获取单一的POST参数，不存在时，返回指定的默认值
     *
     * Returns the named request body parameter value.
     * If the parameter does not exist, the second parameter passed to this method will be returned.
     * @param string $name the parameter name
     * @param mixed $defaultValue the default parameter value if the parameter does not exist.
     * @return mixed the parameter value
     * @see getBodyParams()
     * @see setBodyParams()
     */
    public function getBodyParam($name, $defaultValue = null)
    {
        $params = $this->getBodyParams();

        if (is_object($params)) {
            // unable to use `ArrayHelper::getValue()` due to different dots in key logic and lack of exception handling
            try {
                return $params->{$name};
            } catch (\Exception $e) {
                return $defaultValue;
            }
        }

        return isset($params[$name]) ? $params[$name] : $defaultValue;
    }

    /**
     * 返回带有给定名称的POST参数。
     * 如果不指定名称，返回所有 POST 参数组成的数组。
     * Returns POST parameter with a given name. If name isn't specified, returns an array of all POST parameters.
     *
     * @param string $name the parameter name
     * @param mixed $defaultValue the default parameter value if the parameter does not exist.
     * @return array|mixed
     */
    public function post($name = null, $defaultValue = null)
    {
        if ($name === null) {
            return $this->getBodyParams();
        }

        return $this->getBodyParam($name, $defaultValue);
    }

    private $_queryParams;

    /**
     * 用于获取所有的GET参数,所有的GET参数保存在 $_GET 或 $this->_queryParams 中。
     *
     * Returns the request parameters given in the [[queryString]].
     *
     * This method will return the contents of `$_GET` if params where not explicitly set.
     * @return array the request GET parameter values.
     * @see setQueryParams()
     */
    public function getQueryParams()
    {
        if ($this->_queryParams === null) {
            // 请留意这里并未使用 $this->_queryParams = $_GET 进行缓存。
            // 说明一旦指定了 $_queryParams 则 $_GET 会失效。
            return $_GET;
        }

        return $this->_queryParams;
    }

    /**
     * Sets the request [[queryString]] parameters.
     * @param array $values the request query parameters (name-value pairs)
     * @see getQueryParam()
     * @see getQueryParams()
     */
    public function setQueryParams($values)
    {
        $this->_queryParams = $values;
    }

    /**
     * 用于获取GET参数，可以指定参数名和默认值
     *
     * Returns GET parameter with a given name. If name isn't specified, returns an array of all GET parameters.
     *
     * @param string $name the parameter name
     * @param mixed $defaultValue the default parameter value if the parameter does not exist.
     * @return array|mixed
     */
    public function get($name = null, $defaultValue = null)
    {
        if ($name === null) {
            return $this->getQueryParams();
        }

        return $this->getQueryParam($name, $defaultValue);
    }

    /**
     * // 根据参数名获取单一的GET参数，不存在时，返回指定的默认值
     * Returns the named GET parameter value.
     * If the GET parameter does not exist, the second parameter passed to this method will be returned.
     * @param string $name the GET parameter name.
     * @param mixed $defaultValue the default parameter value if the GET parameter does not exist.
     * @return mixed the GET parameter value
     * @see getBodyParam()
     */
    public function getQueryParam($name, $defaultValue = null)
    {
        $params = $this->getQueryParams();

        return isset($params[$name]) ? $params[$name] : $defaultValue;
    }

    private $_hostInfo;
    private $_hostName;

    /**
     * 返回当前请求URL的模式和主机部分
     * 包含协议和域名:https://www.baidu.com
     *
     * 返回的URL没有结尾的斜杠。
     * 默认情况下，这是根据用户请求信息确定的。
     * 您可以通过设置[[setHostInfo()|hostInfo]]属性来显式地指定它。
     * Returns the schema and host part of the current request URL.
     *
     * The returned URL does not have an ending slash.
     *
     * By default this value is based on the user request information. This method will
     * return the value of `$_SERVER['HTTP_HOST']` if it is available or `$_SERVER['SERVER_NAME']` if not.
     * You may want to check out the [PHP documentation](http://php.net/manual/en/reserved.variables.server.php)
     * for more information on these variables.
     *
     * You may explicitly specify it by setting the [[setHostInfo()|hostInfo]] property.
     *
     * > Warning: Dependent on the server configuration this information may not be
     * > reliable and [may be faked by the user sending the HTTP request](https://www.acunetix.com/vulnerabilities/web/host-header-attack).
     * > If the webserver is configured to serve the same site independent of the value of
     * > the `Host` header, this value is not reliable. In such situations you should either
     * > fix your webserver configuration or explicitly set the value by setting the [[setHostInfo()|hostInfo]] property.
     * > If you don't have access to the server configuration, you can setup [[\yii\filters\HostControl]] filter at
     * > application level in order to protect against such kind of attack.
     *
     * @property string|null schema and hostname part (with port number if needed) of the request URL
     * (e.g. `http://www.yiiframework.com`), null if can't be obtained from `$_SERVER` and wasn't set.
     * See [[getHostInfo()]] for security related notes on this property.
     * @return string|null schema and hostname part (with port number if needed) of the request URL
     * (e.g. `http://www.yiiframework.com`), null if can't be obtained from `$_SERVER` and wasn't set.
     * @see setHostInfo()
     */
    public function getHostInfo()
    {
        if ($this->_hostInfo === null) {
            // 判断是否是https连接
            $secure = $this->getIsSecureConnection();
            $http = $secure ? 'https' : 'http';

            if ($this->headers->has('X-Forwarded-Host')) {
                $this->_hostInfo = $http . '://' . $this->headers->get('X-Forwarded-Host');
            } elseif ($this->headers->has('Host')) {
                // 直接拼接主机地址
                $this->_hostInfo = $http . '://' . $this->headers->get('Host');
            } elseif (isset($_SERVER['SERVER_NAME'])) {
                $this->_hostInfo = $http . '://' . $_SERVER['SERVER_NAME'];
                // 获取端口号
                $port = $secure ? $this->getSecurePort() : $this->getPort();
                // 判断是否是常规端口号（http:80,https:443）,若不是，则拼接到后面
                if (($port !== 80 && !$secure) || ($port !== 443 && $secure)) {
                    $this->_hostInfo .= ':' . $port;
                }
            }
        }

        return $this->_hostInfo;
    }

    /**
     * Sets the schema and host part of the application URL.
     * This setter is provided in case the schema and hostname cannot be determined
     * on certain Web servers.
     * @param string|null $value the schema and host part of the application URL. The trailing slashes will be removed.
     * @see getHostInfo() for security related notes on this property.
     */
    public function setHostInfo($value)
    {
        $this->_hostName = null;
        $this->_hostInfo = $value === null ? null : rtrim($value, '/');
    }

    /**
     * Returns the host part of the current request URL.
     * Value is calculated from current [[getHostInfo()|hostInfo]] property.
     *
     * > Warning: The content of this value may not be reliable, dependent on the server
     * > configuration. Please refer to [[getHostInfo()]] for more information.
     *
     * @return string|null hostname part of the request URL (e.g. `www.yiiframework.com`)
     * @see getHostInfo()
     * @since 2.0.10
     */
    public function getHostName()
    {
        if ($this->_hostName === null) {
            $this->_hostName = parse_url($this->getHostInfo(), PHP_URL_HOST);
        }

        return $this->_hostName;
    }

    private $_baseUrl;

    /**
     * 返回应用程序的相对 URL。
     * 这类似于[[scriptUrl]]，除了它不包含脚本文件名，并移除了结尾的斜杠。
     * Returns the relative URL for the application.
     * This is similar to [[scriptUrl]] except that it does not include the script file name,
     * and the ending slashes are removed.
     * @return string the relative URL for the application
     * @see setScriptUrl()
     */
    public function getBaseUrl()
    {
        if ($this->_baseUrl === null) {
            // 用上面的脚本路径的父目录，再去除末尾的 \ 和 /
            $this->_baseUrl = rtrim(dirname($this->getScriptUrl()), '\\/');
        }

        return $this->_baseUrl;
    }

    /**
     * Sets the relative URL for the application.
     * By default the URL is determined based on the entry script URL.
     * This setter is provided in case you want to change this behavior.
     * @param string $value the relative URL for the application
     */
    public function setBaseUrl($value)
    {
        $this->_baseUrl = $value;
    }

    private $_scriptUrl;

    /**
     * 这个方法用于获取当前入口脚本的相对路径
     * 一般为 '/index.php'.
     *
     * Returns the relative URL of the entry script.
     * The implementation of this method referenced Zend_Controller_Request_Http in Zend Framework.
     * @return string the relative URL of the entry script.
     * @throws InvalidConfigException if unable to determine the entry script URL
     */
    public function getScriptUrl()
    {
        if ($this->_scriptUrl === null) {
            /**
             * eg:
             * 'SCRIPT_FILENAME' => string '/vagrant/nest/web/index.php' (length=14)
             * 'SCRIPT_NAME' => string '/index.php' (length=6)
             *
             * $this->getScriptFile() 用的是 $_SERVER['SCRIPT_FILENAME']，获取入口脚本的文件路径
             */
            $scriptFile = $this->getScriptFile();
            $scriptName = basename($scriptFile);

            // 下面的这些判断分支代码，为各主流PHP framework所用，Yii, Zend, Symfony等都是大同小异。
            if (isset($_SERVER['SCRIPT_NAME']) && basename($_SERVER['SCRIPT_NAME']) === $scriptName) {
                $this->_scriptUrl = $_SERVER['SCRIPT_NAME'];
            } elseif (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) === $scriptName) {
                $this->_scriptUrl = $_SERVER['PHP_SELF'];
            } elseif (isset($_SERVER['ORIG_SCRIPT_NAME']) && basename($_SERVER['ORIG_SCRIPT_NAME']) === $scriptName) {
                $this->_scriptUrl = $_SERVER['ORIG_SCRIPT_NAME'];
            } elseif (isset($_SERVER['PHP_SELF']) && ($pos = strpos($_SERVER['PHP_SELF'], '/' . $scriptName)) !== false) {
                $this->_scriptUrl = substr($_SERVER['SCRIPT_NAME'], 0, $pos) . '/' . $scriptName;
            } elseif (!empty($_SERVER['DOCUMENT_ROOT']) && strpos($scriptFile, $_SERVER['DOCUMENT_ROOT']) === 0) {
                $this->_scriptUrl = str_replace([$_SERVER['DOCUMENT_ROOT'], '\\'], ['', '/'], $scriptFile);
            } else {
                throw new InvalidConfigException('Unable to determine the entry script URL.');
            }
        }

        return $this->_scriptUrl;
    }

    /**
     * Sets the relative URL for the application entry script.
     * This setter is provided in case the entry script URL cannot be determined
     * on certain Web servers.
     * @param string $value the relative URL for the application entry script.
     */
    public function setScriptUrl($value)
    {
        $this->_scriptUrl = $value === null ? null : '/' . trim($value, '/');
    }

    private $_scriptFile;

    /**
     * 返回入口脚本文件路径
     * Returns the entry script file path.
     * The default implementation will simply return `$_SERVER['SCRIPT_FILENAME']`.
     * @return string the entry script file path
     * @throws InvalidConfigException
     */
    public function getScriptFile()
    {
        if (isset($this->_scriptFile)) {
            return $this->_scriptFile;
        }

        if (isset($_SERVER['SCRIPT_FILENAME'])) {
            return $_SERVER['SCRIPT_FILENAME'];
        }

        throw new InvalidConfigException('Unable to determine the entry script file path.');
    }

    /**
     * Sets the entry script file path.
     * The entry script file path normally can be obtained from `$_SERVER['SCRIPT_FILENAME']`.
     * If your server configuration does not return the correct value, you may configure
     * this property to make it right.
     * @param string $value the entry script file path.
     */
    public function setScriptFile($value)
    {
        $this->_scriptFile = $value;
    }

    private $_pathInfo;

    /**
     * Returns the path info of the currently requested URL.
     * A path info refers to the part that is after the entry script and before the question mark (query string).
     * The starting and ending slashes are both removed.
     * @return string part of the request URL that is after the entry script and before the question mark.
     * Note, the returned path info is already URL-decoded.
     * @throws InvalidConfigException if the path info cannot be determined due to unexpected server configuration
     *
     * 这个方法其实是调用 resolvePathInfo() 来获取路径信息的
     */
    public function getPathInfo()
    {
        if ($this->_pathInfo === null) {
            //这个方法其实是调用 resolvePathInfo() 来获取路径信息的
            $this->_pathInfo = $this->resolvePathInfo();
        }

        return $this->_pathInfo;
    }

    /**
     * Sets the path info of the current request.
     * This method is mainly provided for testing purpose.
     * @param string $value the path info of the current request
     */
    public function setPathInfo($value)
    {
        $this->_pathInfo = $value === null ? null : ltrim($value, '/');
    }

    /**
     * Resolves the path info part of the currently requested URL.
     * A path info refers to the part that is after the entry script and before the question mark (query string).
     * The starting slashes are both removed (ending slashes will be kept).
     * @return string part of the request URL that is after the entry script and before the question mark.
     * Note, the returned path info is decoded.
     * @throws InvalidConfigException if the path info cannot be determined due to unexpected server configuration
     */
    protected function resolvePathInfo()
    {
        // 这个 getUrl() 调用的是 resolveRequestUri() 来获取当前的URL
        $pathInfo = $this->getUrl();

        // 去除URL中的查询参数部分，即 ? 及之后的内容
        if (($pos = strpos($pathInfo, '?')) !== false) {
            $pathInfo = substr($pathInfo, 0, $pos);
        }

        // 使用PHP urldecode() 进行解码，所有 %## 转成对应的字符， + 转成空格
        $pathInfo = urldecode($pathInfo);

        // 这个正则列举了各种编码方式，通过排除这些编码，来确认是 UTF-8 编码
        // try to encode in UTF8 if not so
        // http://w3.org/International/questions/qa-forms-utf-8.html
        if (!preg_match('%^(?:
            [\x09\x0A\x0D\x20-\x7E]              # ASCII
            | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
            | \xE0[\xA0-\xBF][\x80-\xBF]         # excluding overlongs
            | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
            | \xED[\x80-\x9F][\x80-\xBF]         # excluding surrogates
            | \xF0[\x90-\xBF][\x80-\xBF]{2}      # planes 1-3
            | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
            | \xF4[\x80-\x8F][\x80-\xBF]{2}      # plane 16
            )*$%xs', $pathInfo)
        ) {
            $pathInfo = utf8_encode($pathInfo);
        }

        // 获取当前脚本的URL
        $scriptUrl = $this->getScriptUrl();
        // 获取Base URL
        $baseUrl = $this->getBaseUrl();
        if (strpos($pathInfo, $scriptUrl) === 0) {
            $pathInfo = substr($pathInfo, strlen($scriptUrl));
        } elseif ($baseUrl === '' || strpos($pathInfo, $baseUrl) === 0) {
            $pathInfo = substr($pathInfo, strlen($baseUrl));
        } elseif (isset($_SERVER['PHP_SELF']) && strpos($_SERVER['PHP_SELF'], $scriptUrl) === 0) {
            $pathInfo = substr($_SERVER['PHP_SELF'], strlen($scriptUrl));
        } else {
            throw new InvalidConfigException('Unable to determine the path info of the current request.');
        }

        // 去除 $pathInfo 前的 '/'
        if (substr($pathInfo, 0, 1) === '/') {
            $pathInfo = substr($pathInfo, 1);
        }

        return (string) $pathInfo;
    }

    /**
     * Returns the currently requested absolute URL.
     * This is a shortcut to the concatenation of [[hostInfo]] and [[url]].
     * @return string the currently requested absolute URL.
     */
    public function getAbsoluteUrl()
    {
        return $this->getHostInfo() . $this->getUrl();
    }

    private $_url;

    /**
     * 返回当前请求的相对URL
     * Returns the currently requested relative URL.
     * This refers to the portion of the URL that is after the [[hostInfo]] part.
     * It includes the [[queryString]] part if any.
     * @return string the currently requested relative URL. Note that the URI returned may be URL-encoded depending on the client.
     * @throws InvalidConfigException if the URL cannot be determined due to unusual server configuration
     */
    public function getUrl()
    {
        if ($this->_url === null) {
            // 这个其实调用的是 resolveRequestUri() 来获取当前URL
            $this->_url = $this->resolveRequestUri();
        }

        return $this->_url;
    }

    /**
     * Sets the currently requested relative URL.
     * The URI must refer to the portion that is after [[hostInfo]].
     * Note that the URI should be URL-encoded.
     * @param string $value the request URI to be set
     */
    public function setUrl($value)
    {
        $this->_url = $value;
    }

    /**
     * // 这个方法用于获取当前URL的URI部分，即主机或主机名之后的内容，包括查询参数。
    // 这个方法参考了 Zend Framework 1 的部分代码，通过各种环境下的HTTP头来获取URI。
    // 返回值为 $_SERVER['REQUEST_URI'] 或 $_SERVER['HTTP_X_REWRITE_URL']，
    // 或 $_SERVER['ORIG_PATH_INFO'] + $_SERVER['QUERY_STRING']。
    // 即，对于 http://www.digpage.com/index.html?helloworld，
    // 得到URI为 index.html?helloworld
     *
     * Resolves the request URI portion for the currently requested URL.
     * This refers to the portion that is after the [[hostInfo]] part. It includes the [[queryString]] part if any.
     * The implementation of this method referenced Zend_Controller_Request_Http in Zend Framework.
     * @return string|bool the request URI portion for the currently requested URL.
     * Note that the URI returned may be URL-encoded depending on the client.
     * @throws InvalidConfigException if the request URI cannot be determined due to unusual server configuration
     */
    protected function resolveRequestUri()
    {
        // 使用了开启了ISAPI_Rewrite的IIS
        if ($this->headers->has('X-Rewrite-Url')) { // IIS
            $requestUri = $this->headers->get('X-Rewrite-Url');
        } elseif (isset($_SERVER['REQUEST_URI'])) {
            // 一般情况，需要去掉URL中的协议、主机、端口等内容
            $requestUri = $_SERVER['REQUEST_URI'];
            // 如果URI不为空或以'/'打头，则去除 http:// 或 https:// 直到第一个 /
            if ($requestUri !== '' && $requestUri[0] !== '/') {
                $requestUri = preg_replace('/^(http|https):\/\/[^\/]+/i', '', $requestUri);
            }
        // IIS 5.0， PHP以CGI方式运行，需要把查询参数接上
        } elseif (isset($_SERVER['ORIG_PATH_INFO'])) { // IIS 5.0 CGI
            $requestUri = $_SERVER['ORIG_PATH_INFO'];
            if (!empty($_SERVER['QUERY_STRING'])) {
                $requestUri .= '?' . $_SERVER['QUERY_STRING'];
            }
        } else {
            throw new InvalidConfigException('Unable to determine the request URI.');
        }

        return $requestUri;
    }

    /**
     * Returns part of the request URL that is after the question mark.
     * @return string part of the request URL that is after the question mark
     */
    public function getQueryString()
    {
        return isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
    }

    /**
     * 判断请求是否通过安全通道(https)发送的。
     * Return if the request is sent via secure channel (https).
     * @return bool if the request is sent via secure channel (https)
     */
    public function getIsSecureConnection()
    {
        if (isset($_SERVER['HTTPS']) && (strcasecmp($_SERVER['HTTPS'], 'on') === 0 || $_SERVER['HTTPS'] == 1)) {
            return true;
        }
        foreach ($this->secureProtocolHeaders as $header => $values) {
            if (($headerValue = $this->headers->get($header, null)) !== null) {
                foreach ($values as $value) {
                    if (strcasecmp($headerValue, $value) === 0) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Returns the server name.
     * @return string server name, null if not available
     */
    public function getServerName()
    {
        return isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : null;
    }

    /**
     * Returns the server port number.
     * @return int|null server port number, null if not available
     */
    public function getServerPort()
    {
        return isset($_SERVER['SERVER_PORT']) ? (int) $_SERVER['SERVER_PORT'] : null;
    }

    /**
     * Returns the URL referrer.
     * @return string|null URL referrer, null if not available
     */
    public function getReferrer()
    {
        return $this->headers->get('Referer');
    }

    /**
     * Returns the URL origin of a CORS request.
     *
     * The return value is taken from the `Origin` [[getHeaders()|header]] sent by the browser.
     *
     * Note that the origin request header indicates where a fetch originates from.
     * It doesn't include any path information, but only the server name.
     * It is sent with a CORS requests, as well as with POST requests.
     * It is similar to the referer header, but, unlike this header, it doesn't disclose the whole path.
     * Please refer to <https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Origin> for more information.
     *
     * @return string|null URL origin of a CORS request, `null` if not available.
     * @see getHeaders()
     * @since 2.0.13
     */
    public function getOrigin()
    {
        return $this->getHeaders()->get('origin');
    }

    /**
     * Returns the user agent.
     * @return string|null user agent, null if not available
     */
    public function getUserAgent()
    {
        return $this->headers->get('User-Agent');
    }

    /**
     * 返回用户IP地址
     * Returns the user IP address.
     * The IP is determined using headers and / or `$_SERVER` variables.
     * @return string|null user IP address, null if not available
     */
    public function getUserIP()
    {
        foreach ($this->ipHeaders as $ipHeader) {
            if ($this->headers->has($ipHeader)) {
                return trim(explode(',', $this->headers->get($ipHeader))[0]);
            }
        }

        return $this->getRemoteIP();
    }

    /**
     * Returns the user host name.
     * The HOST is determined using headers and / or `$_SERVER` variables.
     * @return string|null user host name, null if not available
     */
    public function getUserHost()
    {
        foreach ($this->ipHeaders as $ipHeader) {
            if ($this->headers->has($ipHeader)) {
                return gethostbyaddr(trim(explode(',', $this->headers->get($ipHeader))[0]));
            }
        }

        return $this->getRemoteHost();
    }

    /**
     * Returns the IP on the other end of this connection.
     * This is always the next hop, any headers are ignored.
     * @return string|null remote IP address, `null` if not available.
     * @since 2.0.13
     */
    public function getRemoteIP()
    {
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    }

    /**
     * Returns the host name of the other end of this connection.
     * This is always the next hop, any headers are ignored.
     * @return string|null remote host name, `null` if not available
     * @see getUserHost()
     * @see getRemoteIP()
     * @since 2.0.13
     */
    public function getRemoteHost()
    {
        return isset($_SERVER['REMOTE_HOST']) ? $_SERVER['REMOTE_HOST'] : null;
    }

    /**
     * 通过HTTP认证发送的用户名，如果用户名没有被发送，将返回null
     * @return string|null the username sent via HTTP authentication, `null` if the username is not given
     * @see getAuthCredentials() to get both username and password in one call
     */
    public function getAuthUser()
    {
        return $this->getAuthCredentials()[0];
    }

    /**
     * 通过HTTP认证发送的密码，如果没有给出密码，则返回null
     * @return string|null the password sent via HTTP authentication, `null` if the password is not given
     * @see getAuthCredentials() to get both username and password in one call
     */
    public function getAuthPassword()
    {
        return $this->getAuthCredentials()[1];
    }

    /**
     * @return array that contains exactly two elements:
     * - 0: the username sent via HTTP authentication, `null` if the username is not given
     * - 1: the password sent via HTTP authentication, `null` if the password is not given
     * @see getAuthUser() to get only username
     * @see getAuthPassword() to get only password
     * @since 2.0.13
     */
    public function getAuthCredentials()
    {
        $username = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : null;
        $password = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : null;
        if ($username !== null || $password !== null) {
            return [$username, $password];
        }

        /*
         * Apache with php-cgi does not pass HTTP Basic authentication to PHP by default.
         * To make it work, add the following line to to your .htaccess file:
         *
         * RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
         */
        $auth_token = $this->getHeaders()->get('HTTP_AUTHORIZATION') ?: $this->getHeaders()->get('REDIRECT_HTTP_AUTHORIZATION');
        if ($auth_token !== null && strpos(strtolower($auth_token), 'basic') === 0) {
            $parts = array_map(function ($value) {
                return strlen($value) === 0 ? null : $value;
            }, explode(':', base64_decode(mb_substr($auth_token, 6)), 2));

            if (count($parts) < 2) {
                return [$parts[0], null];
            }

            return $parts;
        }

        return [null, null];
    }

    private $_port;

    /**
     * 返回用于非安全请求的端口。
     * 如果当前请求为非安全请求，则默认为80或服务器指定的端口。
     * Returns the port to use for insecure requests.
     * Defaults to 80, or the port specified by the server if the current
     * request is insecure.
     * @return int port number for insecure requests.
     * @see setPort()
     */
    public function getPort()
    {
        if ($this->_port === null) {
            $serverPort = $this->getServerPort();
            $this->_port = !$this->getIsSecureConnection() && $serverPort !== null ? $serverPort : 80;
        }

        return $this->_port;
    }

    /**
     * Sets the port to use for insecure requests.
     * This setter is provided in case a custom port is necessary for certain
     * server configurations.
     * @param int $value port number.
     */
    public function setPort($value)
    {
        if ($value != $this->_port) {
            $this->_port = (int) $value;
            $this->_hostInfo = null;
        }
    }

    private $_securePort;

    /**
     * 返回用于安全请求的端口。
     * 如果当前请求是安全的，则默认为443，或服务器指定的端口。
     * Returns the port to use for secure requests.
     * Defaults to 443, or the port specified by the server if the current
     * request is secure.
     * @return int port number for secure requests.
     * @see setSecurePort()
     */
    public function getSecurePort()
    {
        if ($this->_securePort === null) {
            $serverPort = $this->getServerPort();
            $this->_securePort = $this->getIsSecureConnection() && $serverPort !== null ? $serverPort : 443;
        }

        return $this->_securePort;
    }

    /**
     * Sets the port to use for secure requests.
     * This setter is provided in case a custom port is necessary for certain
     * server configurations.
     * @param int $value port number.
     */
    public function setSecurePort($value)
    {
        if ($value != $this->_securePort) {
            $this->_securePort = (int) $value;
            $this->_hostInfo = null;
        }
    }

    private $_contentTypes;

    /**
     * 返回终端用户可接受的内容类型.
     * 这是由Accept HTTP头决定的
     * Returns the content types acceptable by the end user.
     *
     * This is determined by the `Accept` HTTP header. For example,
     *
     * ```php
     * $_SERVER['HTTP_ACCEPT'] = 'text/plain; q=0.5, application/json; version=1.0, application/xml; version=2.0;';
     * $types = $request->getAcceptableContentTypes();
     * print_r($types);
     * // displays:
     * // [
     * //     'application/json' => ['q' => 1, 'version' => '1.0'],
     * //      'application/xml' => ['q' => 1, 'version' => '2.0'],
     * //           'text/plain' => ['q' => 0.5],
     * // ]
     * ```
     *
     * @return array the content types ordered by the quality score. Types with the highest scores
     * will be returned first. The array keys are the content types, while the array values
     * are the corresponding quality score and other parameters as given in the header.
     */
    public function getAcceptableContentTypes()
    {
        if ($this->_contentTypes === null) {
            if ($this->headers->get('Accept') !== null) {
                $this->_contentTypes = $this->parseAcceptHeader($this->headers->get('Accept'));
            } else {
                $this->_contentTypes = [];
            }
        }

        return $this->_contentTypes;
    }

    /**
     * Sets the acceptable content types.
     * Please refer to [[getAcceptableContentTypes()]] on the format of the parameter.
     * @param array $value the content types that are acceptable by the end user. They should
     * be ordered by the preference level.
     * @see getAcceptableContentTypes()
     * @see parseAcceptHeader()
     */
    public function setAcceptableContentTypes($value)
    {
        $this->_contentTypes = $value;
    }

    /**
     * Returns request content-type
     * The Content-Type header field indicates the MIME type of the data
     * contained in [[getRawBody()]] or, in the case of the HEAD method, the
     * media type that would have been sent had the request been a GET.
     * For the MIME-types the user expects in response, see [[acceptableContentTypes]].
     * @return string request content-type. Null is returned if this information is not available.
     * @link https://tools.ietf.org/html/rfc2616#section-14.17
     * HTTP 1.1 header field definitions
     */
    public function getContentType()
    {
        if (isset($_SERVER['CONTENT_TYPE'])) {
            return $_SERVER['CONTENT_TYPE'];
        }

        //fix bug https://bugs.php.net/bug.php?id=66606
        return $this->headers->get('Content-Type');
    }

    private $_languages;

    /**
     * Returns the languages acceptable by the end user.
     * This is determined by the `Accept-Language` HTTP header.
     * @return array the languages ordered by the preference level. The first element
     * represents the most preferred language.
     */
    public function getAcceptableLanguages()
    {
        if ($this->_languages === null) {
            if ($this->headers->has('Accept-Language')) {
                $this->_languages = array_keys($this->parseAcceptHeader($this->headers->get('Accept-Language')));
            } else {
                $this->_languages = [];
            }
        }

        return $this->_languages;
    }

    /**
     * @param array $value the languages that are acceptable by the end user. They should
     * be ordered by the preference level.
     */
    public function setAcceptableLanguages($value)
    {
        $this->_languages = $value;
    }

    /**
     * 解析给定的`Accept` (or `Accept-Language`)头
     * Parses the given `Accept` (or `Accept-Language`) header.
     *
     * This method will return the acceptable values with their quality scores and the corresponding parameters
     * as specified in the given `Accept` header. The array keys of the return value are the acceptable values,
     * while the array values consisting of the corresponding quality scores and parameters. The acceptable
     * values with the highest quality scores will be returned first. For example,
     *
     * ```php
     * $header = 'text/plain; q=0.5, application/json; version=1.0, application/xml; version=2.0;';
     * $accepts = $request->parseAcceptHeader($header);
     * print_r($accepts);
     * // displays:
     * // [
     * //     'application/json' => ['q' => 1, 'version' => '1.0'],
     * //      'application/xml' => ['q' => 1, 'version' => '2.0'],
     * //           'text/plain' => ['q' => 0.5],
     * // ]
     * ```
     *
     * @param string $header the header to be parsed
     * @return array the acceptable values ordered by their quality score. The values with the highest scores
     * will be returned first.
     */
    public function parseAcceptHeader($header)
    {
        $accepts = [];
        // 用逗号分隔成数组
        foreach (explode(',', $header) as $i => $part) {
            $params = preg_split('/\s*;\s*/', trim($part), -1, PREG_SPLIT_NO_EMPTY);
            if (empty($params)) {
                continue;
            }
            $values = [
                'q' => [$i, array_shift($params), 1],
            ];
            foreach ($params as $param) {
                if (strpos($param, '=') !== false) {
                    list($key, $value) = explode('=', $param, 2);
                    if ($key === 'q') {
                        $values['q'][2] = (float) $value;
                    } else {
                        $values[$key] = $value;
                    }
                } else {
                    $values[] = $param;
                }
            }
            $accepts[] = $values;
        }

        usort($accepts, function ($a, $b) {
            $a = $a['q']; // index, name, q
            $b = $b['q'];
            if ($a[2] > $b[2]) {
                return -1;
            }

            if ($a[2] < $b[2]) {
                return 1;
            }

            if ($a[1] === $b[1]) {
                return $a[0] > $b[0] ? 1 : -1;
            }

            if ($a[1] === '*/*') {
                return 1;
            }

            if ($b[1] === '*/*') {
                return -1;
            }

            $wa = $a[1][strlen($a[1]) - 1] === '*';
            $wb = $b[1][strlen($b[1]) - 1] === '*';
            if ($wa xor $wb) {
                return $wa ? 1 : -1;
            }

            return $a[0] > $b[0] ? 1 : -1;
        });

        $result = [];
        foreach ($accepts as $accept) {
            $name = $accept['q'][1];
            $accept['q'] = $accept['q'][2];
            $result[$name] = $accept;
        }

        return $result;
    }

    /**
     * Returns the user-preferred language that should be used by this application.
     * The language resolution is based on the user preferred languages and the languages
     * supported by the application. The method will try to find the best match.
     * @param array $languages a list of the languages supported by the application. If this is empty, the current
     * application language will be returned without further processing.
     * @return string the language that the application should use.
     */
    public function getPreferredLanguage(array $languages = [])
    {
        if (empty($languages)) {
            return Yii::$app->language;
        }
        foreach ($this->getAcceptableLanguages() as $acceptableLanguage) {
            $acceptableLanguage = str_replace('_', '-', strtolower($acceptableLanguage));
            foreach ($languages as $language) {
                $normalizedLanguage = str_replace('_', '-', strtolower($language));

                if (
                    $normalizedLanguage === $acceptableLanguage // en-us==en-us
                    || strpos($acceptableLanguage, $normalizedLanguage . '-') === 0 // en==en-us
                    || strpos($normalizedLanguage, $acceptableLanguage . '-') === 0 // en-us==en
                ) {
                    return $language;
                }
            }
        }

        return reset($languages);
    }

    /**
     * Gets the Etags.
     *
     * @return array The entity tags
     */
    public function getETags()
    {
        if ($this->headers->has('If-None-Match')) {
            return preg_split('/[\s,]+/', str_replace('-gzip', '', $this->headers->get('If-None-Match')), -1, PREG_SPLIT_NO_EMPTY);
        }

        return [];
    }

    /**
     * Returns the cookie collection.
     *
     * Through the returned cookie collection, you may access a cookie using the following syntax:
     *
     * ```php
     * $cookie = $request->cookies['name']
     * if ($cookie !== null) {
     *     $value = $cookie->value;
     * }
     *
     * // alternatively
     * $value = $request->cookies->getValue('name');
     * ```
     *
     * @return CookieCollection the cookie collection.
     */
    public function getCookies()
    {
        if ($this->_cookies === null) {
            $this->_cookies = new CookieCollection($this->loadCookies(), [
                'readOnly' => true,
            ]);
        }

        return $this->_cookies;
    }

    /**
     * 将源字符串`$_COOKIE`，转换成数组 [[Cookie]]
     * Converts `$_COOKIE` into an array of [[Cookie]].
     *
     * 例子：
     * $name  : _identity
     * $value : 4588675d6443115ae00699cf51b9df0b267788553c02293394215f6e2bc0070ca:2:{i:0;s:9:"_identity";i:1;s:46:"[32,"K1r5cqGo9D6Rm8eDwBPs1SL4DSRRQhIO",604800]";}
     *
     * 经过验证后：
     * $data ：a:2:{i:0;s:9:"_identity";i:1;s:46:"[32,"K1r5cqGo9D6Rm8eDwBPs1SL4DSRRQhIO",604800]";}
     *
     * @unserialize($data) 反序列化后：
     * $data ： ['_identity', '[32,"K1r5cqGo9D6Rm8eDwBPs1SL4DSRRQhIO",604800]'];
     *
     *
     * 返回从请求中获得的cookie
     * @return array the cookies obtained from request
     * @throws InvalidConfigException if [[cookieValidationKey]] is not set when [[enableCookieValidation]] is true
     */
    protected function loadCookies()
    {
        $cookies = [];
        if ($this->enableCookieValidation) {
            // 若启用 [[enableCookieValidation]] cookie验证，则 [[cookieValidationKey]] 必须存在
            // [[cookieValidationKey]]用于验证cookie是否被篡改
            if ($this->cookieValidationKey == '') {
                throw new InvalidConfigException(get_class($this) . '::cookieValidationKey must be configured with a secret key.');
            }
            // 遍历所有的cookie
            foreach ($_COOKIE as $name => $value) {
                // 若cookie值不是字符串，则跳过
                if (!is_string($value)) {
                    continue;
                }
                // 使用[[cookieValidationKey]]验证cookie值是否被篡改,并返回去掉验证字符串的数据
                $data = Yii::$app->getSecurity()->validateData($value, $this->cookieValidationKey);
                // 若验证失败，则跳过
                if ($data === false) {
                    continue;
                }
                // 反序列化数据
                $data = @unserialize($data);
                // 若$data是数组，且 $data[0] === $name
                if (is_array($data) && isset($data[0], $data[1]) && $data[0] === $name) {
                    // 格式化cookie数据
                    $cookies[$name] = Yii::createObject([
                        'class' => 'yii\web\Cookie',
                        'name' => $name,
                        'value' => $data[1],
                        'expire' => null,
                    ]);
                }
            }
        } else {
            // 为启用验证，则直接格式化cookie数据
            foreach ($_COOKIE as $name => $value) {
                $cookies[$name] = Yii::createObject([
                    'class' => 'yii\web\Cookie',
                    'name' => $name,
                    'value' => $value,
                    'expire' => null,
                ]);
            }
        }

        return $cookies;
    }

    private $_csrfToken;

    /**
     * 返回用于执行CSRF验证的令牌
     * Returns the token used to perform CSRF validation.
     *
     * 此令牌以一种防止[漏洞攻击BREACH attacks](http://breachattack.com/) 的方式生成。
     * 它可以通过一个隐藏的HTML表单或HTTP头值传递来支持CSRF验证。
     * This token is generated in a way to prevent [BREACH attacks](http://breachattack.com/). It may be passed
     * along via a hidden field of an HTML form or an HTTP header value to support CSRF validation.
     *
     * 是否重新生成CSRF令牌。
     * 当此参数为true时，每次调用该方法时，将生成一个新的CSRF令牌，并持久保存(在会话或cookie中)。
     * @param bool $regenerate whether to regenerate CSRF token. When this parameter is true, each time
     * this method is called, a new CSRF token will be generated and persisted (in session or cookie).
     * @return string the token used to perform CSRF validation.
     */
    public function getCsrfToken($regenerate = false)
    {
        // 如果 CSRF验证令牌 为空 或 需要重新生成令牌
        if ($this->_csrfToken === null || $regenerate) {
            // 需要重新生成令牌 或 从Cookie或Session没有读取到CSRF令牌
            $token = $this->loadCsrfToken();
            if ($regenerate || empty($token)) {
                // 生成CSRF令牌，并添加到Cookie或Session中
                $token = $this->generateCsrfToken();
            }
            $this->_csrfToken = Yii::$app->security->maskToken($token);
        }

        return $this->_csrfToken;
    }

    /**
     * 从Cookie或Session读取CSRF令牌。
     * 如果Cookie或Session中没有CSRF令牌，则返回Null
     * Loads the CSRF token from cookie or session.
     * @return string the CSRF token loaded from cookie or session. Null is returned if the cookie or session
     * does not have CSRF token.
     */
    protected function loadCsrfToken()
    {
        // 允许使用cookie来存储CSRF令牌。
        if ($this->enableCsrfCookie) {
            //从Cookie中读取CSRF令牌。
            return $this->getCookies()->getValue($this->csrfParam);
        }

        // 从session中读取CSRF令牌。
        return Yii::$app->getSession()->get($this->csrfParam);
    }

    /**
     * 生成一个用于执行CSRF验证的未加密的随机令牌。
     * Generates an unmasked random token used to perform CSRF validation.
     * @return string the random token for CSRF validation.
     */
    protected function generateCsrfToken()
    {
        // 生成指定长度的随机字符串。默认是32字节
        $token = Yii::$app->getSecurity()->generateRandomString();
        // 使用cookie来存储CSRF令牌。
        if ($this->enableCsrfCookie) {
            // 用随机生成的CSRF令牌创建一个cookie实例.
            $cookie = $this->createCsrfCookie($token);
            // 将该cookie添加到响应的cookie集合中
            Yii::$app->getResponse()->getCookies()->add($cookie);
        } else {
            // 将 $token 添加到 会话中
            Yii::$app->getSession()->set($this->csrfParam, $token);
        }

        return $token;
    }

    /**
     * @return string the CSRF token sent via [[CSRF_HEADER]] by browser. Null is returned if no such header is sent.
     */
    public function getCsrfTokenFromHeader()
    {
        return $this->headers->get(static::CSRF_HEADER);
    }

    /**
     * 用随机生成的CSRF令牌创建一个cookie实例.
     * 在[[csrfCookie]]中指定的初始化配置数组的值将被应用到生成的cookie中
     * Creates a cookie with a randomly generated CSRF token.
     * Initial values specified in [[csrfCookie]] will be applied to the generated cookie.
     * @param string $token the CSRF token
     * @return Cookie the generated cookie
     * @see enableCsrfValidation
     */
    protected function createCsrfCookie($token)
    {
        // 获取初始化数组
        // 使用配置数组创建Cookie实例并返回
        $options = $this->csrfCookie;
        return Yii::createObject(array_merge($options, [
            'class' => 'yii\web\Cookie',
            'name' => $this->csrfParam,
            'value' => $token,
        ]));
    }

    /**
     * Performs the CSRF validation.
     *
     * This method will validate the user-provided CSRF token by comparing it with the one stored in cookie or session.
     * This method is mainly called in [[Controller::beforeAction()]].
     *
     * Note that the method will NOT perform CSRF validation if [[enableCsrfValidation]] is false or the HTTP method
     * is among GET, HEAD or OPTIONS.
     *
     * @param string $clientSuppliedToken the user-provided CSRF token to be validated. If null, the token will be retrieved from
     * the [[csrfParam]] POST field or HTTP header.
     * This parameter is available since version 2.0.4.
     * @return bool whether CSRF token is valid. If [[enableCsrfValidation]] is false, this method will return true.
     */
    public function validateCsrfToken($clientSuppliedToken = null)
    {
        $method = $this->getMethod();
        // only validate CSRF token on non-"safe" methods https://tools.ietf.org/html/rfc2616#section-9.1.1
        if (!$this->enableCsrfValidation || in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        $trueToken = $this->getCsrfToken();

        if ($clientSuppliedToken !== null) {
            return $this->validateCsrfTokenInternal($clientSuppliedToken, $trueToken);
        }

        return $this->validateCsrfTokenInternal($this->getBodyParam($this->csrfParam), $trueToken)
            || $this->validateCsrfTokenInternal($this->getCsrfTokenFromHeader(), $trueToken);
    }

    /**
     * Validates CSRF token.
     *
     * @param string $clientSuppliedToken The masked client-supplied token.
     * @param string $trueToken The masked true token.
     * @return bool
     */
    private function validateCsrfTokenInternal($clientSuppliedToken, $trueToken)
    {
        if (!is_string($clientSuppliedToken)) {
            return false;
        }

        $security = Yii::$app->security;

        return $security->unmaskToken($clientSuppliedToken) === $security->unmaskToken($trueToken);
    }
}
