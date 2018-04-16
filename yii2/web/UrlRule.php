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
 * UrlRule表示UrlManager用于解析和生成url的规则
 * UrlRule represents a rule used by [[UrlManager]] for parsing and generating URLs.
 *
 * 要定义自己的URL解析和创建逻辑，可以从这个类延伸，并将其添加到[[UrlManager::rules]]。
 * To define your own URL parsing and creation logic you can extend from this class
 * and add it to [[UrlManager::rules]] like this:
 *
 * ```php
 * 'rules' => [
 *     ['class' => 'MyUrlRule', 'pattern' => '...', 'route' => 'site/index', ...],
 *     // ...
 * ]
 * ```
 *
 * @property null|int $createUrlStatus Status of the URL creation after the last [[createUrl()]] call. `null`
 * if rule does not provide info about create status. This property is read-only.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class UrlRule extends BaseObject implements UrlRuleInterface
{
    // 用于 $mode 表示路由规则的2种工作模式：仅用于解析请求和仅用于生成URL。
    // 任意不为1或2的值均表示两种模式同时适用，
    // 一般未设定或为0时即表示两种模式均适用。
    /**
     * 使用该值设置[[mode]]，标记该规则仅用于URL解析
     * Set [[mode]] with this value to mark that this rule is for URL parsing only.
     */
    const PARSING_ONLY = 1;
    /**
     * 使用该值设置[[mode]]，标记该规则只用于URL创建
     * Set [[mode]] with this value to mark that this rule is for URL creation only.
     */
    const CREATION_ONLY = 2;
    /**
     * Represents the successful URL generation by last [[createUrl()]] call.
     * @see $createStatus
     * @since 2.0.12
     */
    const CREATE_STATUS_SUCCESS = 0;
    /**
     * Represents the unsuccessful URL generation by last [[createUrl()]] call, because rule does not support
     * creating URLs.
     * @see $createStatus
     * @since 2.0.12
     */
    const CREATE_STATUS_PARSING_ONLY = 1;
    /**
     * Represents the unsuccessful URL generation by last [[createUrl()]] call, because of mismatched route.
     * @see $createStatus
     * @since 2.0.12
     */
    const CREATE_STATUS_ROUTE_MISMATCH = 2;
    /**
     * Represents the unsuccessful URL generation by last [[createUrl()]] call, because of mismatched
     * or missing parameters.
     * @see $createStatus
     * @since 2.0.12
     */
    const CREATE_STATUS_PARAMS_MISMATCH = 4;

    /**
     * // 路由规则名称
     * @var string the name of this rule. If not set, it will use [[pattern]] as the name.
     */
    public $name;
    /**
     *  // 用于解析请求或生成URL的模式，通常是正则表达式
     * On the rule initialization, the [[pattern]] matching parameters names will be replaced with [[placeholders]].
     * @var string the pattern used to parse and create the path info part of a URL.
     * @see host
     * @see placeholders
     */
    public $pattern;
    /**
     * // 用于解析或创建URL时，处理主机信息的部分，如 http://example.com
     * @var string the pattern used to parse and create the host info part of a URL (e.g. `http://example.com`).
     * @see pattern
     */
    public $host;
    /**
     * // 指向controller 和 action 的路由
     * @var string the route to the controller action
     */
    public $route;
    /**
     * 该规则提供的默认GET参数(键值对)，在当前规则用于解析请求时，这些GET参数会被注入到 $_GET 中去。
     * @var array the default GET parameters (name => value) that this rule provides.
     * When this rule is used to parse the incoming request, the values declared in this property
     * will be injected into $_GET.
     */
    public $defaults = [];
    /**
     * 指定URL的后缀，通常是诸如 ".html" 等，
     * 使得一个URL看起来好像指向一个静态页面。
     * 如果这个值未设定，使用 UrlManager::suffix 的值。
     *
     * @var string the URL suffix used for this rule.
     * For example, ".html" can be used so that the URL looks like pointing to a static HTML page.
     * If not set, the value of [[UrlManager::suffix]] will be used.
     */
    public $suffix;
    /**
     * 指定当前规则适用的HTTP方法，如 GET, POST, DELETE 等。
     * 可以使用数组表示同时适用于多个方法。
     * 如果未设定，表明当前规则适用于所有方法。
     * 当然，这个属性仅在解析请求时有效，在生成URL时是无效的。
     * @var string|array the HTTP verb (e.g. GET, POST, DELETE) that this rule should match.
     * Use array to represent multiple verbs that this rule may match.
     * If this property is not set, the rule can match any verb.
     * Note that this property is only used when parsing a request. It is ignored for URL creation.
     */
    public $verb;
    /**
     * 表明当前规则的工作模式，取值可以是 0, PARSING_ONLY, CREATION_ONLY。
     * 未设定时等同于0。
     * 
     * @var int a value indicating if this rule should be used for both request parsing and URL creation,
     * parsing only, or creation only.
     * If not set or 0, it means the rule is both request parsing and URL creation.
     * If it is [[PARSING_ONLY]], the rule is for request parsing only.
     * If it is [[CREATION_ONLY]], the rule is for URL creation only.
     */
    public $mode;
    /**
     * 表明URL中的参数是否需要进行url编码，默认是进行。
     * @var bool a value indicating if parameters should be url encoded.
     */
    public $encodeParams = true;
    /**
     * 这个规则使用的[[UrlNormalizer]]格式化器的配置。
     * 如果为null，将会使用 [[UrlManager::normalizer]]
     * 如果为false,该条规则将跳过格式化
     *
     * @var UrlNormalizer|array|false|null the configuration for [[UrlNormalizer]] used by this rule.
     * If `null`, [[UrlManager::normalizer]] will be used, if `false`, normalization will be skipped
     * for this rule.
     * @since 2.0.10
     */
    public $normalizer;

    /**
     * @var int|null status of the URL creation after the last [[createUrl()]] call.
     * @since 2.0.12
     */
    protected $createStatus;
    /**
     * 匹配参数名的占位符列表，在[[parseRequest()]], [[createUrl()]]中使用。
     * 在初始化规则中，模式参数名称将被占位符替换。
     * 这个数组包含原始参数名和占位符之间的关系。
     * 数组键是占位符，值是原始名称。
     * @var array list of placeholders for matching parameters names. Used in [[parseRequest()]], [[createUrl()]].
     * On the rule initialization, the [[pattern]] parameters names will be replaced with placeholders.
     * This array contains relations between the original parameters names and their placeholders.
     * The array keys are the placeholders and the values are the original names.
     *
     * @see parseRequest()
     * @see createUrl()
     * @since 2.0.7
     */
    protected $placeholders = [];

    /**
     * 用于生成新URL的模板
     *
     * @var string the template for generating a new URL. This is derived from [[pattern]] and is used in generating URL.
     */
    private $_template;
    /**
     * // 一个用于匹配路由部分的正则表达式，用于生成URL
     * @var string the regex for matching the route part. This is used in generating URL.
     */
    private $_routeRule;
    /**
     * // 用于保存一组匹配参数的正则表达式，用于生成URL
     * @var array list of regex for matching parameters. This is used in generating URL.
     */
    private $_paramRules = [];
    /**
     * // 保存一组路由中使用的参数
     * @var array list of parameters used in the route.
     */
    private $_routeParams = [];


    /**
     * @return string
     * @since 2.0.11
     */
    public function __toString()
    {
        $str = '';
        if ($this->verb !== null) {
            $str .= implode(',', $this->verb) . ' ';
        }
        if ($this->host !== null && strrpos($this->name, $this->host) === false) {
            $str .= $this->host . '/';
        }
        $str .= $this->name;

        if ($str === '') {
            return '/';
        }

        return $str;
    }

    /**
     * Initializes this rule.
     *
     * 让我们来看看这个 init() 的成果吧。
     * 以 ['post/<action:\w+>/<id:\d+>' => 'post/<action>'] 为例，经过 init() 处理后，我们得到了:
     *
     * $urlRule->route = 'post/<action>';
     * $urlRule->pattern = '#^post/(?P<action>\w+)(/(?P<id>\d+))?$#u';
     * $urlRule->_template = '/post/<action>/<id>/';
     * $urlRule->_routeRule = '#^post/(?P<action>\w+)$#';
     * $urlRule->_routeParams = ['action' => '<action>'];
     * $urlRule->_paramRules = ['id' => '#^\d+$#u'];
     *
     */
    public function init()
    {
        // 一个路由规则必定要有 pattern ，否则是没有意义的，
        // 一个什么都没规定的规定，要来何用？
        if ($this->pattern === null) {
            throw new InvalidConfigException('UrlRule::pattern must be set.');
        }
        // 不指定规则匹配后所要指派的路由，Yii怎么知道将请求交给谁来处理？
        // 不指定路由，Yii怎么知道这个规则可以为谁创建URL？
        if ($this->route === null) {
            throw new InvalidConfigException('UrlRule::route must be set.');
        }
        // 如果配置了格式化器
        if (is_array($this->normalizer)) {
            // 配置数组中加入class
            $normalizerConfig = array_merge(['class' => UrlNormalizer::className()], $this->normalizer);
            // 创建格式化器对象
            $this->normalizer = Yii::createObject($normalizerConfig);
        }

        // 若格式化器不是 UrlNormalizer 的实例，则抛异常
        if ($this->normalizer !== null && $this->normalizer !== false && !$this->normalizer instanceof UrlNormalizer) {
            throw new InvalidConfigException('Invalid config for UrlRule::normalizer.');
        }

        // 如果定义了一个或多个verb，说明规则仅适用于特定的HTTP方法。
        if ($this->verb !== null) {
            // verb的定义可以是字符串（单一的verb）或数组（单一或多个verb）。
            if (is_array($this->verb)) {
                foreach ($this->verb as $i => $verb) {
                    // 既然是HTTP方法，那就要全部大写。
                    $this->verb[$i] = strtoupper($verb);
                }
            } else {
                $this->verb = [strtoupper($this->verb)];
            }
        }
        // 若未指定规则的名称，那么使用最能区别于其他规则的 $pattern 作为规则的名称
        if ($this->name === null) {
            $this->name = $this->pattern;
        }

        $this->preparePattern();
    }

    /**
     * Process [[$pattern]] on rule initialization.
     */
    private function preparePattern()
    {

        // 删除 pattern 两端的 "/"，特别是重复的 "/"，
        // 在写 pattern 时，虽然有正则的成分，但不需要在两端加上 "/"，
        // 更不能加上 "#" 等其他分隔符
        $this->pattern = $this->trimSlashes($this->pattern);
        // route 也要去掉两头的 '/'
        $this->route = trim($this->route, '/');

        // 如果定义了 host ，将 host 部分加在 pattern 前面，作为新的 pattern
        if ($this->host !== null) {
            // 写入的host末尾如果已经包含有 "/" 则去掉，特别是重复的 "/"
            $this->host = rtrim($this->host, '/');
            $this->pattern = rtrim($this->host . '/' . $this->pattern, '/');

        // 既未定义 host ，pattern 又是空的，那么 pattern 匹配任意字符串。
        // 而基于这个pattern的，用于生成的URL的template就是空的，
        // 意味着使用该规则生成所有URL都是空的。
        // 后续也无需再作其他初始化工作了。
        } elseif ($this->pattern === '') {
            $this->_template = '';
            $this->pattern = '#^$#u';

            return;
        // pattern 不是空串，且包含有 '://'，以此认定该pattern包含主机信息
        } elseif (($pos = strpos($this->pattern, '://')) !== false) {
            // 除 '://' 外，第一个 '/' 之前的内容就是主机信息
            if (($pos2 = strpos($this->pattern, '/', $pos + 3)) !== false) {
                $this->host = substr($this->pattern, 0, $pos2);
            } else {
                // '://' 后再无其他 '/'，那么整个 pattern 其实就是主机信息
                $this->host = $this->pattern;
            }
        } elseif (strncmp($this->pattern, '//', 2) === 0) {
            if (($pos2 = strpos($this->pattern, '/', 2)) !== false) {
                $this->host = substr($this->pattern, 0, $pos2);
            } else {
                $this->host = $this->pattern;
            }
        // pattern 不是空串，且不包含主机信息，两端加上 '/' ，形成一个正则
        } else {
            $this->pattern = '/' . $this->pattern . '/';
        }

        // route 中含有 <参数> ，则将所有参数提取成 [参数 => <参数>]
        // 存入 _routeParams[],
        // 如 ['controller' => '<controller>', 'action' => '<action>'],
        // 留意这里的短路判断，先使用 strpos()，快速排除无需使用正则的情况
        if (strpos($this->route, '<') !== false && preg_match_all('/<([\w._-]+)>/', $this->route, $matches)) {
            foreach ($matches[1] as $name) {
                $this->_routeParams[$name] = "<$name>";
            }
        }

        $this->translatePattern(true);
    }

    /**
     * Prepares [[$pattern]] on rule initialization - replace parameter names by placeholders.
     *
     * @param bool $allowAppendSlash Defines position of slash in the param pattern in [[$pattern]].
     * If `false` slash will be placed at the beginning of param pattern. If `true` slash position will be detected
     * depending on non-optional pattern part.
     */
    private function translatePattern($allowAppendSlash)
    {
        // 这个 $tr[] 和 $tr2[] 用于字符串的转换
        $tr = [
            '.' => '\\.',
            '*' => '\\*',
            '$' => '\\$',
            '[' => '\\[',
            ']' => '\\]',
            '(' => '\\(',
            ')' => '\\)',
        ];

        $tr2 = [];
        $requiredPatternPart = $this->pattern;
        $oldOffset = 0;

        // pattern 中含有 <参数名:参数pattern> ，
        // 其中 ':参数pattern' 部分是可选的。
        // eg： $this->pattern = '/<controller:\w+>/<id:\d+>/'
        if (preg_match_all('/<([\w._-]+):?([^>]+)?>/', $this->pattern, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            $appendSlash = false;
            /**
             *
                array(2) {
                    [0]=> array(3) {
                        [0]=> array(2) {
                            [0]=> string(16) "<controller:\w+>"
                            [1]=> int(1)
                        }
                        [1]=> array(2) {
                            [0]=> string(10) "controller"
                            [1]=> int(2)
                        }
                        [2]=> array(2) {
                            [0]=> string(3) "\w+"
                            [1]=> int(13)
                        }
                    }
                    [1]=> array(3) {
                        [0]=> array(2) {
                            [0]=> string(8) "<id:\d+>"
                            [1]=> int(18)
                        }
                        [1]=> array(2) {
                            [0]=> string(2) "id"
                            [1]=> int(19)
                        }
                        [2]=> array(2) {
                            [0]=> string(3) "\d+"
                            [1]=> int(22)
                        }
                    }
                }
             */
            foreach ($matches as $match) {
                // 获取 “参数名”
                $name = $match[1][0];
                // 获取 “参数pattern” ，如果未指定，使用 '[^\/]' ，
                // 表示匹配除 '/' 外的所有字符
                $pattern = isset($match[2][0]) ? $match[2][0] : '[^\/]+';
                $placeholder = 'a' . hash('crc32b', $name); // placeholder must begin with a letter
                $this->placeholders[$placeholder] = $name;
                // 如果 defaults[] 中有同名参数，
                if (array_key_exists($name, $this->defaults)) {
                    // $match[0][0] 是整个 <参数名:参数pattern> 串
                    $length = strlen($match[0][0]);
                    $offset = $match[0][1];
                    // pattern 中 <参数名:参数pattern> 两头都有 '/'
                    $requiredPatternPart = str_replace("/{$match[0][0]}/", '//', $requiredPatternPart);
                    if (
                        $allowAppendSlash
                        && ($appendSlash || $offset === 1)
                        && (($offset - $oldOffset) === 1)
                        && isset($this->pattern[$offset + $length])
                        && $this->pattern[$offset + $length] === '/'
                        && isset($this->pattern[$offset + $length + 1])
                    ) {
                        // if pattern starts from optional params, put slash at the end of param pattern
                        // @see https://github.com/yiisoft/yii2/issues/13086
                        $appendSlash = true;
                        $tr["<$name>/"] = "((?P<$placeholder>$pattern)/)?";
                    } elseif (
                        $offset > 1
                        && $this->pattern[$offset - 1] === '/'
                        && (!isset($this->pattern[$offset + $length]) || $this->pattern[$offset + $length] === '/')
                    ) {
                        $appendSlash = false;
                        // 留意这个 (?P<name>pattern) 正则，这是一个命名分组。
                        // 仅冠以一个命名供后续引用，使用上与直接的 (pattern) 没有区别
                        // 见：http://php.net/manual/en/regexp.reference.subpatterns.php
                        $tr["/<$name>"] = "(/(?P<$placeholder>$pattern))?";
                    }
                    $tr["<$name>"] = "(?P<$placeholder>$pattern)?";
                    $oldOffset = $offset + $length;
                } else {
                    $appendSlash = false;
                    // defaults[]中没有同名参数
                    $tr["<$name>"] = "(?P<$placeholder>$pattern)";
                }

                // routeParams[]中有同名参数
                if (isset($this->_routeParams[$name])) {
                    $tr2["<$name>"] = "(?P<$placeholder>$pattern)";

                // routeParams[]中没有同名参数，则将 参数pattern 存入 _paramRules[] 中。
                // 留意这里是怎么对  参数pattern  进行处理后再保存的。
                } else {
                    $this->_paramRules[$name] = $pattern === '[^\/]+' ? '' : "#^$pattern$#u";
                }
            }
        }

        // we have only optional params in route - ensure slash position on param patterns
        if ($allowAppendSlash && trim($requiredPatternPart, '/') === '') {
            $this->translatePattern(false);
            return;
        }

        // 将 pattern 中所有的 <参数名:参数pattern> 替换成 <参数名> 后作为 _template
        $this->_template = preg_replace('/<([\w._-]+):?([^>]+)?>/', '<$1>', $this->pattern);
        // 将 _template 中的特殊字符及字符串使用 tr[] 进行转换，并作为最终的pattern
        $this->pattern = '#^' . trim(strtr($this->_template, $tr), '/') . '$#u';

        // if host starts with relative scheme, then insert pattern to match any
        if (strncmp($this->host, '//', 2) === 0) {
            $this->pattern = substr_replace($this->pattern, '[\w]+://', 2, 0);
        }

        // 如果指定了 routePrams 还要使用 tr2[] 对 route 进行转换，
        // 并作为最终的 _routeRule
        if (!empty($this->_routeParams)) {
            $this->_routeRule = '#^' . strtr($this->route, $tr2) . '$#u';
        }
    }

    /**
     * 获取Url格式化方法。
     * @param UrlManager $manager the URL manager
     * @return UrlNormalizer|null
     * @since 2.0.10
     */
    protected function getNormalizer($manager)
    {
        if ($this->normalizer === null) {
            return $manager->normalizer;
        }

        return $this->normalizer;
    }

    /**
     * 判断是否存在Url格式化方法
     * @param UrlManager $manager the URL manager
     * @return bool
     * @since 2.0.10
     */
    protected function hasNormalizer($manager)
    {
        return $this->getNormalizer($manager) instanceof UrlNormalizer;
    }

    /**
     * // 用于解析请求，由UrlRequestInterface接口要求
     *
     * Parses the given request and returns the corresponding route and parameters.
     * @param UrlManager $manager the URL manager
     * @param Request $request the request component
     * @return array|bool the parsing result. The route and the parameters are returned as an array.
     * If `false`, it means this rule cannot be used to parse this path info.
     */
    public function parseRequest($manager, $request)
    {
        // 当前路由规则仅限于创建URL，直接返回 false。
        // 该方法返回false表示当前规则不适用于当前的URL。
        if ($this->mode === self::CREATION_ONLY) {
            return false;
        }

        // 如果规则定义了适用的HTTP方法，则要看当前请求采用的方法是否可以接受
        if (!empty($this->verb) && !in_array($request->getMethod(), $this->verb, true)) {
            return false;
        }

        // 取得配置的 .html 等假后缀，留意 (string)null 转成空串
        $suffix = (string) ($this->suffix === null ? $manager->suffix : $this->suffix);
        // 获取URL中入口脚本之后、查询参数 ? 号之前的全部内容，即为PATH_INFO
        $pathInfo = $request->getPathInfo();
        $normalized = false;
        // 判断是否有Url格式化方法
        if ($this->hasNormalizer($manager)) {
            // 如果有，调用该方法进行格式化
            $pathInfo = $this->getNormalizer($manager)->normalizePathInfo($pathInfo, $suffix, $normalized);
        }

        // 有假后缀且有PATH_INFO
        if ($suffix !== '' && $pathInfo !== '') {
            $n = strlen($suffix);
            // 当前请求的 PATH_INFO 以该假后缀结尾，留意 -$n 的用法
            if (substr_compare($pathInfo, $suffix, -$n, $n) === 0) {
                $pathInfo = substr($pathInfo, 0, -$n);
                // 整个PATH_INFO 仅包含一个假后缀，这是无效的。
                if ($pathInfo === '') {
                    // suffix alone is not allowed
                    return false;
                }
            // 应用配置了假后缀，但是当前URL却不包含该后缀，返回false
            } else {
                return false;
            }
        }

        // 规则定义了主机信息，即 http://www.digpage.com 之类，那要把主机信息接回去。
        if ($this->host !== null) {
            $pathInfo = strtolower($request->getHostInfo()) . ($pathInfo === '' ? '' : '/' . $pathInfo);
        }

        // 当前URL是否匹配规则，留意这个pattern是经过 init() 转换的
        if (!preg_match($this->pattern, $pathInfo, $matches)) {
            return false;
        }
        $matches = $this->substitutePlaceholderNames($matches);

        // 遍历规则定义的默认参数，如果当前URL中没有，则加入到 $matches 中待统一处理，
        // 默认值在这里发挥作用了，虽然没有，但仍视为捕获到了。
        foreach ($this->defaults as $name => $value) {
            if (!isset($matches[$name]) || $matches[$name] === '') {
                $matches[$name] = $value;
            }
        }
        $params = $this->defaults;
        $tr = [];

        // 遍历所有匹配项，注意这个 $name 的由来是 (?P<name>...) 的功劳
        foreach ($matches as $name => $value) {
            // 如果是匹配一个路由参数
            if (isset($this->_routeParams[$name])) {
                $tr[$this->_routeParams[$name]] = $value;
                unset($params[$name]);
            // 如果是匹配一个查询参数
            } elseif (isset($this->_paramRules[$name])) {
                // 这里可能会覆盖掉 $defaults 定义的默认值
                $params[$name] = $value;
            }
        }
        // 使用 $tr 进行转换
        if ($this->_routeRule !== null) {
            $route = strtr($this->route, $tr);
        } else {
            $route = $this->route;
        }

        Yii::debug("Request parsed with URL rule: {$this->name}", __METHOD__);

        if ($normalized) {
            // pathInfo was changed by normalizer - we need also normalize route
            return $this->getNormalizer($manager)->normalizeRoute([$route, $params]);
        }

        return [$route, $params];
    }

    /**
     *  // 用于生成URL，由UrlRequestInterface接口要求
     * Creates a URL according to the given route and parameters.
     * @param UrlManager $manager the URL manager
     * @param string $route the route. It should not have slashes at the beginning or the end.
     * @param array $params the parameters
     * @return string|bool the created URL, or `false` if this rule cannot be used for creating this URL.
     */
    public function createUrl($manager, $route, $params)
    {
        // 判断规则是否仅限于解析请求，而不适用于创建URL
        if ($this->mode === self::PARSING_ONLY) {
            $this->createStatus = self::CREATE_STATUS_PARSING_ONLY;
            return false;
        }

        $tr = [];

        // match the route part first
        // 如果传入的路由与规则定义的路由不一致，
        // 如 post/view 与 post/<action> 并不一致
        if ($route !== $this->route) {
            // 使用 $_routeRule 对 $route 作匹配测试
            if ($this->_routeRule !== null && preg_match($this->_routeRule, $route, $matches)) {
                $matches = $this->substitutePlaceholderNames($matches);
                // 遍历所有的 _routeParams
                foreach ($this->_routeParams as $name => $token) {
                    // 如果该路由规则提供了默认的路由参数，
                    // 且该参数值与传入的路由相同，则可以省略
                    if (isset($this->defaults[$name]) && strcmp($this->defaults[$name], $matches[$name]) === 0) {
                        $tr[$token] = '';
                    } else {
                        $tr[$token] = $matches[$name];
                    }
                }
            // 传入的路由完全不能匹配该规则，返回
            } else {
                $this->createStatus = self::CREATE_STATUS_ROUTE_MISMATCH;
                return false;
            }
        }

        // match default params
        // if a default param is not in the route pattern, its value must also be matched
        // 遍历所有的默认参数
        foreach ($this->defaults as $name => $value) {
            // 如果默认参数是路由参数，如 <action>
            if (isset($this->_routeParams[$name])) {
                continue;
            }
            // 默认参数并非路由参数，那么看看传入的 $params 里是否提供该参数的值。
            // 如果未提供，说明这个规则不适用，直接返回。
            if (!isset($params[$name])) {
                // allow omit empty optional params
                // @see https://github.com/yiisoft/yii2/issues/10970
                if (in_array($name, $this->placeholders) && strcmp($value, '') === 0) {
                    $params[$name] = '';
                } else {
                    $this->createStatus = self::CREATE_STATUS_PARAMS_MISMATCH;
                    return false;
                }
            }
            // 如果 $params 提供了该参数，且参数值一致，则 $params 可省略该参数
            if (strcmp($params[$name], $value) === 0) { // strcmp will do string conversion automatically
                unset($params[$name]);
                // 且如果有该参数的转换规则，也可置为空。等下一转换就消除了。
                if (isset($this->_paramRules[$name])) {
                    $tr["<$name>"] = '';
                }
            // 如果 $params 提供了该参数，但又与默认参数值不一致，
            // 且规则也未定义该参数的正则，那么规则无法处理这个参数。
            } elseif (!isset($this->_paramRules[$name])) {
                $this->createStatus = self::CREATE_STATUS_PARAMS_MISMATCH;
                return false;
            }
        }

        // match params in the pattern
        // 遍历所有的参数匹配规则
        foreach ($this->_paramRules as $name => $rule) {
            // 如果 $params 传入了同名参数，且该参数不是数组，且该参数匹配规则，
            // 则使用该参数匹配规则作为转换规则，并从 $params 中去掉该参数
            if (isset($params[$name]) && !is_array($params[$name]) && ($rule === '' || preg_match($rule, $params[$name]))) {
                $tr["<$name>"] = $this->encodeParams ? urlencode($params[$name]) : $params[$name];
                unset($params[$name]);

            // 否则一旦没有设置该参数的默认值或 $params 提供了该参数，
            // 说明规则又不匹配了
            } elseif (!isset($this->defaults[$name]) || isset($params[$name])) {
                $this->createStatus = self::CREATE_STATUS_PARAMS_MISMATCH;
                return false;
            }
        }

        // 使用 $tr 对 $_template 时行转换，并去除多余的 '/'
        $url = $this->trimSlashes(strtr($this->_template, $tr));
        if ($this->host !== null) {
            // 将 $url 中的多个 '/' 变成一个
            $pos = strpos($url, '/', 8);
            if ($pos !== false) {
                $url = substr($url, 0, $pos) . preg_replace('#/+#', '/', substr($url, $pos));
            }
        } elseif (strpos($url, '//') !== false) {
            $url = preg_replace('#/+#', '/', trim($url, '/'));
        }

        // 加上 .html 之类的假后缀
        if ($url !== '') {
            $url .= ($this->suffix === null ? $manager->suffix : $this->suffix);
        }

        // 加上查询参数们
        if (!empty($params) && ($query = http_build_query($params)) !== '') {
            $url .= '?' . $query;
        }

        $this->createStatus = self::CREATE_STATUS_SUCCESS;
        return $url;
    }

    /**
     * 返回 匹配参数的正则表达式列表
     * Returns status of the URL creation after the last [[createUrl()]] call.
     *
     * @return null|int Status of the URL creation after the last [[createUrl()]] call. `null` if rule does not provide
     * info about create status.
     * @see $createStatus
     * @since 2.0.12
     */
    public function getCreateUrlStatus()
    {
        return $this->createStatus;
    }

    /**
     * Returns list of regex for matching parameter.
     * @return array parameter keys and regexp rules.
     *
     * @since 2.0.6
     */
    protected function getParamRules()
    {
        return $this->_paramRules;
    }

    /**
     * 遍历占位符[[placeholders]]并检查每个占位符是否作为$matches数组中的一个键。
     * 当存在时 —— 用匹配参数的适当名称替换这个占位符键。
     * 这个方法在[[parseRequest()]], [[createUrl()]]中使用。
     *
     * Iterates over [[placeholders]] and checks whether each placeholder exists as a key in $matches array.
     * When found - replaces this placeholder key with a appropriate name of matching parameter.
     * Used in [[parseRequest()]], [[createUrl()]].
     *
     * @param array $matches result of `preg_match()` call
     * @return array input array with replaced placeholder keys
     * @see placeholders
     * @since 2.0.7
     */
    protected function substitutePlaceholderNames(array $matches)
    {
        foreach ($this->placeholders as $placeholder => $name) {
            if (isset($matches[$placeholder])) {
                $matches[$name] = $matches[$placeholder];
                unset($matches[$placeholder]);
            }
        }

        return $matches;
    }

    /**
     * Trim slashes in passed string. If string begins with '//', two slashes are left as is
     * in the beginning of a string.
     *
     * @param string $string
     * @return string
     */
    private function trimSlashes($string)
    {
        // 删除 pattern 两端的 "/"，特别是重复的 "/"，
        // 在写 pattern 时，虽然有正则的成分，但不需要在两端加上 "/"，
        // 更不能加上 "#" 等其他分隔符
        if (strncmp($string, '//', 2) === 0) {
            return '//' . trim($string, '/');
        }

        return trim($string, '/');
    }
}
