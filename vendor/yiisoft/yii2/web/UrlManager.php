<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\web;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\caching\CacheInterface;
use yii\helpers\Url;

/**
 * UrlManager根据一组规则处理HTTP请求解析和创建url
 * UrlManager handles HTTP request parsing and creation of URLs based on a set of rules.
 *
 * UrlManager 默认情况下是在应用程序组件 [[\yii\base\Application]] 中配置。
 * 您可以通过Yii::$app->urlManager访问该实例。
 * UrlManager is configured as an application component in [[\yii\base\Application]] by default.
 * You can access that instance via `Yii::$app->urlManager`.
 *
 * You can modify its configuration by adding an array to your application config under `components`
 * as it is shown in the following example:
 *
 * 您可以通过向您的应用程序配置中添加一个数组来修改它的配置，如下示例。
 * ```php
 * 'urlManager' => [
 *     'enablePrettyUrl' => true,
 *     'rules' => [
 *         // your rules go here
 *     ],
 *     // ...
 * ]
 * ```
 *
 * 由[[createUrl()]]使用的基本URL，用于创建URL
 * Rules are classes implementing the [[UrlRuleInterface]], by default that is [[UrlRule]].
 * For nesting rules, there is also a [[GroupUrlRule]] class.
 *
 * For more details and usage information on UrlManager, see the [guide article on routing](guide:runtime-routing).
 *
 * @property string $baseUrl The base URL that is used by [[createUrl()]] to prepend to created URLs.
 * 
 * 主机信息(如"http://www.example.com")，它被[[createAbsoluteUrl()]]用于创建url
 * @property string $hostInfo The host info (e.g. `http://www.example.com`) that is used by
 * [[createAbsoluteUrl()]] to prepend to created URLs.
 * @property string $scriptUrl The entry script URL that is used by [[createUrl()]] to prepend to created
 * URLs.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class UrlManager extends Component
{
    /**
     * 是否启用美化URL
     * 相对于将所有参数放入URL的查询字符串部分，美化URL允许使用路径信息来表示一些参数，从而生成更友好的URL.
     * 例如将 "/index.php?r=news%2Fview&id=100" 替换为 "/news/Yii-is-released"
     * @var bool whether to enable pretty URLs. Instead of putting all parameters in the query
     * string part of a URL, pretty URLs allow using path info to represent some of the parameters
     * and can thus produce more user-friendly URLs, such as "/news/Yii-is-released", instead of
     * "/index.php?r=news%2Fview&id=100".
     *
     * 用于表明urlManager是否启用URL美化功能，在Yii1.1中称为path格式URL，Yii2.0中改称美化。
     * 默认不启用。但实际使用中，特别是产品环境，一般都会启用。
     */
    public $enablePrettyUrl = false;
    /**
     * @var bool whether to enable strict parsing. If strict parsing is enabled, the incoming
     * requested URL must match at least one of the [[rules]] in order to be treated as a valid request.
     * Otherwise, the path info part of the request will be treated as the requested route.
     * This property is used only when [[enablePrettyUrl]] is `true`.
     *
     * 是否启用严格解析，如启用严格解析，要求当前请求应至少匹配1个路由规则，
     * 否则认为是无效路由。
     * 这个选项仅在 enablePrettyUrl 启用后才有效。
     *
     */
    public $enableStrictParsing = false;
    /**
     * 创建和解析url的规则，当启用[[enablePrettyUrl]]时生效。
     * 数组中的每个元素都是用于创建单个URL规则的配置数组。
     * 在用于创建规则对象之前，该配置数组将首先与[[ruleConfig]]合并。
     *
     * @var array the rules for creating and parsing URLs when [[enablePrettyUrl]] is `true`.
     * This property is used only if [[enablePrettyUrl]] is `true`. Each element in the array
     * is the configuration array for creating a single URL rule. The configuration will
     * be merged with [[ruleConfig]] first before it is used for creating the rule object.
     *
     * 如果一条规则只指定[[UrlRule::pattern|pattern]] and [[UrlRule::route|route]]: `'pattern' => 'route'`，将会被认为是一种特殊的快捷方式.
     * 也就是说，除了使用配置数组，还可以使用关键字来表示匹配模式和相应路由的值。
     * 例如 `'post/<id:\d+>' => 'post/view'`：id 是命名参数，post/100 形式的URL，其实是 post/view&id=100
     * 
     * A special shortcut format can be used if a rule only specifies [[UrlRule::pattern|pattern]]
     * and [[UrlRule::route|route]]: `'pattern' => 'route'`. That is, instead of using a configuration
     * array, one can use the key to represent the pattern and the value the corresponding route.
     * For example, `'post/<id:\d+>' => 'post/view'`.
     *
     * 对于RESTful路由，前面提到的快捷方式还允许您指定规则应该应用于哪种HTTP方法[[UrlRule::verb|HTTP verb]]。
     * 你可以通过将它添加到匹配模式前来实现，以空格分隔。
     * 例如：`'PUT post/<id:\d+>' => 'post/update'`
     * 你还可以通过用逗号分隔它们来指定多个动词：`'POST,PUT post/index' => 'post/create'`。
     * 在快捷方式中支持的动词有： GET, HEAD, POST, PUT, PATCH and DELETE.
     * 注意，当以这种方式指定动词时，[[UrlRule::mode|mode]]将被设置为 PARSING_ONLY，因此通常不会为普通GET请求指定一个动词。
     *
     * For RESTful routing the mentioned shortcut format also allows you to specify the
     * [[UrlRule::verb|HTTP verb]] that the rule should apply for.
     * You can do that  by prepending it to the pattern, separated by space.
     * For example, `'PUT post/<id:\d+>' => 'post/update'`.
     * You may specify multiple verbs by separating them with comma
     * like this: `'POST,PUT post/index' => 'post/create'`.
     * The supported verbs in the shortcut format are: GET, HEAD, POST, PUT, PATCH and DELETE.
     * Note that [[UrlRule::mode|mode]] will be set to PARSING_ONLY when specifying verb in this way
     * so you normally would not specify a verb for normal GET request.
     *
     * Here is an example configuration for RESTful CRUD controller:
     *
     * ```php
     * [
     *      // 为路由指定了一个别名，以 dashboard 表示 site/index 路由
     *     'dashboard' => 'site/index',
     *
     *      // id 是命名参数，post/100 形式的URL，其实是 post/view&id=100
     *     'post/<id:\d+>' => 'post/view',
     *
     *     'POST <controller:[\w-]+>s' => '<controller>/create',
     *     '<controller:[\w-]+>s' => '<controller>/index',
     *
     *     'PUT <controller:[\w-]+>/<id:\d+>'    => '<controller>/update',
     *
     *      // 包含了 HTTP 方法限定，仅限于DELETE方法
     *     'DELETE <controller:[\w-]+>/<id:\d+>' => '<controller>/delete',
     *     '<controller:[\w-]+>/<id:\d+>'        => '<controller>/view',
     * ];
     * ```
     *
     * 注意，如果在创建UrlManager对象之后修改该属性，请确保使用规则对象而不是规则配置填充该数组。
     * Note that if you modify this property after the UrlManager object is created, make sure
     * you populate the array with rule objects instead of rule configurations.
     *
     * 用于为urlManager声明路由规则。
     * 数组的键相当于请求（需要解析的或将要生成的），而元素的值则对应的路由， 即 controller/action 。
     * 请求部分可称为pattern，路由部分则可称为route。 对于这2个部分的形式，大致上可以这么看：

        - pattern 是从正则表达式变形而来。去除了两端的 / # 等分隔符。 特别注意别在pattern两端画蛇添足加上分隔符。
        - pattern 中可以使用正则表达式的命名参数，以供route部分引用。
     *    这个命名参数也是变形了的。 对于原来 (?P<name>pattern) 的命名参数，要变形成 <name:pattern> 。
        - pattern 中可以使用HTTP方法限定。
        - route 不应再含有正则表达式，但是可以按 <name> 的形式引用命名参数。
     *
        也就是说，解析请求时，Yii从左往右使用这个数组；而生成URL时Yii从右往左使用这个数组。
     *
     * 保存所有路由规则的配置数组，并不在这里保存路由规则的实例
     */
    public $rules = [];
    /**
     * 启用[[enablePrettyUrl]]时可以使用的URL后缀。
     * 例如,可以使用'.html'后缀，让URL看起来就像是指向一个静态html页面。
     * @var string the URL suffix used when [[enablePrettyUrl]] is `true`.
     * For example, ".html" can be used so that the URL looks like pointing to a static HTML page.
     * This property is used only if [[enablePrettyUrl]] is `true`.
     */
    public $suffix;
    /**
     * @var bool whether to show entry script name in the constructed URL. Defaults to `true`.
     * This property is used only if [[enablePrettyUrl]] is `true`.
     *
     * 指定URL是否保留入口脚本 index.php
     */
    public $showScriptName = true;
    /**
     * @var string the GET parameter name for route. This property is used only if [[enablePrettyUrl]] is `false`.
     * // 指定不启用 enablePrettyUrl 情况下，URL中用于表示路由的查询参数，默认为 r
     */
    public $routeParam = 'r';
    /**
     * @var CacheInterface|string the cache object or the application component ID of the cache object.
     * Compiled URL rules will be cached through this cache object, if it is available.
     *
     * 指定应用的缓存组件ID，编译过的路由规则将通过这个缓存组件进行缓存。
     * 由于应用的缓存组件默认为 cache ，所以这里也默认为 cache 。
     * 如果不想使用缓存，需显式地置为 false
     * 
     * After the UrlManager object is created, if you want to change this property,
     * you should only assign it with a cache object.
     * Set this property to `false` if you do not want to cache the URL rules.
     *
     * Cache entries are stored for the time set by [[\yii\caching\Cache::$defaultDuration|$defaultDuration]] in
     * the cache configuration, which is unlimited by default. You may want to tune this value if your [[rules]]
     * change frequently.
     */
    public $cache = 'cache';
    /**
     * @var array the default configuration of URL rules. Individual rule configurations
     * specified via [[rules]] will take precedence when the same property of the rule is configured.
     *
     * // 路由规则的默认配置，注意上面的 rules[] 中的同名规则，优先于这个默认配置的规则。
     */
    public $ruleConfig = ['class' => 'yii\web\UrlRule'];
    /**
     * [[UrlNormalizer]]的配置.
     * 默认值是false，这意味着将跳过规范化。
     * 如果希望启用URL规范化，则应该手动配置该属性。
     * @var UrlNormalizer|array|string|false the configuration for [[UrlNormalizer]] used by this UrlManager.
     * The default value is `false`, which means normalization will be skipped.
     * If you wish to enable URL normalization, you should configure this property manually.
     * For example:
     *
     * ```php
     * [
     *     'class' => 'yii\web\UrlNormalizer',
     *     'collapseSlashes' => true,
     *     'normalizeTrailingSlash' => true,
     * ]
     * ```
     *
     * @since 2.0.10
     */
    public $normalizer = false;

    /**
     * 缓存规则的缓存键
     * @var string the cache key for cached rules
     * @since 2.0.8
     */
    protected $cacheKey = __CLASS__;

    private $_baseUrl;
    private $_scriptUrl;
    private $_hostInfo;
    private $_ruleCache;


    /**
     * Initializes UrlManager.
     * urlManager 初始化
     */
    public function init()
    {
        parent::init();

        if ($this->normalizer !== false) {
            $this->normalizer = Yii::createObject($this->normalizer);
            if (!$this->normalizer instanceof UrlNormalizer) {
                throw new InvalidConfigException('`' . get_class($this) . '::normalizer` should be an instance of `' . UrlNormalizer::className() . '` or its DI compatible configuration.');
            }
        }

        // 如果未启用 enablePrettyUrl 或者没有指定任何的路由规则，
        // 这个urlManager不需要进一步初始化。
        if (!$this->enablePrettyUrl) {
            return;
        }
        // 初始化前， $this->cache 是缓存组件的ID，是个字符串，需要获取其实例。
        if (is_string($this->cache)) {
            // 如果获取不到实例，说明应用不提供缓存功能，
            // 那么置这个 $this->cache 为false
            $this->cache = Yii::$app->get($this->cache, false);
        }
        if (empty($this->rules)) {
            return;
        }
        $this->rules = $this->buildRules($this->rules);
    }

    /**
     * 增加了额外的URL规则
     * Adds additional URL rules.
     *
     * 该方法将调用[[buildRules()]]来解析给定的规则声明，然后将它们附加或插入到现有规则[[rules]]中
     * This method will call [[buildRules()]] to parse the given rule declarations and then append or insert
     * them to the existing [[rules]].
     *
     * 注意，如果没有启用[[enablePrettyUrl]]，此方法无效。
     * Note that if [[enablePrettyUrl]] is `false`, this method will do nothing.
     *
     * 要添加的新规则。每个数组元素表示一个规则声明。请参阅 [[rules]] 获取有效的规则格式。
     * @param array $rules the new rules to be added. Each array element represents a single rule declaration.
     * Please refer to [[rules]] for the acceptable rule format.
     *
     * 是否将新规则附加到现有规则的末尾，否则将添加到现有规则的开头。
     * @param bool $append whether to add the new rules by appending them to the end of the existing rules.
     */
    public function addRules($rules, $append = true)
    {
        // 如果没有启用[[enablePrettyUrl]]，此方法无效
        if (!$this->enablePrettyUrl) {
            return;
        }
        // 从给定的规则声明构建URL规则对象
        $rules = $this->buildRules($rules);
        if ($append) {
            // 将新规则附加到现有规则的末尾
            $this->rules = array_merge($this->rules, $rules);
        } else {
            // 将新规则添加到现有规则的开头。
            $this->rules = array_merge($rules, $this->rules);
        }
    }

    /**
     * 从给定的规则声明构建URL规则对象
     * Builds URL rule objects from the given rule declarations.
     *
     * 规则声明，每个数组元素表示一个规则声明。请参阅 [[rules]] 获取有效的规则格式。
     * @param array $ruleDeclarations the rule declarations. Each array element represents a single rule declaration.
     * Please refer to [[rules]] for the acceptable rule formats.
     *
     * 返回 根据给定规则声明构建的规则对象
     * @return UrlRuleInterface[] the rule objects built from the given rule declarations
     * @throws InvalidConfigException if a rule declaration is invalid
     */
    protected function buildRules($ruleDeclarations)
    {
        $builtRules = $this->getBuiltRulesFromCache($ruleDeclarations);
        if ($builtRules !== false) {
            return $builtRules;
        }

        $builtRules = [];
        // PHP 方法动词
        $verbs = 'GET|HEAD|POST|PUT|PATCH|DELETE|OPTIONS';
        // 遍历 rules 数组
        foreach ($ruleDeclarations as $key => $rule) {
            // rule是string
            if (is_string($rule)) {
                $rule = ['route' => $rule];
                /**
                 * 正则表达式： "/^((?:(GET|HEAD|POST|PUT|PATCH|DELETE|OPTIONS),)*(GET|HEAD|POST|PUT|PATCH|DELETE|OPTIONS))\\s+(.*)$/"
                 * (?:pattern)： 非获取匹配，匹配pattern但不获取匹配结果，不进行存储供以后使用。
                 * 这在使用或字符“(|)”来组合一个模式的各个部分时很有用。例如“industr(?:y|ies)”就是一个比“industry|industries”更简略的表达式。
                 *
                 * 其中：
                 * "(?:(GET|HEAD|POST|PUT|PATCH|DELETE|OPTIONS),)*" 匹配0个或多个类似 "POST," 的字符，
                 * 即匹配$key前面包含多种HTTP动词的情况："POST,PUT,PATCH <controller:[\w-]+>"
                 *
                 * "\s+" 匹配一个或多个空白字符，即匹配HTTP动词后面的空格。
                 */
                if (preg_match("/^((?:($verbs),)*($verbs))\\s+(.*)$/", $key, $matches)) {
                    // 将匹配到的HTTP动词分割成数组
                    $rule['verb'] = explode(',', $matches[1]);
                    // rules that are not applicable for GET requests should not be used to create URLs
                    // 不适用于GET请求的规则不应该用于创建url
                    if (!in_array('GET', $rule['verb'], true)) {
                        // 标记该规则仅用于URL解析
                        $rule['mode'] = UrlRule::PARSING_ONLY;
                    }
                    $key = $matches[4];
                }
                $rule['pattern'] = $key;
                /**
                 * 一套下来，$rule变成了
                 * $rule => [
                 *      'route' => 'controller/action',
                 *      'verb'  => ['POST', 'PUT', 'PATCH'],
                 *      'mode'  => UrlRule::PARSING_ONLY,
                 *      'pattern' => '<controller:[\w-]+>',
                 * ]
                 */
            }

            // rule是配置数组
            if (is_array($rule)) {
                // 这里根据配置数组创建UrlRule对象
                // 创建对象前，先将$rule与ruleConfig合并。
                // 当键名相同时，$rule 会覆盖 ruleConfig的元素。
                $rule = Yii::createObject(array_merge($this->ruleConfig, $rule));
            }
            // 判断UrlRule对象是否实现 UrlRuleInterface 接口
            if (!$rule instanceof UrlRuleInterface) {
                throw new InvalidConfigException('URL rule class must implement UrlRuleInterface.');
            }
            $builtRules[] = $rule;
        }

        $this->setBuiltRulesCache($ruleDeclarations, $builtRules);

        return $builtRules;
    }

    /**
     * Stores $builtRules to cache, using $rulesDeclaration as a part of cache key.
     *
     * @param array $ruleDeclarations the rule declarations. Each array element represents a single rule declaration.
     * Please refer to [[rules]] for the acceptable rule formats.
     * @param UrlRuleInterface[] $builtRules the rule objects built from the given rule declarations.
     * @return bool whether the value is successfully stored into cache
     * @since 2.0.14
     */
    protected function setBuiltRulesCache($ruleDeclarations, $builtRules)
    {
        if (!$this->cache instanceof CacheInterface) {
            return false;
        }

        return $this->cache->set([$this->cacheKey, $this->ruleConfig, $ruleDeclarations], $builtRules);
    }

    /**
     * Provides the built URL rules that are associated with the $ruleDeclarations from cache.
     *
     * @param array $ruleDeclarations the rule declarations. Each array element represents a single rule declaration.
     * Please refer to [[rules]] for the acceptable rule formats.
     * @return UrlRuleInterface[]|false the rule objects built from the given rule declarations or boolean `false` when
     * there are no cache items for this definition exists.
     * @since 2.0.14
     */
    protected function getBuiltRulesFromCache($ruleDeclarations)
    {
        if (!$this->cache instanceof CacheInterface) {
            return false;
        }

        return $this->cache->get([$this->cacheKey, $this->ruleConfig, $ruleDeclarations]);
    }

    /**
     * 解析用户请求
     *
     * Parses the user request.
     * @param Request $request the request component
     * @return array|bool the route and the associated parameters. The latter is always empty
     * if [[enablePrettyUrl]] is `false`. `false` is returned if the current request cannot be successfully parsed.
     *
     * 返回值：路径和相关的参数。
     * 如果 [[enablePrettyUrl]] is `false`则后者总是空的。
     * 如果当前的请求不能被成功解析则返回false
     *
     */
    public function parseRequest($request)
    {
        // 启用了 enablePrettyUrl 的情况
        if ($this->enablePrettyUrl) {
            /* @var $rule UrlRule */
            // 依次使用所有路由规则来解析当前请求
            // 一旦有一个规则适用，后面的规则就没有被调用的机会了
            foreach ($this->rules as $rule) {
                $result = $rule->parseRequest($this, $request);
                if (YII_DEBUG) {
                    Yii::debug([
                        'rule' => method_exists($rule, '__toString') ? $rule->__toString() : get_class($rule),
                        'match' => $result !== false,
                        'parent' => null,
                    ], __METHOD__);
                }
                if ($result !== false) {
                    return $result;
                }
            }

            // 所有路由规则都不适用，又启用了 enableStrictParsing ，
            // 那只能返回 false  了。
            if ($this->enableStrictParsing) {
                return false;
            }

            // 所有路由规则都不适用，幸好还没启用 enableStrictParing，
            // 那就用默认的解析逻辑
            Yii::debug('No matching URL rules. Using default URL parsing logic.', __METHOD__);

            // 配置时所定义的fake suffix，诸如 ".html" 等
            $suffix = (string) $this->suffix;
            // 获取路径信息
            $pathInfo = $request->getPathInfo();
            $normalized = false;
            if ($this->normalizer !== false) {
                //  格式化指定的路由
                $pathInfo = $this->normalizer->normalizePathInfo($pathInfo, $suffix, $normalized);
            }
            if ($suffix !== '' && $pathInfo !== '') {
                // 这个分支的作用在于确保 $pathInfo 不能仅仅是包含一个 ".html"。
                $n = strlen($this->suffix);
                // 留意这个 -$n 的用法
                if (substr_compare($pathInfo, $this->suffix, -$n, $n) === 0) {
                    $pathInfo = substr($pathInfo, 0, -$n);
                    // 仅包含 ".html" 的$pathInfo要之何用？掐死算了。
                    if ($pathInfo === '') {
                        // suffix alone is not allowed
                        return false;
                    }
                } else {
                    // 后缀没匹配上
                    // suffix doesn't match
                    return false;
                }
            }

            if ($normalized) {
                // pathInfo was changed by normalizer - we need also normalize route
                // pathInfo被normalizer 修改了，我们还需要规范化路由
                return $this->normalizer->normalizeRoute([$pathInfo, []]);
            }

            return [$pathInfo, []];
        }
        // 没有启用 enablePrettyUrl的情况，那就更简单了，
        // 直接使用默认的解析逻辑就OK了
        Yii::debug('Pretty URL not enabled. Using default URL parsing logic.', __METHOD__);
        // 获取路由 r=site/index
        $route = $request->getQueryParam($this->routeParam, '');
        if (is_array($route)) {
            $route = '';
        }

        return [(string) $route, []];
    }

    /**
     * 使用给定的路由和查询参数创建一个URL
     * Creates a URL using the given route and query parameters.
     *
     * 您可以将该路径指定为字符串，`site/index`
     * 也可以使用数组创建附加查询参数的URL。
     * You may specify the route as a string, e.g., `site/index`. You may also use an array
     * if you want to specify additional query parameters for the URL being created. The
     * array format must be:
     *
     * ```php
     * // generates: /index.php?r=site%2Findex&param1=value1&param2=value2
     * ['site/index', 'param1' => 'value1', 'param2' => 'value2']
     * ```
     * 如果您想要创建一个带有锚的URL，您可以使用带有参数 ‘#’ 的数组格式
     * If you want to create a URL with an anchor, you can use the array format with a `#` parameter.
     * For example,
     *
     * ```php
     * // generates: /index.php?r=site%2Findex&param1=value1#name
     * ['site/index', 'param1' => 'value1', '#' => 'name']
     * ```
     *
     * 创建的URL是相对的，适应[[createAbsoluteUrl()]]创建绝对路径
     * The URL created is a relative one. Use [[createAbsoluteUrl()]] to create an absolute URL.
     *
     * 注意此方法不像[[\yii\helpers\Url::toRoute()]], 这个方法总是把给定的路径当作绝对路径
     * Note that unlike [[\yii\helpers\Url::toRoute()]], this method always treats the given route
     * as an absolute route.
     *
     * @param string|array $params use a string to represent a route (e.g. `site/index`),
     * or an array to represent a route with query parameters (e.g. `['site/index', 'param1' => 'value1']`).
     * @return string the created URL
     */
    public function createUrl($params)
    {
        $params = (array) $params;
        // 锚点
        $anchor = isset($params['#']) ? '#' . $params['#'] : '';
        // 删除锚点和路由参数（如果存在的话）
        unset($params['#'], $params[$this->routeParam]);

        // 去掉路由左侧的 '/'
        $route = trim($params[0], '/');
        // 删除路由
        unset($params[0]);

        // 设置显示入口脚本，或者没有设置美化Url,获取入口脚本Url（'/index.php'），否则获取基础URL('')
        $baseUrl = $this->showScriptName || !$this->enablePrettyUrl ? $this->getScriptUrl() : $this->getBaseUrl();

        // 启用了美化URL
        if ($this->enablePrettyUrl) {
            // 缓存key
            $cacheKey = $route . '?';
            // 遍历参数，拼接到$cacheKey后
            foreach ($params as $key => $value) {
                if ($value !== null) {
                    $cacheKey .= $key . '&';
                }
            }
            // 如果存在，就从内部缓存获取URL
            $url = $this->getUrlFromCache($cacheKey, $route, $params);

            // 缓存中不存在$url
            if ($url === false) {
                /* @var $rule UrlRule */
                // 遍历URL规则
                foreach ($this->rules as $rule) {
                    if (in_array($rule, $this->_ruleCache[$cacheKey], true)) {
                        // avoid redundant calls of `UrlRule::createUrl()` for rules checked in `getUrlFromCache()`
                        // @see https://github.com/yiisoft/yii2/issues/14094
                        continue;
                    }
                    // 使用Url规则创建URL
                    $url = $rule->createUrl($this, $route, $params);
                    if ($this->canBeCached($rule)) {
                        // 将规则进行缓存
                        $this->setRuleToCache($cacheKey, $rule);
                    }
                    if ($url !== false) {
                        break;
                    }
                }
            }

            if ($url !== false) {
                // url中存在'://'，说明url中包含协议信息
                if (strpos($url, '://') !== false) {
                    /**
                     * strpos($url, '/', 8) !== false
                     * 从字符串第九为开始向后查找'/',返回'/'第一次出现的位置。
                     * 这样是为了跳过前面的协议信息 'http://', 'https://'等。
                     * 判断$url是否包含路由信息
                     *
                     * $baseUrl 不为空字符串，且$url后面包含路由
                     */
                    if ($baseUrl !== '' && ($pos = strpos($url, '/', 8)) !== false) {
                        // 将 $baseUrl 插入到 主机地址和路由中间，将锚点信息拼接到最后
                        return substr($url, 0, $pos) . $baseUrl . substr($url, $pos) . $anchor;
                    }
                    // 直接将 $baseUrl 和 锚点信息拼接到 $url 后面
                    return $url . $baseUrl . $anchor;
                } elseif (strpos($url, '//') === 0) {
                    if ($baseUrl !== '' && ($pos = strpos($url, '/', 2)) !== false) {
                        return substr($url, 0, $pos) . $baseUrl . substr($url, $pos) . $anchor;
                    }

                    // 将 $baseUrl 拼接到 $url 和 锚点前
                    // eg: /index.php/weibo/create?account_open_id=1906604475#2
                    return $url . $baseUrl . $anchor;
                }

                $url = ltrim($url, '/');
                return "$baseUrl/{$url}{$anchor}";
            }

            // 若$url依然为false，继续往下执行

            // 添加后缀
            if ($this->suffix !== null) {
                $route .= $this->suffix;
            }
            // 若参数不为空，则在url后拼接参数
            if (!empty($params) && ($query = http_build_query($params)) !== '') {
                $route .= '?' . $query;
            }

            $route = ltrim($route, '/');
            return "$baseUrl/{$route}{$anchor}";
        }

        // 未启用美化URL

        // $url = "/index.php?r=controller/action"
        $url = "$baseUrl?{$this->routeParam}=" . urlencode($route);
        // 若参数不为空，则在url后拼接参数
        if (!empty($params) && ($query = http_build_query($params)) !== '') {
            $url .= '&' . $query;
        }

        // 将锚点拼接到$url后
        return $url . $anchor;
    }

    /**
     * Returns the value indicating whether result of [[createUrl()]] of rule should be cached in internal cache.
     *
     * @param UrlRuleInterface $rule
     * @return bool `true` if result should be cached, `false` if not.
     * @since 2.0.12
     * @see getUrlFromCache()
     * @see setRuleToCache()
     * @see UrlRule::getCreateUrlStatus()
     */
    protected function canBeCached(UrlRuleInterface $rule)
    {
        return
            // if rule does not provide info about create status, we cache it every time to prevent bugs like #13350
            // @see https://github.com/yiisoft/yii2/pull/13350#discussion_r114873476
            !method_exists($rule, 'getCreateUrlStatus') || ($status = $rule->getCreateUrlStatus()) === null
            || $status === UrlRule::CREATE_STATUS_SUCCESS
            || $status & UrlRule::CREATE_STATUS_PARAMS_MISMATCH;
    }

    /**
     * 如果存在，就从内部缓存获取URL
     * Get URL from internal cache if exists.
     * 生成的用来存储数据的缓存的键
     * @param string $cacheKey generated cache key to store data.
     * 路径
     * @param string $route the route (e.g. `site/index`).
     * @param array $params rule params.
     * @return bool|string the created URL
     * @see createUrl()
     * @since 2.0.8
     */
    protected function getUrlFromCache($cacheKey, $route, $params)
    {
        if (!empty($this->_ruleCache[$cacheKey])) {
            // 存在该缓存且不为空,遍历该缓存的url规则
            foreach ($this->_ruleCache[$cacheKey] as $rule) {

                /**
                 * 使用$rule创建URL
                 * @var $rule UrlRule
                 */
                if (($url = $rule->createUrl($this, $route, $params)) !== false) {
                    return $url;
                }
            }
        } else {
            // 不存在该缓存，则置空，并返回false
            $this->_ruleCache[$cacheKey] = [];
        }

        return false;
    }

    /**
     * 存储规则(例如，[[UrlRule]])到内部缓存
     * Store rule (e.g. [[UrlRule]]) to internal cache.
     * @param $cacheKey
     * @param UrlRuleInterface $rule
     * @since 2.0.8
     */
    protected function setRuleToCache($cacheKey, UrlRuleInterface $rule)
    {
        $this->_ruleCache[$cacheKey][] = $rule;
    }

    /**
     * 使用给定的路由和查询参数创建一个绝对URL
     * Creates an absolute URL using the given route and query parameters.
     *
     * 这个方法优先考虑使用createUrl()和hostInfo创建URL
     * This method prepends the URL created by [[createUrl()]] with the [[hostInfo]].
     *
     * 注意此方法不像[[\yii\helpers\Url::toRoute()]], 这个方法总是把给定的路径当作绝对路径
     * Note that unlike [[\yii\helpers\Url::toRoute()]], this method always treats the given route
     * as an absolute route.
     *
     * @param string|array $params use a string to represent a route (e.g. `site/index`),
     * or an array to represent a route with query parameters (e.g. `['site/index', 'param1' => 'value1']`).
     * @param string|null $scheme the scheme to use for the URL (either `http`, `https` or empty string
     * for protocol-relative URL).
     * If not specified the scheme of the current request will be used.
     * @return string the created URL
     * @see createUrl()
     */
    public function createAbsoluteUrl($params, $scheme = null)
    {
        // 强制转换为数组
        $params = (array) $params;
        // 使用给定的路由和查询参数创建一个URL
        $url = $this->createUrl($params);
        // 若$url中不存在'://'，则一定不是绝对路径
        if (strpos($url, '://') === false) {
            // 获取主机地址（包含协议信息），拼接到$url前
            $hostInfo = $this->getHostInfo();
            if (strpos($url, '//') === 0) {
                $url = substr($hostInfo, 0, strpos($hostInfo, '://')) . ':' . $url;
            } else {
                $url = $hostInfo . $url;
            }
        }

        // 如果指定了 $scheme，则将协议信息修改为$scheme
        return Url::ensureScheme($url, $scheme);
    }

    /**
     * 返回由 [[createUrl()]] 使用的 基础URL。
     * 一般为空字符串
     * Returns the base URL that is used by [[createUrl()]] to prepend to created URLs.
     * 默认为 @see Request::baseUrl
     * It defaults to [[Request::baseUrl]].
     * This is mainly used when [[enablePrettyUrl]] is `true` and [[showScriptName]] is `false`.
     * @return string the base URL that is used by [[createUrl()]] to prepend to created URLs.
     * @throws InvalidConfigException if running in console application and [[baseUrl]] is not configured.
     */
    public function getBaseUrl()
    {
        if ($this->_baseUrl === null) {
            $request = Yii::$app->getRequest();
            if ($request instanceof Request) {
                $this->_baseUrl = $request->getBaseUrl();
            } else {
                throw new InvalidConfigException('Please configure UrlManager::baseUrl correctly as you are running a console application.');
            }
        }

        return $this->_baseUrl;
    }

    /**
     * 设置由[[createUrl()]]使用的基本URL，以预先创建URL。
     * 这主要是在启用[[enablePrettyUrl]]，并且 [[showScriptName]]为false时使用。
     * Sets the base URL that is used by [[createUrl()]] to prepend to created URLs.
     * This is mainly used when [[enablePrettyUrl]] is `true` and [[showScriptName]] is `false`.
     * @param string $value the base URL that is used by [[createUrl()]] to prepend to created URLs.
     */
    public function setBaseUrl($value)
    {
        $this->_baseUrl = $value === null ? null : rtrim(Yii::getAlias($value), '/');
    }

    /**
     * 返回由[[createUrl()]]使用的入口脚本Url。
     * 一般为 '/index.php'
     *
     * Returns the entry script URL that is used by [[createUrl()]] to prepend to created URLs.
     * 默认为 @see Request::scriptUrl
     * It defaults to [[Request::scriptUrl]].
     * 这里主要是当[[enablePrettyUrl]]为false或[[showScriptName]]为true时使用
     * This is mainly used when [[enablePrettyUrl]] is `false` or [[showScriptName]] is `true`.
     * @return string the entry script URL that is used by [[createUrl()]] to prepend to created URLs.
     * @throws InvalidConfigException if running in console application and [[scriptUrl]] is not configured.
     */
    public function getScriptUrl()
    {
        if ($this->_scriptUrl === null) {
            $request = Yii::$app->getRequest();
            if ($request instanceof Request) {
                $this->_scriptUrl = $request->getScriptUrl();
            } else {
                throw new InvalidConfigException('Please configure UrlManager::scriptUrl correctly as you are running a console application.');
            }
        }

        return $this->_scriptUrl;
    }

    /**
     * 设置由[[createUrl()]]使用的入口脚本Url。
     * Sets the entry script URL that is used by [[createUrl()]] to prepend to created URLs.
     * 这里主要是当[[enablePrettyUrl]]为false或[[showScriptName]]为true时使用
     * This is mainly used when [[enablePrettyUrl]] is `false` or [[showScriptName]] is `true`.
     * @param string $value the entry script URL that is used by [[createUrl()]] to prepend to created URLs.
     */
    public function setScriptUrl($value)
    {
        $this->_scriptUrl = $value;
    }

    /**
     * 返回当前请求URL的模式和主机部分
     * 包含协议和域名 : https://www.baidu.com
     * Returns the host info that is used by [[createAbsoluteUrl()]] to prepend to created URLs.
     * @return string the host info (e.g. `http://www.example.com`) that is used by [[createAbsoluteUrl()]] to prepend to created URLs.
     * @throws InvalidConfigException if running in console application and [[hostInfo]] is not configured.
     */
    public function getHostInfo()
    {
        if ($this->_hostInfo === null) {
            $request = Yii::$app->getRequest();
            if ($request instanceof \yii\web\Request) {
                $this->_hostInfo = $request->getHostInfo();
            } else {
                throw new InvalidConfigException('Please configure UrlManager::hostInfo correctly as you are running a console application.');
            }
        }

        return $this->_hostInfo;
    }

    /**
     * 设置由[[createAbsoluteUrl()]]使用的主机信息，以预先创建url。
     * Sets the host info that is used by [[createAbsoluteUrl()]] to prepend to created URLs.
     * @param string $value the host info (e.g. "http://www.example.com") that is used by [[createAbsoluteUrl()]] to prepend to created URLs.
     */
    public function setHostInfo($value)
    {
        $this->_hostInfo = $value === null ? null : rtrim($value, '/');
    }
}
