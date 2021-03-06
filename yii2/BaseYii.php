<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii;

use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;
use yii\base\UnknownClassException;
use yii\di\Container;
use yii\log\Logger;

/**
 * Gets the application start timestamp.
 * 获得应用程序开始时间戳。
 */
defined('YII_BEGIN_TIME') or define('YII_BEGIN_TIME', microtime(true));
/**
 * This constant defines the framework installation directory.
 * 这个常量定义了框架安装目录
 */
defined('YII2_PATH') or define('YII2_PATH', __DIR__);
/**
 * This constant defines whether the application should be in debug mode or not. Defaults to false.
 * 这个常量定义了应用程序是否处于调试模式。默认为false
 */
defined('YII_DEBUG') or define('YII_DEBUG', false);
/**
 * This constant defines in which environment the application is running. Defaults to 'prod', meaning production environment.
 * 该常量定义了应用程序运行的环境。默认是prod，代表是生产环境。
 * You may define this constant in the bootstrap script. The value could be 'prod' (production), 'dev' (development), 'test', 'staging', etc.
 * 你可以在引导脚本里定义这个常量，可以定义为prod(生产环境)，dev(开发环境)，test，staging等
 */
defined('YII_ENV') or define('YII_ENV', 'prod');
/**
 * Whether the the application is running in production environment.
 * 应用程序是否在生产环境中运行
 */
defined('YII_ENV_PROD') or define('YII_ENV_PROD', YII_ENV === 'prod');
/**
 * Whether the the application is running in development environment.
 * 应用是否在开发环境下运行
 */
defined('YII_ENV_DEV') or define('YII_ENV_DEV', YII_ENV === 'dev');
/**
 * Whether the the application is running in testing environment.
 * 应用是否在测试环境下运行
 */
defined('YII_ENV_TEST') or define('YII_ENV_TEST', YII_ENV === 'test');

/**
 * This constant defines whether error handling should be enabled. Defaults to true.
 * 该常量定义错误处理是否开启，默认开启
 */
defined('YII_ENABLE_ERROR_HANDLER') or define('YII_ENABLE_ERROR_HANDLER', true);

/**
 * BaseYii is the core helper class for the Yii framework.
 * BaseYii是Yii框架的核心助手类
 *
 * Do not use BaseYii directly. Instead, use its child class [[\Yii]] which you can replace to
 * customize methods of BaseYii.
 * 不要直接使用BaseYii类。可以使用它的子类Yii来代替，在子类里自定义一些方法替换原有的方法即可。
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class BaseYii
{
    /**
     * @var array class map used by the Yii autoloading mechanism.
     * Yii框架自动加载机制需要使用的类列表
     * The array keys are the class names (without leading backslashes), and the array values
     * are the corresponding class file paths (or [path aliases](guide:concept-aliases)). This property mainly affects
     * how [[autoload()]] works.
     * 数组的键是类名（开头没有反斜杠），数组的值是相关类文件的路径（或者路径的别名）
     * @see autoload()
     */
    public static $classMap = [];
    /**
     * @var \yii\console\Application|\yii\web\Application the application instance
     * 属性 应用的实例
     */
    public static $app;
    /**
     * 别名需在使用前定义，因此通常来讲，定义别名应当在放在应用的初始化阶段。
     * 别名必然以 @ 打头。
     * 别名的定义可以使用之前已经定义过的别名。
     * 别名在储存时，至多只分成两级，第一级的键是根别名。 第二级别名的键是完整的别名，而不是去除根别名后剩下的所谓的“二级”别名。
     * Yii通过分层的树结构来保存别名最主要是为高效检索作准备。
     * 很多地方可以直接使用别名，而不用调用 Yii::getAlias() 转换成真实的路径或URL。
     * 别名解析时，优先匹配较长的别名。
     * Yii预定义了许多常用的别名供编程时使用。
     * 使用别名时，要将别名放在最前面，不能放在中间。
     *
     * yii\BaseYii::$aliases 用于保存整个Yii应用的所有的别名。
     * 这里默认地把 yii\BaseYii.php 所在的目录作为 @yii 别名。
     *
     * @var array registered path aliases
     * 属性 数组 注册的路径别名
     * @see getAlias()
     * @see setAlias()
     */
    public static $aliases = ['@yii' => __DIR__];
    /**
     * @var Container the dependency injection (DI) container used by [[createObject()]].
     * createObject方法使用的依赖注入容器
     * You may use [[Container::set()]] to set up the needed dependencies of classes and
     * their initial property values.
     * 你可以使用Container::set方法设置类需要的依赖关系和他们初始的属性值
     * @see createObject()
     * @see Container
     */
    public static $container;


    /**
     * Returns a string representing the current version of the Yii framework.
     * 返回Yii框架的版本号。
     * @return string the version of Yii framework
     * 返回值 字符串 Yii框架的版本号
     */
    public static function getVersion()
    {
        return '2.0.15.1';
    }

    /**
     * Translates a path alias into an actual path.
     * 把路径别名转化成一个真实的路径
     *
     * The translation is done according to the following procedure:
     * 转化的步骤如下：
     *
     * 1. If the given alias does not start with '@', it is returned back without change;
     * 1. 如果提供的别名没有以@开始，不做任何处理直接返回；
     * 2. Otherwise, look for the longest registered alias that matches the beginning part
     *    of the given alias. If it exists, replace the matching part of the given alias with
     *    the corresponding registered path.
     * 2. 否则，查找匹配给定别名开头的最长注册的别名。如果存在，根据相应的注册路径替换
     * 给定的别名的匹配部分
     * 
     * 3. Throw an exception or return false, depending on the `$throwException` parameter.
     * 3 抛出异常或者返回false，取决于$throwException的参数
     *
     * For example, by default '@yii' is registered as the alias to the Yii framework directory,
     * say '/path/to/yii'. The alias '@yii/web' would then be translated into '/path/to/yii/web'.
     * 例如，默认@yii被注册为Yii框架目录的别名，就是/path/to/yii.别名@yii/web就会被转化为
     * /path/to/yii/web
     *
     * If you have registered two aliases '@foo' and '@foo/bar'. Then translating '@foo/bar/config'
     * would replace the part '@foo/bar' (instead of '@foo') with the corresponding registered path.
     * This is because the longest alias takes precedence.
     * 若果你注册了两个别名@foo和@foo/bar，转化@foo/bar/config的时候会使用@foo/bar,而不是@foo,
     * 因为最长的路径别名优先。
     *
     * However, if the alias to be translated is '@foo/barbar/config', then '@foo' will be replaced
     * instead of '@foo/bar', because '/' serves as the boundary character.
     * 还有，如果将要被转化的路径是@foo/bar/config,那么@foo将会被替换，而不是@foo/bar，
     * 因为/代表着最上一级
     *
     * Note, this method does not check if the returned path exists or not.
     * 注意，该方法不会检测返回的路径是否存在。
     *
     * See the [guide article on aliases](guide:concept-aliases) for more information.
     *
     * @param string $alias the alias to be translated.
     * 参数 字符串 将要被转换的别名
     * 
     * @param bool $throwException whether to throw an exception if the given alias is invalid.
     * 参数 boolean 如果给定的路径别名不合法，是否抛出异常
     * 
     * If this is false and an invalid alias is given, false will be returned by this method.
     * 如果该值为false，并且提供了不合法的别名，该方法会返回false
     * 
     * @return string|bool the path corresponding to the alias, false if the root alias is not previously registered.
     * 返回值 字符串或boolean 根据别名产生的路径，如果根别名当前没有注册，就会返回false
     * 
     * @throws InvalidArgumentException if the alias is invalid while $throwException is true.
     * 当$throwException为true，并且别名不合法
     * @see setAlias()
     *
     * 先按根别名找到可能保存别名的分支。
     * 遍历这个分支下的所有树叶。由于之前叶子（别名）是按键值逆排序的，所以优先匹配长别名。
     * 将找到的最长匹配别名替换成其所对应的值，再接上 @alias 的后半截，成为新的别名。
     *
     * 别名的解析过程可以这么看:
     *
     * // 无效的别名，别名必须以@打头，别名不能放在中间
     * // 但是语句不会出错，会认为这是一个路径，一字不变的路径： path/to/@foo/bar
     * Yii::getAlias('path/to/@foo/bar');
     *
     * // 定义 @foo @foo/bar @foo/bar/qux 3个别名
     * Yii::setAlias('@foo', 'path/to/foo');
     * Yii::setAlias('@foo/bar', 'path/2/bar');
     * Yii::setAlias('@foo/bar/qux', 'path/to/qux');

     * // 找不到 @foobar根别名，抛出异常
     * Yii::getAlias('@foobar/index.php');
     *
     * // 匹配@foo，相当于 path/to/foo/qux/index.php
     * Yii::getAlias('@foo/qux/index.php');
     *
     * // 匹配@foo/bar/qux，相当于 path/to/qux/2/index.php
     * Yii::getAlias('@foo/bar/qux/2/index.php');
     *
     * // 匹配@foo/bar，相当于 path/to/bar/2/2/index.php
     * Yii::getAlias('@foo/bar/2/index.php');
     *
     */
    public static function getAlias($alias, $throwException = true)
    {
        // 一切不以@打头的别名都是无效的
        if (strncmp($alias, '@', 1)) {
            // not an alias
            //不是一个别名，则直接返回该字符串
            return $alias;
        }

        // 先确定根别名 $root
        $pos = strpos($alias, '/');
        $root = $pos === false ? $alias : substr($alias, 0, $pos);

        // 从根别名开始找起，如果根别名没找到，一切免谈
        if (isset(static::$aliases[$root])) {
            if (is_string(static::$aliases[$root])) {
                return $pos === false ? static::$aliases[$root] : static::$aliases[$root] . substr($alias, $pos);
            }

            // 由于写入前使用了 krsort() 所以，较长的别名会被先遍历到
            foreach (static::$aliases[$root] as $name => $path) {
                if (strpos($alias . '/', $name . '/') === 0) {
                    return $path . substr($alias, strlen($name));
                }
            }
        }

        if ($throwException) {
            throw new InvalidArgumentException("Invalid path alias: $alias");
        }

        return false;
    }

    /**
     * Returns the root alias part of a given alias.
     * 返回给定别名的根别名部分
     * A root alias is an alias that has been registered via [[setAlias()]] previously.
     * 根别名是之前使用setAlias注册过的别名。
     * If a given alias matches multiple root aliases, the longest one will be returned.
     * 如果给定的别名匹配了多个根别名，返回最长的那一个
     * @param string $alias the alias
     * 参数 字符串 别名
     * @return string|bool the root alias, or false if no root alias is found
     * 返回值 字符串或boolean 根别名，当根别名没有找到时返回false
     */
    public static function getRootAlias($alias)
    {
        // 查找 '/' 第一次出现的位置
        $pos = strpos($alias, '/');

        // 若不存在 '/', 则说明当前字符串就是根别名，若存在'/',则从字符串开头一直截取到 '/'(不包含 '/')
        $root = $pos === false ? $alias : substr($alias, 0, $pos);

        // 判断 $aliases 中是否存在该别名，若存在则继续，不存在直接返回false
        if (isset(static::$aliases[$root])) {
            if (is_string(static::$aliases[$root])) {
                // 是字符串，则直接返回
                return $root;
            }

            // 若是数组
            foreach (static::$aliases[$root] as $name => $path) {
                if (strpos($alias . '/', $name . '/') === 0) {
                    return $name;
                }
            }
        }

        return false;
    }

    /**
     * Registers a path alias.
     * 注册路径别名
     *
     * A path alias is a short name representing a long path (a file path, a URL, etc.)
     * For example, we use '@yii' as the alias of the path to the Yii framework directory.
     * 路径别名是使用一个短路径表示一个长路径（文件路径，url等）
     * 例如，我们使用@yii作为yii框架目录的别名
     *
     * A path alias must start with the character '@' so that it can be easily differentiated
     * from non-alias paths.
     * 路径别名一定要以字符@开始，跟非路径别名以示区分。
     *
     * Note that this method does not check if the given path exists or not. All it does is
     * to associate the alias with the path.
     * 注意该方法不会检测给定的路径是否存在。它所有的功能就是把路径和别名联系在一起
     *
     * Any trailing '/' and '\' characters in the given path will be trimmed.
     * 给定路径后面的/和\将会被去掉。
     *
     * See the [guide article on aliases](guide:concept-aliases) for more information.
     *
     * @param string $alias the alias name (e.g. "@yii"). It must start with a '@' character.
     * 参数 字符串 别名（例如@yii），必须以@开头
     * 
     * It may contain the forward slash '/' which serves as boundary character when performing
     * alias translation by [[getAlias()]].
     * 它可以包含/,getAlias方法转化路径的时候/可以当做分隔符
     * 
     * @param string $path the path corresponding to the alias. If this is null, the alias will
     * be removed. Trailing '/' and '\' characters will be trimmed. This can be
     * 参数 字符串 跟别名相关的路径。如果是null，将会把别名删除。会去掉最后的/和\，可以是：
     *
     * - a directory or a file path (e.g. `/tmp`, `/tmp/main.txt`)
     * - 目录或者文件路径，例如/tmp,/tmp/main.txt
     * - a URL (e.g. `http://www.yiiframework.com`)
     * - url (例如http://www.yiiframework.com )
     * - a path alias (e.g. `@yii/base`). In this case, the path alias will be converted into the
     *   actual path first by calling [[getAlias()]].
     * - 路径别名。这种情况下，路径别名首先将会被getAlias方法转化为实际路径。
     *
     * @throws InvalidArgumentException if $path is an invalid alias.
     * 抛出不合法参数异常， 如果别名不合法
     * @see getAlias()
     */
    public static function setAlias($alias, $path)
    {

        /*  别名规范化
            如果要定义的别名 $alias 并非以 @ 打头，自动为这个别名加上 @ 前缀。
            总之，只要是别名，必然以 @ 打头

            下面的两个语句，都定义了相同的别名 @foo
            Yii::setAlias('foo', 'path/to/foo');
            Yii::setAlias('@foo', 'path/to/foo');
        */
        if (strncmp($alias, '@', 1)) {
            $alias = '@' . $alias;
        }

        //找到别名的第一段，即@ 到第一个 / 之间的内容，如@foo/bar/qux的@foo
        /*  获取根别名$alias 的根别名，就是 @ 加上第一个 / 之间地内容，以 $root 表示。
            这里可以看出，别名是分层次的。下面3个语句的根别名都是 @foo

            Yii::setAlias('@foo', 'path/to/some/where');
            Yii::setAlias('@foo/bar', 'path/to/some/where');
            Yii::setAlias('@foo/bar/qux', 'path/to/some/where');
        */
        $pos = strpos($alias, '/');
        $root = $pos === false ? $alias : substr($alias, 0, $pos);

        //如果传入的 $path 不是 null ，说明是正常的别名定义。
        // 对于正常的别名定义，就是往 BaseYii::$aliases[] 里写入信息。
        // 而如果 $path 为 null ，说明是要删除别名
        // 定义别名@foo  Yii::setAlias('@foo', 'path/to/some/where');
        // 删除别名@foo  Yii::setAlias('@foo', null);
        if ($path !== null) {

            //如果 $path 以 @ 打头，说明这也是一个别名，则调用 Yii::getAlias(), 并将解析后的结果作为新的 $path
            //如果 $path 不以 @ 打头，说明是一个正常的path 或 URL， 那么去除 $path 末尾的 / 和 \
            $path = strncmp($path, '@', 1) ? rtrim($path, '\\/') : static::getAlias($path);

            // 检查是否有 $aliases[$root]，
            // 看看是否已经定义好了根别名。如果没有，则以$root为键，保存这个别名
            if (!isset(static::$aliases[$root])) {
                if ($pos === false) {
                    // 如果全新别名本身就是根别名
                    static::$aliases[$root] = $path;
                } else {
                    //如果全新的别名并非是一个根别名，即形如 @foo/bar 带有二级、三级等路径的
                    static::$aliases[$root] = [$alias => $path];
                }

            // 如果 $aliases[$root] 已经存在，则替换成新的路径，或增加新的路径
            /**
             *  // 初始 BaseYii::aliases['@foo'] = 'path/to/foo'
                Yii::setAlias('@foo', 'path/to/foo');

                // 直接覆盖 BaseYii::aliases['@foo'] = 'path/to/foo2'
                Yii::setAlias('@foo', 'path/to/foo2');

             *  // 新增
             *  BaseYii::aliases['@foo'] = [
             *      '@foo/bar' => 'path/to/foo/bar',
             *      '@foo' => 'path/to/foo2',
             *  ];
             *
                Yii::setAlias('@foo/bar', 'path/to/foo/bar');
             */
            } elseif (is_string(static::$aliases[$root])) {
                if ($pos === false) {
                    static::$aliases[$root] = $path;
                } else {
                    static::$aliases[$root] = [
                        $alias => $path,
                        $root => static::$aliases[$root],
                    ];
                }
            } else {
                static::$aliases[$root][$alias] = $path;
                krsort(static::$aliases[$root]);
            }
        } elseif (isset(static::$aliases[$root])) {
            // 当传入的 $path 为 null 时，表示要删除这个别名。
            //Yii使用PHP的 unset() 注销 BaseYii::$aliases[] 数组中的对应元素， 达到删除别名的目的。
            //注意删除别名后，不需要调用 krsort() 对数组进行处理。
            if (is_array(static::$aliases[$root])) {
                unset(static::$aliases[$root][$alias]);
            } elseif ($pos === false) {
                unset(static::$aliases[$root]);
            }
        }
    }

    /**
     * Class autoload loader.
     * 自动加载类的方法
     *
     * This method is invoked automatically when PHP sees an unknown class.
     * 当PHP遇到未知的类的时候，自动调用此方法
     * The method will attempt to include the class file according to the following procedure:
     * 该方法会根据如下步骤尝试包含类文件：
     *
     * 1. Search in [[classMap]];
     * 1. 在类列表里搜索
     * 2. If the class is namespaced (e.g. `yii\base\Component`), it will attempt
     *    to include the file associated with the corresponding path alias
     *    (e.g. `@yii/base/Component.php`);
     * 2. 如果类包含命名空间（例如 yii\base\Component），它将会尝试包含跟路径别名相关的文件
     * 例如 @yii/base/Component.php
     *
     * This autoloader allows loading classes that follow the [PSR-4 standard](http://www.php-fig.org/psr/psr-4/)
     * and have its top-level namespace or sub-namespaces defined as path aliases.
     * 该自动加载可以加载符合[PSR-4 标准的类]，并且用于路径别名定义的最高级别的命名空间或者子命名
     *
     * Example: When aliases `@yii` and `@yii/bootstrap` are defined, classes in the `yii\bootstrap` namespace
     * will be loaded using the `@yii/bootstrap` alias which points to the directory where bootstrap extension
     * files are installed and all classes from other `yii` namespaces will be loaded from the yii framework directory.
     * 例如，当别名@yii和@yii/bootstrap被定义过，在yii\bootstrap命名空间下的类将会被使用指向bootstrap扩展安装目录@yii/bootstrap别名
     * 并且yii命名空间下的其他类将会从yii框架目录加载
     *
     * Also the [guide section on autoloading](guide:concept-autoloading).
     *还有[自动加载指导部分](guide:concept-autoloading)
     *
     * @param string $className the fully qualified class name without a leading backslash "\"
     * 参数 字符串 没有\斜线开头的完整的合格的类名
     * @throws UnknownClassException if the class does not exist in the class file
     * 抛出未知类异常。 如果类在类文件中不存在
     */
    public static function autoload($className)
    {
        // 检查 $classMap[$className] 看看是否在映射表中已经有拟加载类的位置信息
        if (isset(static::$classMap[$className])) {
            // 如果有，那么将这个路径作为类文件的所在位置。 类文件的完整路径保存在 $classFile
            $classFile = static::$classMap[$className];
            // 再看看这个位置信息是不是一个路径别名，即是不是以 @ 打头， 是的话，将路径别名解析成实际路径。
            if ($classFile[0] === '@') {
                $classFile = static::getAlias($classFile);
            }
        } elseif (strpos($className, '\\') !== false) {

            // 如果有 \ ，认为这个类名符合规范，将其转换成路径形式。 即所有的 \ 用 / 替换，并加上 .php 的后缀。
            // 将替换后的类名，加上 @ 前缀，作为一个路径别名，进行解析。
            // 从别名的解析过程我们知道，如果根别名不存在，将会抛出异常。
            // 所以，类的命名，必须以有效的根别名打头:

            // 有效的类名，因为@yii是一个已经预定义好的别名
            // use yii\base\Application;

            // 无效的类名，因为没有 @foo 或 @foo/bar 的根别名，要提前定义好
            // use foo\bar\SomeClass;
            $classFile = static::getAlias('@' . str_replace('\\', '/', $className) . '.php', false);
            if ($classFile === false || !is_file($classFile)) {
                return;
            }
        } else {
            // 如果 $classMap[$className] 没有该类的信息，
            // 那么，看看这个类名中是否含有 \ ， 如果没有，说明这是一个不符合规范要求的类名，autoloader直接返回.
            // PHP会尝试使用其他已经注册的autoloader进行加载。
            return;
        }

        // 使用PHP的 include() 将类文件加载进来，实现类的加载。
        include $classFile;

        if (YII_DEBUG && !class_exists($className, false) && !interface_exists($className, false) && !trait_exists($className, false)) {
            throw new UnknownClassException("Unable to find '$className' in file: $classFile. Namespace missing?");
        }
    }

    /**
     * Creates a new object using the given configuration.
     * 采用给定的配置项创建对象
     *
     * You may view this method as an enhanced version of the `new` operator.
     * 您可以把该方法看做是new操作的增强版本。
     * The method supports creating an object based on a class name, a configuration array or
     * an anonymous function.
     * 该方法支持基于类名，配置数组，或者匿名行数创建对象。
     *
     * Below are some usage examples:
     * 如下是一些有用的例子：
     *
     * ```php
     * // create an object using a class name
     * // 使用类名创建对象。
     * $object = Yii::createObject('yii\db\Connection');
     *
     * // create an object using a configuration array
     * // 使用配置数组创建对象
     * $object = Yii::createObject([
     *     'class' => 'yii\db\Connection',
     *     'dsn' => 'mysql:host=127.0.0.1;dbname=demo',
     *     'username' => 'root',
     *     'password' => '',
     *     'charset' => 'utf8',
     * ]);
     *
     * // create an object with two constructor parameters
     * // 使用两个构造参数创建对象
     * $object = \Yii::createObject('MyClass', [$param1, $param2]);
     * ```
     *
     * Using [[\yii\di\Container|dependency injection container]], this method can also identify
     * dependent objects, instantiate them and inject them into the newly created object.
     * 使用[[\yii\di\Container|dependency injection container]]，该方法也可以确定依赖对象,实例化它们,并将它们注入到新创建的对象。
     *
     * @param string|array|callable $type the object type. This can be specified in one of the following forms:
     * 参数 对象类型，可以通过如下的方式指定：
     *
     * - a string: representing the class name of the object to be created
     * - 字符串： 代表被创建对象的类名
     * - a configuration array: the array must contain a `class` element which is treated as the object class,
     *   and the rest of the name-value pairs will be used to initialize the corresponding object properties
     * - 配置数组： 数组必须包含被当做对象的类元素，并且其他的键值对将会被初始化为对象的属性。
     * - a PHP callable: either an anonymous function or an array representing a class method (`[$class or $object, $method]`).
     *   The callable should return a new instance of the object being created.
     * - php回调:匿名函数或者表示类方法的数组，回调应该返回被创建对象的新实例。
     *
     * @param array $params the constructor parameters
     * 参数 数组 构造函数使用的参数
     * @return object the created object
     * 返回值 对象 被创建的对象
     * @throws InvalidConfigException if the configuration is invalid.
     * 当配置项不合法时，抛出不合法的配置项异常
     * @see \yii\di\Container
     */
    public static function createObject($type, array $params = [])
    {
        //  static::$container是引用了DI容器的静态变量
        if (is_string($type)) {
            // 字符串，代表一个类名、接口名、别名。
            return static::$container->get($type, $params);
        } elseif (is_array($type) && isset($type['class'])) {
            // 是个数组，代表配置数组，必须含有 class 元素。
            $class = $type['class'];
            unset($type['class']);
            // 调用DI容器的get() 来获取、创建实例
            return static::$container->get($class, $params, $type);
        } elseif (is_callable($type, true)) {
            // 是个PHP callable，那就调用它，并将其返回值作为服务或组件的实例返回
            return static::$container->invoke($type, $params);
        } elseif (is_array($type)) {
            // 是个数组但没有 class 元素，抛出异常
            throw new InvalidConfigException('Object configuration must be an array containing a "class" element.');
        }

        // 其他情况，抛出异常
        throw new InvalidConfigException('Unsupported configuration type: ' . gettype($type));
    }

    private static $_logger;

    /**
     * @return Logger message logger
     * 返回值 日志记录器对象
     */
    public static function getLogger()
    {
        if (self::$_logger !== null) {
            return self::$_logger;
        }

        return self::$_logger = static::createObject('yii\log\Logger');
    }

    /**
     * Sets the logger object.
     * 设置日志记录器对象
     * @param Logger $logger the logger object.
     * 参数 日志对象
     */
    public static function setLogger($logger)
    {
        self::$_logger = $logger;
    }

    /**
     * Logs a debug message.
     * 记录追踪信息。
     * Trace messages are logged mainly for development purpose to see
     * the execution work flow of some code. This method will only log
     * a message when the application is in debug mode.
     * 主要是在开发环境下才记录追踪信息，为了查看一些代码的运行流程。
     * 
     * @param string|array $message the message to be logged. This can be a simple string or a more
     * complex data structure, such as array.
     * 参数 字符串 被记录的信息
     * 
     * @param string $category the category of the message.
     * 参数 字符串 信息的分类
     * 
     * @since 2.0.14
     */
    public static function debug($message, $category = 'application')
    {
        if (YII_DEBUG) {
            static::getLogger()->log($message, Logger::LEVEL_TRACE, $category);
        }
    }

    /**
     * Alias of [[debug()]].
     * @param string|array $message the message to be logged. This can be a simple string or a more
     * complex data structure, such as array.
     * @param string $category the category of the message.
     * @deprecated since 2.0.14. Use [[debug()]] instead.
     */
    public static function trace($message, $category = 'application')
    {
        static::debug($message, $category);
    }

    /**
     * Logs an error message.
     * 记录错误信息
     * An error message is typically logged when an unrecoverable error occurs
     * during the execution of an application.
     * 通常存在应用程序发生不可恢复的错误时，才会记录错误信息
     * 
     * @param string|array $message the message to be logged. This can be a simple string or a more
     * complex data structure, such as array.
     * 参数 字符串 被记录的信息
     * 
     * @param string $category the category of the message.
     * 参数 字符串 信息所属分类
     */
    public static function error($message, $category = 'application')
    {
        static::getLogger()->log($message, Logger::LEVEL_ERROR, $category);
    }

    /**
     * Logs a warning message.
     * 记录警告信息
     * A warning message is typically logged when an error occurs while the execution
     * can still continue.
     *
     * 当程序还能继续运行的时候，记录警告信息
     * @param string|array $message the message to be logged. This can be a simple string or a more
     * complex data structure, such as array.
     * 参数 字符串 被记录的信息
     * @param string $category the category of the message.
     * 参数 字符串 信息所属的分类
     */
    public static function warning($message, $category = 'application')
    {
        static::getLogger()->log($message, Logger::LEVEL_WARNING, $category);
    }

    /**
     * Logs an informative message.
     * 记录一个有用的信息
     * An informative message is typically logged by an application to keep record of
     * something important (e.g. an administrator logs in).
     * 通常情况下，为了记录应用程序产生的一些重要的信息（例如管理员登陆），才会记录有用信息
     * @param string|array $message the message to be logged. This can be a simple string or a more
     * complex data structure, such as array.
     * 参数 字符串 被记录的有用信息
     * @param string $category the category of the message.
     * 参数 字符串 信息所属的分类
     */
    public static function info($message, $category = 'application')
    {
        static::getLogger()->log($message, Logger::LEVEL_INFO, $category);
    }

    /**
     * Marks the beginning of a code block for profiling.
     * 标记代码块分析开始的位置。
     *
     * This has to be matched with a call to [[endProfile]] with the same category name.
     * 必须和带有相同分类名的endProfile配对使用
     * The begin- and end- calls must also be properly nested. For example,
     * 开始和结束的调用，必须合理嵌套。例如：
     *
     * ```php
     * \Yii::beginProfile('block1');
     * // some code to be profiled
     * // 一些被分析的代码
     *     \Yii::beginProfile('block2');
     *     // some other code to be profiled
     *     // 另外其他的分析代码
     *     \Yii::endProfile('block2');
     * \Yii::endProfile('block1');
     * ```
     * @param string $token token for the code block
     * 参数 字符串 代码块的玲api
     * @param string $category the category of this log message
     * 参数 字符串 该记录信息的分类
     * @see endProfile()
     */
    public static function beginProfile($token, $category = 'application')
    {
        static::getLogger()->log($token, Logger::LEVEL_PROFILE_BEGIN, $category);
    }

    /**
     * Marks the end of a code block for profiling.
     * 标记被分析代码块的结束位置。
     * This has to be matched with a previous call to [[beginProfile]] with the same category name.
     * 该方法必须和带有相同分类名的beginProfile方法配合使用
     * @param string $token token for the code block
     * 参数 字符串 代码块的令牌
     * @param string $category the category of this log message
     * 参数 字符串 该记录信息的分类
     * @see beginProfile()
     */
    public static function endProfile($token, $category = 'application')
    {
        static::getLogger()->log($token, Logger::LEVEL_PROFILE_END, $category);
    }

    /**
     * Returns an HTML hyperlink that can be displayed on your Web page showing "Powered by Yii Framework" information.
     * 在你的网页上返回Powered by Yii Framework的超链接信息
     * @return string an HTML hyperlink that can be displayed on your Web page showing "Powered by Yii Framework" information
     * 返回值 字符串 把Powered by Yii Framework信息显示在您网站上的超链接
     * 
     * @deprecated since 2.0.14, this method will be removed in 2.1.0.
     */
    public static function powered()
    {
        return \Yii::t('yii', 'Powered by {yii}', [
            'yii' => '<a href="http://www.yiiframework.com/" rel="external">' . \Yii::t('yii',
                    'Yii Framework') . '</a>',
        ]);
    }

    /**
     * Translates a message to the specified language.
     * 把信息翻译成指定的语言
     *
     * This is a shortcut method of [[\yii\i18n\I18N::translate()]].
     * 该方法是[[\yii\i18n\I18N::translate()]]的一个快捷方式。
     *
     * The translation will be conducted according to the message category and the target language will be used.
     * 翻译将根据信息类别和使用目标语言
     *
     * You can add parameters to a translation message that will be substituted with the corresponding value after
     * translation. The format for this is to use curly brackets around the parameter name as you can see in the following example:
     * 您可以将参数添加到翻译消息,翻译后将采用相应的替换值。参数的格式是使用花括号包裹，请看下面的例子：
     *
     * ```php
     * $username = 'Alexander';
     * echo \Yii::t('app', 'Hello, {username}!', ['username' => $username]);
     * ```
     *
     * Further formatting of message parameters is supported using the [PHP intl extensions](http://www.php.net/manual/en/intro.intl.php)
     * message formatter. See [[\yii\i18n\I18N::translate()]] for more details.
     * 通过[PHP intl extensions]支持更多的信息参数。详情请参考[[\yii\i18n\I18N::translate()]]。
     *
     * @param string $category the message category.
     * 参数 字符串 信息分类
     * @param string $message the message to be translated.
     * 参数 字符串 被翻译的信息
     * @param array $params the parameters that will be used to replace the corresponding placeholders in the message.
     * 参数 数组 信息中相应的占位符要被使用的参数
     * @param string $language the language code (e.g. `en-US`, `en`). If this is null, the current
     * [[\yii\base\Application::language|application language]] will be used.
     * 参数 字符串 语言代码，（例如`en-US`, `en`），如果为空，就会使用默认的[[\yii\base\Application::language|application language]]
     * @return string the translated message.
     * 返回值 字符串 翻译过的信息
     */
    public static function t($category, $message, $params = [], $language = null)
    {
        if (static::$app !== null) {
            return static::$app->getI18n()->translate($category, $message, $params, $language ?: static::$app->language);
        }

        $placeholders = [];
        foreach ((array) $params as $name => $value) {
            $placeholders['{' . $name . '}'] = $value;
        }

        return ($placeholders === []) ? $message : strtr($message, $placeholders);
    }

    /**
     * Configures an object with the initial property values.
     * 使用初始化属性 配置对象
     * @param object $object the object to be configured
     * 参数 对象 被配置的对象
     * @param array $properties the property initial values given in terms of name-value pairs.
     * 参数 数组 属性初始值给定的名称-值对
     * @return object the object itself
     * 返回值 对象本身
     */
    public static function configure($object, $properties)
    {
        foreach ($properties as $name => $value) {
            $object->$name = $value;
        }

        return $object;
    }

    /**
     * Returns the public member variables of an object.
     * 返回对象的公共成员变量。
     * This method is provided such that we can get the public member variables of an object.
     * 通过该方法，我们可以获得一个对象的公共成员变量。
     * It is different from "get_object_vars()" because the latter will return private
     * and protected variables if it is called within the object itself.
     * 它跟get_object_vars方法的不同之处在于，当对象自身调用get_object_vars方法时，会返回私有和受保护的成员变量
     *
     * @param object $object the object to be handled
     * 参数 对象 将要被处理的对象
     * @return array the public member variables of the object
     * 返回值 数组 对象的公共成员变量
     */
    public static function getObjectVars($object)
    {
        return get_object_vars($object);
    }
}
