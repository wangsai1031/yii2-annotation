<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\di;

use ReflectionClass;
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;

/**
 * Container 用于实现 依赖注入容器
 * Container implements a [dependency injection](http://en.wikipedia.org/wiki/Dependency_injection) container.
 *
 * 依赖注入（Dependency Injection，DI）容器就是一个对象，它知道怎样初始化并配置对象及其依赖的所有对象。
 * 更多关于DI的信息，请参考[Martin Fowler's article](http://martinfowler.com/articles/injection.html).
 * A dependency injection (DI) container is an object that knows how to instantiate and configure objects and all their dependent objects.
 * For more information about DI, please refer to [Martin Fowler's article](http://martinfowler.com/articles/injection.html).
 *
 * Container支持构造函数注入和属性注入。
 * Container supports constructor injection as well as property injection.
 *
 * 要使用Container,首先需要通过调用[[set()]]来设置类依赖项。
 * 然后调用get()来创建一个新的类对象。
 * Container将自动实例化依赖对象，将它们注入到创建的对象中，配置并最终返回新创建的对象。
 * To use Container, you first need to set up the class dependencies by calling [[set()]].
 * You then call [[get()]] to create a new class object.
 * Container will automatically instantiate dependent objects, inject them into the object being created, configure and finally return the newly created object.
 *
 * 默认情况下，[[\Yii::$container]]引用一个容器实例，该容器通过使用[[\Yii::createObject()]]创建新的对象实例。
 * 在创建新对象时，您可以使用该方法来替换`new`操作符，这将使您受益于自动依赖解析和默认属性配置。
 * By default, [[\Yii::$container]] refers to a Container instance which is used by [[\Yii::createObject()]] to create new object instances.
 * You may use this method to replace the `new` operator when creating a new object, which gives you the benefit of automatic dependency resolution and default property configuration.
 *
 * Below is an example of using Container:
 * 下面是一个使用容器的示例。
 * UserLister 类依赖一个实现了 UserFinderInterface 接口的对象；
 * UserFinder 类实现了这个接口，并依赖于一个 Connection 对象。
 * 所有这些依赖关系都是通过类构造器参数的类型提示定义的。
 * 通过属性依赖关系的注册，DI 容器可以自动解决这些依赖关系并能通过一个简单的 get('userLister') 调用创建一个新的 UserLister 实例。
 *
 * ```php
 * namespace app\models;
 *
 * use yii\base\Object;
 * use yii\db\Connection;
 * use yii\di\Container;
 *
 * interface UserFinderInterface
 * {
 *     function findUser();
 * }
 *
 * class UserFinder extends Object implements UserFinderInterface
 * {
 *     public $db;
 *
 *     public function __construct(Connection $db, $config = [])
 *     {
 *         $this->db = $db;
 *         parent::__construct($config);
 *     }
 *
 *     public function findUser()
 *     {
 *     }
 * }
 *
 * class UserLister extends Object
 * {
 *     public $finder;
 *
 *     public function __construct(UserFinderInterface $finder, $config = [])
 *     {
 *         $this->finder = $finder;
 *         parent::__construct($config);
 *     }
 * }
 *
 * $container = new Container;
 * $container->set('yii\db\Connection', [
 *     'dsn' => '...',
 * ]);
 * $container->set('app\models\UserFinderInterface', [
 *     'class' => 'app\models\UserFinder',
 * ]);
 * $container->set('userLister', 'app\models\UserLister');
 *
 * $lister = $container->get('userLister');
 *
 * // which is equivalent to:
 * // 等价于:
 *
 * $db = new \yii\db\Connection(['dsn' => '...']);
 * $finder = new UserFinder($db);
 * $lister = new UserLister($finder);
 * ```
 *
 * 对象定义或已加载的共享对象的列表(type or ID => 定义或实例). 这个属性是只读的.
 * @property array $definitions The list of the object definitions or the loaded shared objects (type or ID =>
 * definition or instance). This property is read-only.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Container extends Component
{
    /**
     * 用于保存单例Singleton对象，以对象类型为键
     *
     * 键：类名，接口名，别名
     * 值：类的实例，NULL时表示尚未实例化
     *
     * @var array singleton objects indexed by their types
     */
    private $_singletons = [];
    /**
     * 用于保存依赖的定义。由其类型索引的对象定义。
     * 键：类名，接口名，别名
     * 值：一个必须具有'class'元素的数组，或者一个php callback
     *
     * @var array object definitions indexed by their types
     */
    private $_definitions = [];
    /**
     * 用于保存构造函数的参数，以对象类型为键
     *
     * 键：类名，接口名，别名
     * 值：一个数组，一般应满足 yii\base\Object 对于构造函数参数的要求
     * @var array constructor parameters indexed by object types
     */
    private $_params = [];
    /**
     * 以类（接口、别名）名为键， 缓存了这个类（接口、别名）的ReflectionClass。一经缓存，便不会再更改。
     *
     * 键：类名，接口名，别名
     * 值：某个类的Reflection Class 的实例
     *
     * @var array cached ReflectionClass objects indexed by class/interface names
     */
    private $_reflections = [];
    /**
     * 用于缓存依赖信息，以类名或接口名为键
     *
     * 键：类名，接口名，别名
     * 值：
     *  1.一个无下标数组，表示类的构造函数参数的类型
     *  2.当类的构造函数参数为基本类型时，数组元素为null
     *  3.当构造函数参数为类类型时，数组元素为instance实例
     *  4.当构造函数参数具有默认值时，数组元素为该默认值
     *  5.当数组为空时，表示类不具有构造函数
     *
     *  与 $_reflections 这两个缓存数组都是在 yii\di\Container::getDependencies() 中完成。这个函数只是简单地向数组写入数据。
        经过 yii\di\Container::resolveDependencies() 处理，DI容器会将依赖信息转换成实例。
     *  这个实例化的过程中，是向容器索要实例。也就是说，有可能会引起递归。
     * @var array cached dependencies indexed by class/interface names. Each class name
     * is associated with a list of constructor parameter types or default values.
     */
    private $_dependencies = [];


    /**
     * 返回被请求类的实例
     * Returns an instance of the requested class.
     *
     *  get() 解析依赖获取对象是一个自动递归的过程，也就是说，当要获取的对象依赖于其他对象时， Yii会自动获取这些对象及其所依赖的下层对象的实例。
     * 同时，即使对于未定义的依赖，DI容器通过PHP的Reflection API，也可以自动解析出当前对象的依赖来。
        get() 不直接实例化对象，也不直接解析依赖信息。而是通过 build() 来实例化对象和解析依赖。
        get() 会根据依赖定义，递归调用自身去获取依赖单元。
     * 因此，在整个实例化过程中，一共有两个地方会产生递归：一是 get() ， 二是 build() 中的 resolveDependencies() 。
     *
     * You may provide constructor parameters (`$params`) and object configurations (`$config`)
     * that will be used during the creation of the instance.
     *
     * If the class implements [[\yii\base\Configurable]], the `$config` parameter will be passed as the last
     * parameter to the class constructor; Otherwise, the configuration will be applied *after* the object is
     * instantiated.
     *
     * Note that if the class is declared to be singleton by calling [[setSingleton()]],
     * the same instance of the class will be returned each time this method is called.
     * In this case, the constructor parameters and object configurations will be used
     * only if the class is instantiated the first time.
     *
     * $class 表示将要创建或者获取的对象。可以是一个类名、接口名、别名。
     * $params 是一个用于这个要创建的对象的构造函数的参数，其参数顺序要与构造函数的定义一致。 通常用于未定义的依赖。
     * $config 是一个配置数组，用于配置获取的对象。通常用于未定义的依赖，或覆盖原来依赖中定义好的配置。
     *
     * @param string $class the class name or an alias name (e.g. `foo`) that was previously registered via [[set()]]
     * or [[setSingleton()]].
     * @param array $params a list of constructor parameter values. The parameters should be provided in the order
     * they appear in the constructor declaration. If you want to skip some parameters, you should index the remaining
     * ones with the integers that represent their positions in the constructor parameter list.
     * @param array $config a list of name-value pairs that will be used to initialize the object properties.
     * @return object an instance of the requested class.
     * @throws InvalidConfigException if the class cannot be recognized or correspond to an invalid definition
     * @throws NotInstantiableException If resolved to an abstract class or an interface (since 2.0.9)
     */
    public function get($class, $params = [], $config = [])
    {
        // 已经有一个完成实例化的单例，直接引用这个单例
        if (isset($this->_singletons[$class])) {
            // singleton
            return $this->_singletons[$class];
        } elseif (!isset($this->_definitions[$class])) {
            // 是个尚未注册过的依赖，说明它不依赖其他单元，或者依赖信息不用定义，
            // 则根据传入的参数创建一个实例
            return $this->build($class, $params, $config);
        }

        // 注意这里创建了 $_definitions[$class] 数组的副本
        $definition = $this->_definitions[$class];

        // 依赖的定义是个 PHP callable，调用之
        if (is_callable($definition, true)) {
            $params = $this->resolveDependencies($this->mergeParams($class, $params));
            $object = call_user_func($definition, $this, $params, $config);
        } elseif (is_array($definition)) {
            // 依赖的定义是个数组，合并相关的配置和参数，创建之
            $concrete = $definition['class'];
            unset($definition['class']);

            // 合并将依赖定义中配置数组和参数数组与传入的配置数组和参数数组合并
            $config = array_merge($definition, $config);
            $params = $this->mergeParams($class, $params);

            if ($concrete === $class) {
                // 这是递归终止的重要条件
                $object = $this->build($class, $params, $config);
            } else {
                // 这里实现了递归解析
                $object = $this->get($concrete, $params, $config);
            }
        } elseif (is_object($definition)) {
            // 依赖的定义是个对象则应当保存为单例
            return $this->_singletons[$class] = $definition;
        } else {
            throw new InvalidConfigException('Unexpected object definition type: ' . gettype($definition));
        }

        // 依赖的定义已经定义为单例的，应当实例化该对象
        if (array_key_exists($class, $this->_singletons)) {
            // singleton
            $this->_singletons[$class] = $object;
        }

        return $object;
    }

    /**
     * 用这个容器注册一个类定义
     * Registers a class definition with this container.
     *
     * 与 setSingleton() 相比，只是 set() 用于在每次请求时构造新的实例返回， 而 setSingleton() 只维护一个单例，每次请求时都返回同一对象。
     * 表现在数据结构上，就是 set() 在注册依赖时，会把使用 setSingleton() 注册的依赖删除。
     * 否则，在解析依赖时，你让Yii究竟是依赖续弦还是原配？
     * 因此，在DI容器中，依赖关系的定义是唯一的。 后定义的同名依赖，会覆盖前面定义好的依赖。
     * For example,
     *
     * ```php
     * // 直接以类名注册一个依赖，虽然这么做没什么意义。
       // $_definition['yii\db\Connection'] = 'yii\db\Connetcion'
     * // register a class name as is. This can be skipped.
     * $container->set('yii\db\Connection');
     *
     * // 注册一个接口，当一个类依赖于该接口时，定义中的类会自动被实例化，并供有依赖需要的类使用
     * //  $_definition['yii\mail\MailInterface', 'yii\swiftmailer\Mailer']
     * // register an interface
     * // When a class depends on the interface, the corresponding class will be instantiated as the dependent object
     * $container->set('yii\mail\MailInterface', 'yii\swiftmailer\Mailer');
     *
     * // 注册一个别名，当调用$container->get('foo')时，可以得到一个yii\db\Connection 实例。
     * //  $_definition['foo', 'yii\db\Connection']
     * // register an alias name. You can use $container->get('foo')
     * // to create an instance of Connection
     * $container->set('foo', 'yii\db\Connection');
     *
     * // 注册带配置项的类，当类通过get()方法进行实例化时，将会应用这些配置项
     * // $_definition['yii\db\Connection'] = [...]
     * // register a class with configuration. The configuration
     * // will be applied when the class is instantiated by get()
     * $container->set('yii\db\Connection', [
     *     'dsn' => 'mysql:host=127.0.0.1;dbname=demo',
     *     'username' => 'root',
     *     'password' => '',
     *     'charset' => 'utf8',
     * ]);
     *
     * 用一个配置数组来注册一个别名，由于别名的类型不详，因此配置数组中需要有 class 元素。
     * // $_definition['db'] = [...]
     * // register an alias name with class configuration
     * // In this case, a "class" element is required to specify the class
     * $container->set('db', [
     *     'class' => 'yii\db\Connection',
     *     'dsn' => 'mysql:host=127.0.0.1;dbname=demo',
     *     'username' => 'root',
     *     'password' => '',
     *     'charset' => 'utf8',
     * ]);
     *
     * // 用一个PHP callable来注册一个别名，每次引用这个别名时，这个callable都会被调用。
     * // $_definition['db'] = function(...){...}
     * // register a PHP callable
     * // The callable will be executed when $container->get('db') is called
     * $container->set('db', function ($container, $params, $config) {
     *     return new \yii\db\Connection($config);
     * });
     *
     *  // 用一个对象来注册一个别名，每次引用这个别名时，这个对象都会被引用。
        // $_definition['pageCache'] = anInstanceOfFileCache
        $container->set('pageCache', new FileCache);
     *
     * ```
     *
     * 如果一个同名的类定义已经存在，那么它就会被新的一个定义覆盖。
     * 您可以使用[[has()]]来检查类定义是否已经存在
     * If a class definition with the same name already exists, it will be overwritten with the new one.
     * You may use [[has()]] to check if a class definition already exists.
     *
     * @param string $class class name, interface name or alias name
     * @param mixed $definition the definition associated with `$class`. It can be one of the following:
     *
     * 与`$class`相关的定义，它可以是以下内容之一：
     * - PHP 可调用方法: 这个 可调用方法 将在[[get()]]调用时执行.
     *   可调用方法的参数应该是：`function ($container, $params, $config)`，
     *   `$params` 表示构造函数参数列表，`$config` 表示对象配置，`$container`表示容器对象。
     *   可调用函数的返回值将通过[[get()]]作为对象实例请求返回。
     * - 配置数组：该数组包含键值对，当[[get()]]被调用时，它将用于初始化新创建的对象的属性值。
     *   `class`元素代表要创建的对象的类。如果没有指定`class`，`$class`将被作为类名使用。
     * - 字符串：一个类名，一个接口名或者一个别名
     *
     * - a PHP callable: The callable will be executed when [[get()]] is invoked.
     *   The signature of the callable should be `function ($container, $params, $config)`,
     *   where `$params` stands for the list of constructor parameters, `$config` the object configuration, and `$container` the container object.
     *   The return value of the callable will be returned by [[get()]] as the object instance requested.
     * - a configuration array: the array contains name-value pairs that will be used to initialize the property values of the newly created object when [[get()]] is called.
     *   The `class` element stands for the the class of the object to be created.
     *   If `class` is not specified, `$class` will be used as the class name.
     * - a string: a class name, an interface name or an alias name.
     * @param array $params the list of constructor parameters. The parameters will be passed to the class
     * constructor when [[get()]] is called.
     * @return $this the container itself
     */
    public function set($class, $definition = [], array $params = [])
    {
        // 规范化 $definition 并写入 $_definitions[$class]
        $this->_definitions[$class] = $this->normalizeDefinition($class, $definition);
        // 将构造函数参数写入 $_params[$class]
        $this->_params[$class] = $params;
        // 删除 $_singleton[] 中的同名依赖
        unset($this->_singletons[$class]);
        return $this;
    }

    /**
     * 用这个容器注册一个类定义，并将类标记为一个单例类
     * Registers a class definition with this container and marks the class as a singleton class.
     *
     * 与 set() 相比，只是 set() 用于在每次请求时构造新的实例返回， 而 setSingleton() 只维护一个单例，每次请求时都返回同一对象。
     * 表现在数据结构上，就是 set() 在注册依赖时，会把使用 setSingleton() 注册的依赖删除。
     * 否则，在解析依赖时，你让Yii究竟是依赖续弦还是原配？
     * 因此，在DI容器中，依赖关系的定义是唯一的。 后定义的同名依赖，会覆盖前面定义好的依赖。
     *
     * This method is similar to [[set()]] except that classes registered via this method will only have one
     * instance. Each time [[get()]] is called, the same instance of the specified class will be returned.
     *
     * @param string $class class name, interface name or alias name
     * @param mixed $definition the definition associated with `$class`. See [[set()]] for more details.
     * @param array $params the list of constructor parameters. The parameters will be passed to the class
     * constructor when [[get()]] is called.
     * @return $this the container itself
     * @see set()
     */
    public function setSingleton($class, $definition = [], array $params = [])
    {
        // 规范化 $definition 并写入 $_definitions[$class]
        $this->_definitions[$class] = $this->normalizeDefinition($class, $definition);
        // 将构造函数参数写入 $_params[$class]
        $this->_params[$class] = $params;
        // 将$_singleton[$class]置为null，表示还未实例化
        $this->_singletons[$class] = null;
        return $this;
    }

    /**
     * 返回一个值，指示容器是否有指定名称的定义。
     * Returns a value indicating whether the container has the definition of the specified name.
     * @param string $class class name, interface name or alias name
     * @return boolean whether the container has the definition of the specified name..
     * @see set()
     */
    public function has($class)
    {
        return isset($this->_definitions[$class]);
    }

    /**
     * 返回一个值，表示给定的名称是否与已注册的单例相对应.
     * Returns a value indicating whether the given name corresponds to a registered singleton.
     * @param string $class class name, interface name or alias name
     * @param boolean $checkInstance whether to check if the singleton has been instantiated.
     * @return boolean whether the given name corresponds to a registered singleton. If `$checkInstance` is true,
     * the method should return a value indicating whether the singleton has been instantiated.
     */
    public function hasSingleton($class, $checkInstance = false)
    {
        return $checkInstance ? isset($this->_singletons[$class]) : array_key_exists($class, $this->_singletons);
    }

    /**
     * 删除指定名称的定义。
     * Removes the definition for the specified name.
     * @param string $class class name, interface name or alias name
     */
    public function clear($class)
    {
        unset($this->_definitions[$class], $this->_singletons[$class]);
    }

    /**
     * 规范化类的定义
     *
     *  - 如果 $definition 是空的，直接返回数组 ['class' => $class]
        - 如果 $definition 是字符串，那么认为这个字符串就是所依赖的类名、接口名或别名， 那么直接返回数组 ['class' => $definition]
        - 如果 $definition 是一个PHP callable，或是一个对象，那么直接返回该 $definition
        - 如果 $definition 是一个数组，那么其应当是一个包含了元素 $definition['class'] 的配置数组。
     *  如果该数组未定义 $definition['class'] 那么，将传入的 $class 作为该元素的值，最后返回该数组。
        - 上一步中，如果 definition['class'] 未定义，而 $class 不是一个有效的类名，那么抛出异常。
        - 如果 $definition 不属于上述的各种情况，也抛出异常。
     *
     * Normalizes the class definition.
     * @param string $class class name
     * @param string|array|callable $definition the class definition
     * @return array the normalized class definition
     * @throws InvalidConfigException if the definition is invalid.
     */
    protected function normalizeDefinition($class, $definition)
    {
        if (empty($definition)) {
            // $definition 是空的转换成 ['class' => $class] 形式
            return ['class' => $class];
        } elseif (is_string($definition)) {
            // $definition 是字符串，转换成 ['class' => $definition] 形式
            return ['class' => $definition];
        } elseif (is_callable($definition, true) || is_object($definition)) {
            // $definition 是PHP callable 或对象，则直接将其作为依赖的定义
            return $definition;
        } elseif (is_array($definition)) {
            // $definition 是数组则确保该数组定义了 class 元素
            if (!isset($definition['class'])) {
                if (strpos($class, '\\') !== false) {
                    $definition['class'] = $class;
                } else {
                    throw new InvalidConfigException("A class definition requires a \"class\" member.");
                }
            }
            return $definition;
        } else {
            throw new InvalidConfigException("Unsupported definition type for \"$class\": " . gettype($definition));
        }
    }

    /**
     * 返回对象定义或已加载的共享对象的列表
     * Returns the list of the object definitions or the loaded shared objects.
     * @return array the list of the object definitions or the loaded shared objects (type or ID => definition or instance).
     */
    public function getDefinitions()
    {
        return $this->_definitions;
    }

    /**
     * 创建指定类的一个实例.
     * 该方法将解析指定类的依赖项，实例化它们，并将它们注入到指定类的新实例中.
     * Creates an instance of the specified class.
     * This method will resolve dependencies of the specified class, instantiate them, and inject them into the new instance of the specified class.
     * @param string $class the class name
     * @param array $params constructor parameters
     * @param array $config configurations to be applied to the new instance
     * @return object the newly created instance of the specified class
     * @throws NotInstantiableException If resolved to an abstract class or an interface (since 2.0.9)
     */
    protected function build($class, $params, $config)
    {
        /* @var $reflection ReflectionClass */
        // 调用getDependencies来获取并缓存依赖信息，留意这里 list 的用法
        list ($reflection, $dependencies) = $this->getDependencies($class);

        // 用传入的 $params 的内容补充、覆盖到依赖信息中
        foreach ($params as $index => $param) {
            $dependencies[$index] = $param;
        }

        // 解析依赖信息，如果有依赖单元需要提前实例化，会在这一步完成
        $dependencies = $this->resolveDependencies($dependencies, $reflection);
        // 检查类是否可以实例化
        if (!$reflection->isInstantiable()) {
            // 如果类不能实例化，则抛出异常
            throw new NotInstantiableException($reflection->name);
        }
        // 如果配置为空
        if (empty($config)) {
            // 从给定的参数创建一个新的类实例
            return $reflection->newInstanceArgs($dependencies);
        }

        /**
         * 这个语句是两个条件：
         * 一是依赖信息不为空，也就是要么已经注册过依赖，要么为build() 传入构造函数参数。
         * 二是类必须实现 @see \yii\base\Configurable 接口
         * （实现该接口的类都需要支持通过构造函数的最后一个参数配置其属性（最后一个参数是配置数组））
         */
        if (!empty($dependencies) && $reflection->implementsInterface('yii\base\Configurable')) {
            // set $config as the last parameter (existing one will be overwritten)
            // 按照 Object 类的要求，构造函数的最后一个参数为 $config 数组
            $dependencies[count($dependencies) - 1] = $config;
            // 实例化这个对象
            return $reflection->newInstanceArgs($dependencies);
        } else {
            // 会出现异常的情况有二：
            // 一是依赖信息为空，也就是你前面又没注册过，现在又不提供构造函数参数，你让Yii怎么实例化？
            // 二是要构造的类，根本就不是 Object 类。
            $object = $reflection->newInstanceArgs($dependencies);
            foreach ($config as $name => $value) {
                $object->$name = $value;
            }
            return $object;
        }
    }

    /**
     * 将用户指定的构造函数参数与通过[[set()]]注册的参数合并
     * Merges the user-specified constructor parameters with the ones registered via [[set()]].
     * @param string $class class name, interface name or alias name
     * @param array $params the constructor parameters
     * @return array the merged parameters
     */
    protected function mergeParams($class, $params)
    {
        if (empty($this->_params[$class])) {
            return $params;
        } elseif (empty($params)) {
            return $this->_params[$class];
        } else {
            $ps = $this->_params[$class];
            foreach ($params as $index => $value) {
                $ps[$index] = $value;
            }
            return $ps;
        }
    }

    /**
     * 返回指定类的依赖项
     * 类名、接口名或别名
     * Returns the dependencies of the specified class.
     * @param string $class class name, interface name or alias name
     * @return array the dependencies of the specified class.
     */
    protected function getDependencies($class)
    {
        // 如果已经缓存了其依赖信息，直接返回缓存中的依赖信息
        if (isset($this->_reflections[$class])) {
            return [$this->_reflections[$class], $this->_dependencies[$class]];
        }

        $dependencies = [];
        // 使用PHP5 的反射机制来获取类的有关信息，主要就是为了获取依赖信息
        $reflection = new ReflectionClass($class);

        // 通过类的构建函数的参数来了解这个类依赖于哪些单元
        $constructor = $reflection->getConstructor();
        // 若构造函数存在
        if ($constructor !== null) {
            // 遍历构造函数的参数
            foreach ($constructor->getParameters() as $param) {
                if ($param->isDefaultValueAvailable()) {
                    // 构造函数如果有默认值，将默认值作为依赖。即然是默认值了，就肯定是简单类型了。
                    $dependencies[] = $param->getDefaultValue();
                } else {
                    // 获得类型提示类 eg: function construct(Model $model)
                    $c = $param->getClass();
                    // 构造函数没有默认值，则为其创建一个引用。就是前面提到的 Instance 类型。
                    $dependencies[] = Instance::of($c === null ? null : $c->getName());
                }
            }
        }
        // 将 ReflectionClass 对象缓存起来
        $this->_reflections[$class] = $reflection;
        // 将依赖信息缓存起来
        $this->_dependencies[$class] = $dependencies;

        return [$reflection, $dependencies];
    }

    /**
     * 处理依赖信息， 将依赖信息中保存的Istance实例所引用的类或接口进行实例化。
     * Resolves dependencies by replacing them with the actual object instances.
     * @param array $dependencies the dependencies
     * @param ReflectionClass $reflection the class reflection associated with the dependencies
     * @return array the resolved dependencies
     * @throws InvalidConfigException if a dependency cannot be resolved or if a dependency cannot be fulfilled.
     */
    protected function resolveDependencies($dependencies, $reflection = null)
    {
        // 前面getDependencies() 函数往 $_dependencies[] 中
        // 写入的是一个 Instance 数组
        foreach ($dependencies as $index => $dependency) {
            if ($dependency instanceof Instance) {
                if ($dependency->id !== null) {
                    // 向容器索要所依赖的实例，递归调用 yii\di\Container::get()
                    $dependencies[$index] = $this->get($dependency->id);
                } elseif ($reflection !== null) {
                    // 获取构造函数指定参数名称
                    $name = $reflection->getConstructor()->getParameters()[$index]->getName();
                    // 获取类名
                    $class = $reflection->getName();
                    throw new InvalidConfigException("Missing required parameter \"$name\" when instantiating \"$class\".");
                }
            }
        }
        return $dependencies;
    }

    /**
     * 调用一个回调，并在参数中解析依赖性。
     * Invoke a callback with resolving dependencies in parameters.
     *
     * 这种方法允许调用一个回调，并允许类型提示参数名作为容器的对象来解析。
     * 它还允许使用命名参数调用函数。
     * This methods allows invoking a callback and let type hinted parameter names to be resolved as objects of the Container.
     * It additionally allow calling function using named parameters.
     *
     * For example, the following callback may be invoked using the Container to resolve the formatter dependency:
     * 例如，可以使用Container调用下面的回调来解析格式化程序依赖项
     *
     * ```php
     * $formatString = function($string, \yii\i18n\Formatter $formatter) {
     *    // ...
     * }
     * Yii::$container->invoke($formatString, ['string' => 'Hello World!']);
     * ```
     * 这将使用字符串`'Hello World!'`作为第一个参数，一个由DI容器创建的formatter实例作为第二个参数 传送给callable。
     * This will pass the string `'Hello World!'` as the first param, and a formatter instance created by the DI container as the second param to the callable.
     *
     * @param callable $callback callable to be invoked.
     * @param array $params The array of parameters for the function.
     * This can be either a list of parameters, or an associative array representing named function parameters.
     * @return mixed the callback return value.
     * @throws InvalidConfigException if a dependency cannot be resolved or if a dependency cannot be fulfilled.
     * @throws NotInstantiableException If resolved to an abstract class or an interface (since 2.0.9)
     * @since 2.0.7
     */
    public function invoke(callable $callback, $params = [])
    {
        if (is_callable($callback)) {
            return call_user_func_array($callback, $this->resolveCallableDependencies($callback, $params));
        } else {
            return call_user_func_array($callback, $params);
        }
    }

    /**
     * 解析一个函数的依赖关系。
     * 该方法可用于实现在其他组件中[[invoke()]]所提供的类似功能。
     * Resolve dependencies for a function.
     *
     * This method can be used to implement similar functionality as provided by [[invoke()]] in other components.
     *
     * @param callable $callback callable to be invoked.
     * @param array $params The array of parameters for the function, can be either numeric or associative.
     * @return array The resolved dependencies.
     * @throws InvalidConfigException if a dependency cannot be resolved or if a dependency cannot be fulfilled.
     * @throws NotInstantiableException If resolved to an abstract class or an interface (since 2.0.9)
     * @since 2.0.7
     */
    public function resolveCallableDependencies(callable $callback, $params = [])
    {
        if (is_array($callback)) {
            // 获取方法相关信息的对象
            $reflection = new \ReflectionMethod($callback[0], $callback[1]);
        } else {
            // 获取函数相关信息的对象
            $reflection = new \ReflectionFunction($callback);
        }

        $args = [];

        // 判断$params是否为关联数组
        $associative = ArrayHelper::isAssociative($params);

        // 遍历参数
        foreach ($reflection->getParameters() as $param) {
            // 获取参数名
            $name = $param->getName();
            // 获得参数的类型提示类, 判断是否存在
            if (($class = $param->getClass()) !== null) {
                // 获得类名
                $className = $class->getName();
                // $params是关联数组，存在当前参数名，$params[$name] 是 当前提示类的实例
                if ($associative && isset($params[$name]) && $params[$name] instanceof $className) {
                    $args[] = $params[$name];
                    unset($params[$name]);
                // $params不是关联数组，第一个元素是 当前提示类的实例
                } elseif (!$associative && isset($params[0]) && $params[0] instanceof $className) {
                    $args[] = array_shift($params);

                // 从应用实例中获取当前参数名称的实例，该实例是 当前提示类的实例
                } elseif (isset(Yii::$app) && Yii::$app->has($name) && ($obj = Yii::$app->get($name)) instanceof $className) {
                    $args[] = $obj;
                } else {
                    // If the argument is optional we catch not instantiable exceptions
                    // 如果这个参数是可选的，我们就不会捕捉到实例化的异常
                    try {
                        $args[] = $this->get($className);
                    } catch (NotInstantiableException $e) {
                        if ($param->isDefaultValueAvailable()) {
                            $args[] = $param->getDefaultValue();
                        } else {
                            throw $e;
                        }
                    }

                }
            // $params 为关联数组，并存在$params[$name]
            } elseif ($associative && isset($params[$name])) {
                $args[] = $params[$name];
                unset($params[$name]);
            // $params 不是关联数组，$params 不为空
            } elseif (!$associative && count($params)) {
                $args[] = array_shift($params);
            // 参数有默认值，获取默认值
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            // 不是可选参数，报错
            } elseif (!$param->isOptional()) {
                $funcName = $reflection->getName();
                throw new InvalidConfigException("Missing required parameter \"$name\" when calling \"$funcName\".");
            }
        }

        // 合并 $params 中剩下的值
        foreach ($params as $value) {
            $args[] = $value;
        }
        return $args;
    }
}
