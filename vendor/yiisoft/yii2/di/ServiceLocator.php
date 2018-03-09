<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\di;

use Closure;
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * ServiceLocator用于实现服务定位器模式。
 * ServiceLocator implements a [service locator](http://en.wikipedia.org/wiki/Service_locator_pattern).
 *
 * 要使用服务定位器，第一步是要注册相关组件。 组件可以通过 yii\di\ServiceLocator::set() 或 [[setComponents()]] 方法进行注册。
 * 然后您可以调用[[get()]]来检索指定ID的组件。
 * 定位器将根据定义自动地实例化和配置组件。
 * To use ServiceLocator, you first need to register component IDs with the corresponding component
 * definitions with the locator by calling [[set()]] or [[setComponents()]].
 * You can then call [[get()]] to retrieve a component with the specified ID. The locator will automatically
 * instantiate and configure the component according to the definition.
 *
 * For example,
 *
 * ```php
 * $locator = new \yii\di\ServiceLocator;
 *
 * // 通过一个可用于创建该组件的类名，注册 "cache" （缓存）组件。
    $locator->set('cache', 'yii\caching\ApcCache');

    // 通过一个可用于创建该组件的配置数组，注册 "db" （数据库）组件。
    $locator->set('db', [
        'class' => 'yii\db\Connection',
        'dsn' => 'mysql:host=localhost;dbname=demo',
        'username' => 'root',
        'password' => '',
    ]);

    // 通过一个能返回该组件的匿名函数，注册 "search" 组件。
    $locator->set('search', function () {
        return new app\components\SolrService;
    });

    // 用组件注册 "pageCache" 组件
    $locator->set('pageCache', new FileCache);
 *
 * $locator->setComponents([
 *     'db' => [
 *         'class' => 'yii\db\Connection',
 *         'dsn' => 'sqlite:path/to/file.db',
 *     ],
 *     'cache' => [
 *         'class' => 'yii\caching\DbCache',
 *         'db' => 'db',
 *     ],
 * ]);
 *
 * $db = $locator->get('db');  // or $locator->db
 * $cache = $locator->get('cache');  // or $locator->cache
 * ```
 *
 * 因为[[\yii\base\Module]]继承自ServiceLocator，模块和应用都是服务定位器
 * Because [[\yii\base\Module]] extends from ServiceLocator, modules and the application are all service locators.
 * Modules add [tree traversal](guide:concept-service-locator#tree-traversal) for service resolution.
 *
 * For more details and usage information on ServiceLocator, see the [guide article on service locators](guide:concept-service-locator).
 *
 * 组件定义或已加载组件实例的列表(ID => 定义或实例)
 * @property array $components The list of the component definitions or the loaded component instances (ID =>
 * definition or instance).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class ServiceLocator extends Component
{
    /**
     * // 用于缓存服务、组件等的实例
     * @var array shared component instances indexed by their IDs
     */
    private $_components = [];
    /**
     * 用于保存服务和组件的定义，通常为配置数组，可以用来创建具体的实例
     *
     *  - 配置数组。在向Service Locator索要服务或组件时，这个数组会被用于创建服务或组件的实例。
     * 与DI容器的要求类似，当定义是配置数组时，要求配置数组必须要有 class 元素，表示要创建的是什么类。不然你让Yii调用哪个构造函数？
        - PHP callable。每当向Service Locator索要实例时，这个PHP callable都会被调用，其返回值，就是所要的对象。
     * 对于这个PHP callable有一定的形式要求，一是它要返回一个服务或组件的实例。 二是它不接受任何的参数。
        - 对象。这个更直接，每当你索要某个特定实例时，直接把这个对象给你就是了。
        - 类名。即，使得 is_callable($definition, true) 为真的定义。
     * @var array component definitions indexed by their IDs
     */
    private $_definitions = [];


    /**
     * 重载了 getter 方法，使得访问服务和组件就跟访问类的属性一样。
     * 同时，也保留了原来Component的 getter所具有的功能。
     * 请留意，ServiceLocator 并未重载 __set()，
     * 仍然使用 yii\base\Component::__set()
     * Getter magic method.
     * This method is overridden to support accessing components like reading properties.
     * @param string $name component or property name
     * @return mixed the named property value
     */
    public function __get($name)
    {
        // has() 方法就是判断 $_definitions 数组中是否已经保存了服务或组件的定义
        // 请留意，这个时候服务或组件仅是完成定义，不一定已经实例化
        if ($this->has($name)) {
            // get() 方法用于返回服务或组件的实例
            return $this->get($name);
        }

        // 未定义的服务或组件，那么视为正常的属性、行为，
        // 调用 yii\base\Component::__get()
        return parent::__get($name);
    }

    /**
     * 对比Component，增加了对是否具有某个服务和组件的判断。
     * Checks if a property value is null.
     * This method overrides the parent implementation by checking if the named component is loaded.
     * @param string $name the property name or the event name
     * @return bool whether the property value is null
     */
    public function __isset($name)
    {
        if ($this->has($name)) {
            return true;
        }

        return parent::__isset($name);
    }

    /**
     * 返回一个值，指示定位器是否具有指定的组件定义或实例化该组件。
     * 当 $checkInstance === false 时，用于判断是否已经定义了某个服务或组件
     * 当 $checkInstance === true 时，用于判断是否已经有了某个服务或组件的实例
     * Returns a value indicating whether the locator has the specified component definition or has instantiated the component.
     * This method may return different results depending on the value of `$checkInstance`.
     *
     * - If `$checkInstance` is false (default), the method will return a value indicating whether the locator has the specified
     *   component definition.
     * - If `$checkInstance` is true, the method will return a value indicating whether the locator has
     *   instantiated the specified component.
     *
     * @param string $id component ID (e.g. `db`).
     * @param bool $checkInstance whether the method should check if the component is shared and instantiated.
     * @return bool whether the locator has the specified component definition or has instantiated the component.
     * @see set()
     */
    public function has($id, $checkInstance = false)
    {
        return $checkInstance ? isset($this->_components[$id]) : isset($this->_definitions[$id]);
    }

    /**
     * 根据 $id 获取对应的服务或组件的实例
     * Returns the component instance with the specified ID.
     *
     * Service Locator创建获取服务或组件实例的过程是：

        - 看看缓存数组 $_components 中有没有已经创建好的实例。有的话，皆大欢喜，直接用缓存中的就可以了。
        - 缓存中没有的话，那就要从定义开始创建了。
        - 如果服务或组件的定义是个对象，那么直接把这个对象作为服务或组件的实例返回就可以了。
     * 但有一点要注意，当使用一个PHP callable定义一个服务或组件时，这个定义是一个Closure类的对象。
     * 这种定义虽然也对象，但是可不能把这种对象直接当成服务或组件的实例返回。
        - 如果定义是一个数组或者一个PHP callable，那么把这个定义作为参数，调用 Yii::createObject() 来创建实例。
     *
     * @param string $id component ID (e.g. `db`).
     * @param bool $throwException whether to throw an exception if `$id` is not registered with the locator before.
     * @return object|null the component of the specified ID. If `$throwException` is false and `$id`
     * is not registered before, null will be returned.
     * @throws InvalidConfigException if `$id` refers to a nonexistent component ID
     * @see has()
     * @see set()
     */
    public function get($id, $throwException = true)
    {
        // 如果已经有实例化好的组件或服务，直接使用缓存中的就OK了
        if (isset($this->_components[$id])) {
            return $this->_components[$id];
        }

        // 如果还没有实例化好，那么再看看是不是已经定义好
        if (isset($this->_definitions[$id])) {
            $definition = $this->_definitions[$id];
            // 如果定义是个对象，且不是Closure对象，那么直接将这个对象返回
            if (is_object($definition) && !$definition instanceof Closure) {
                // 实例化后，保存进 $_components 数组中，以后就可以直接引用了
                return $this->_components[$id] = $definition;
            }

            // 是个数组或者PHP callable，调用 Yii::createObject()来创建一个实例
            // 实例化后，保存进 $_components 数组中，以后就可以直接引用了
            return $this->_components[$id] = Yii::createObject($definition);
        } elseif ($throwException) {
            throw new InvalidConfigException("Unknown component ID: $id");
        }

        // 即没实例化，也没定义，万能的Yii也没办法通过一个任意的ID，
        // 就给你找到想要的组件或服务呀，给你个 null 吧。
        // 表示Service Locator中没有这个ID的服务或组件。
        return null;
    }

    /**
     * 用于注册一个组件或服务，其中 $id 用于标识服务或组件。
     *
     * Registers a component definition with this locator.
     * 用这个定位器注册一个组件定义
     *
     * For example,
     *
     * ```php
     * 类名
     * // a class name
     * $locator->set('cache', 'yii\caching\FileCache');
     *
     * 配置数组
     * // a configuration array
     * $locator->set('db', [
     *     'class' => 'yii\db\Connection',
     *     'dsn' => 'mysql:host=127.0.0.1;dbname=demo',
     *     'username' => 'root',
     *     'password' => '',
     *     'charset' => 'utf8',
     * ]);
     *
     * 匿名函数
     * // an anonymous function
     * $locator->set('cache', function ($params) {
     *     return new \yii\caching\FileCache;
     * });
     *
     * 实例
     * // an instance
     * $locator->set('cache', new \yii\caching\FileCache);
     * ```
     *
     * 如果一个具有相同ID的组件定义已经存在，那么它将被覆盖
     * If a component definition with the same ID already exists, it will be overwritten.
     *
     * @param string $id component ID (e.g. `db`).
     * @param mixed $definition the component definition to be registered with this locator.
     * It can be one of the following:
     *
     * $definition 可以是一个类名，一个配置数组，一个PHP callable，或者一个对象
     * - a class name
     * - a configuration array: the array contains name-value pairs that will be used to
     *   initialize the property values of the newly created object when [[get()]] is called.
     *   The `class` element is required and stands for the the class of the object to be created.
     * - a PHP callable: either an anonymous function or an array representing a class method (e.g. `['Foo', 'bar']`).
     *   The callable will be called by [[get()]] to return an object associated with the specified component ID.
     * - an object: When [[get()]] is called, this object will be returned.
     *
     * @throws InvalidConfigException if the definition is an invalid configuration array
     */
    public function set($id, $definition)
    {
        unset($this->_components[$id]);

        // 当定义为 null 时，表示要从Service Locator中删除一个服务或组件
        if ($definition === null) {
            // 确保服务或组件ID的唯一性
            unset($this->_definitions[$id]);
            return;
        }

        // 定义如果是个对象或PHP callable，或类名，直接作为定义保存
        // 留意这里 is_callable的第二个参数为true，所以，类名也可以。
        if (is_object($definition) || is_callable($definition, true)) {
            // an object, a class name, or a PHP callable
            // 定义的过程，只是写入了 $_definitions 数组
            $this->_definitions[$id] = $definition;
        } elseif (is_array($definition)) {
            // 定义如果是个数组，要确保数组中具有 class 元素
            // a configuration array
            if (isset($definition['class'])) {
                // 定义的过程，只是写入了 $_definitions 数组
                $this->_definitions[$id] = $definition;
            } else {
                throw new InvalidConfigException("The configuration for the \"$id\" component must contain a \"class\" element.");
            }
        } else {
            throw new InvalidConfigException("Unexpected configuration type for the \"$id\" component: " . gettype($definition));
        }
    }

    /**
     * 删除一个服务或组件
     * Removes the component from the locator.
     * @param string $id the component ID
     */
    public function clear($id)
    {
        unset($this->_definitions[$id], $this->_components[$id]);
    }

    /**
     * 用于返回Service Locator的 $_components 数组或 $_definitions 数组，
     * 同时也是 components 属性的getter函数
     * Returns the list of the component definitions or the loaded component instances.
     * @param bool $returnDefinitions whether to return component definitions instead of the loaded component instances.
     * @return array the list of the component definitions or the loaded component instances (ID => definition or instance).
     */
    public function getComponents($returnDefinitions = true)
    {
        return $returnDefinitions ? $this->_definitions : $this->_components;
    }

    /**
     * 批量方式注册服务或组件，同时也是 components 属性的setter函数
     * Registers a set of component definitions in this locator.
     *
     * This is the bulk version of [[set()]]. The parameter should be an array
     * whose keys are component IDs and values the corresponding component definitions.
     *
     * For more details on how to specify component IDs and definitions, please refer to [[set()]].
     *
     * If a component definition with the same ID already exists, it will be overwritten.
     *
     * The following is an example for registering two component definitions:
     *
     * ```php
     * [
     *     'db' => [
     *         'class' => 'yii\db\Connection',
     *         'dsn' => 'sqlite:path/to/file.db',
     *     ],
     *     'cache' => [
     *         'class' => 'yii\caching\DbCache',
     *         'db' => 'db',
     *     ],
     * ]
     * ```
     *
     * @param array $components component definitions or instances
     */
    public function setComponents($components)
    {
        foreach ($components as $id => $component) {
            $this->set($id, $component);
        }
    }
}
