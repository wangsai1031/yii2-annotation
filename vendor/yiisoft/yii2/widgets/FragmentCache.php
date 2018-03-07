<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\widgets;

use Yii;
use yii\base\Widget;
use yii\caching\Cache;
use yii\caching\Dependency;
use yii\di\Instance;

/**
 * 片段缓存
 * FragmentCache is used by [[\yii\base\View]] to provide caching of page fragments.
 *
 * @property string|boolean $cachedContent The cached content. False is returned if valid content is not found
 * in the cache. This property is read-only.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class FragmentCache extends Widget
{
    /**
     * 缓存对象或缓存对象的应用程序组件ID。
     * 在创建了片段缓存对象之后，如果您想要更改这个属性，那么您应该只能使用一个缓存对象来分配它。
     * @var Cache|array|string the cache object or the application component ID of the cache object.
     * After the FragmentCache object is created, if you want to change this property, you should only assign it with a cache object.
     * Starting from version 2.0.2, this can also be a configuration array for creating the object.
     */
    public $cache = 'cache';
    /**
     * 过期时间。
     * 数据在缓存中有效的秒数。
     * 使用0表示缓存的数据永远不会过期。
     * @var integer number of seconds that the data can remain valid in cache.
     * Use 0 to indicate that the cached data will never expire.
     */
    public $duration = 60;
    /**
     * 缓存的内容的依赖项
     * @var array|Dependency the dependency that the cached content depends on.
     *
     * 该选项的值可以是一个 yii\caching\Dependency 类的派生类，也可以是创建缓存对象的配置数组。
     * This can be either a [[Dependency]] object or a configuration array for creating the dependency object.
     * For example,
     *
     * ```php
     * [
     *     'class' => 'yii\caching\DbDependency',
     *     'sql' => 'SELECT MAX(updated_at) FROM post',
     * ]
     * ```
     *
     * would make the output cache depends on the last modified time of all posts.
     * If any post has its modification time changed, the cached content would be invalidated.
     */
    public $dependency;
    /**
     * 变化：
     * 缓存的内容可能需要根据一些参数的更改而变化。例如一个 Web 应用 支持多语言，同一段视图代码也许需要生成多个语言的内容。
     * 因此可以设置缓存根据应用当前语言而变化。
     *
     * 导致缓存内容变化的因素列表。
     * 每个因素都是一个表示变体的字符串(例如，语言，GET参数)
     *
     * 下面的变化设置将根据当前的应用程序语言将内容缓存在不同版本中
     * @var array list of factors that would cause the variation of the content being cached.
     * Each factor is a string representing a variation (e.g. the language, a GET parameter).
     * The following variation setting will cause the content to be cached in different versions according to the current application language:
     *
     * ```php
     * [
     *     Yii::$app->language,
     * ]
     * ```
     */
    public $variations;
    /**
     * 是否启用分段缓存。
     * 您可以使用该属性根据特定设置来打开和关闭片段缓存。
     * 例如：仅为GET请求启用片段缓存
     * @var boolean whether to enable the fragment cache.
     * You may use this property to turn on and off the fragment cache according to specific setting (e.g. enable fragment cache only for GET requests).
     */
    public $enabled = true;
    /**
     * 嵌入动态内容的占位符列表。
     * 该属性在内部用于实现内容缓存特性。
     * 不要修改它。
     * @var array a list of placeholders for embedding dynamic contents.
     * This property is used internally to implement the content caching feature.
     * Do not modify it.
     */
    public $dynamicPlaceholders;


    /**
     * 初始化片段缓存对象
     * Initializes the FragmentCache object.
     */
    public function init()
    {
        parent::init();

        // 若启用片段缓存，则实例化缓存组件
        $this->cache = $this->enabled ? Instance::ensure($this->cache, Cache::className()) : null;

        // 片段缓存中没有找到内容
        if ($this->cache instanceof Cache && $this->getCachedContent() === false) {
            // 将当前片段缓存小部件对象 加入到 当前激活的片段缓存小部件的列表。
            $this->getView()->cacheStack[] = $this;
            // 开启ob缓存
            ob_start();
            ob_implicit_flush(false);
        }
    }

    /**
     * 标记要缓存的内容的结束部分。
     * 在此方法调用之前和init()之后显示的内容将被捕获并保存在缓存中。
     * 如果在缓存中已经找到了有效的内容，那么这个方法就什么都不做了。
     * Marks the end of content to be cached.
     * Content displayed before this method call and after [[init()]] will be captured and saved in cache.
     * This method does nothing if valid content is already found in cache.
     */
    public function run()
    {
        // 如果在缓存中已经找到了有效的内容，那么这个方法就什么都不做了。
        if (($content = $this->getCachedContent()) !== false) {
            echo $content;
        } elseif ($this->cache instanceof Cache) {
            array_pop($this->getView()->cacheStack);

            // 获取页面需要缓存部分的内容（beginCache() 与 endCache() 之间的内容）
            $content = ob_get_clean();
            if ($content === false || $content === '') {
                return;
            }
            // 有依赖，则实例化依赖对象
            if (is_array($this->dependency)) {
                $this->dependency = Yii::createObject($this->dependency);
            }

            $data = [$content, $this->dynamicPlaceholders];
            // 加入缓存
            $this->cache->set($this->calculateKey(), $data, $this->duration, $this->dependency);

            if (empty($this->getView()->cacheStack) && !empty($this->dynamicPlaceholders)) {
                // 更新动态内容
                $content = $this->updateDynamicContent($content, $this->dynamicPlaceholders);
            }
            echo $content;
        }
    }

    /**
     * @var string|boolean the cached content. False if the content is not cached.
     */
    private $_content;

    /**
     * 如果可用，返回缓存的内容,如果在缓存中没有找到有效的内容，则返回False。
     * Returns the cached content if available.
     * @return string|false the cached content. False is returned if valid content is not found in the cache.
     */
    public function getCachedContent()
    {
        if ($this->_content === null) {
            $this->_content = false;
            if ($this->cache instanceof Cache) {
                // 生成用于在缓存中存储内容的惟一密钥。
                // 生成的键取决于[[id]]和[[variations]]。
                $key = $this->calculateKey();
                // 从缓存中查数据
                $data = $this->cache->get($key);
                // 如果缓存的数据是数组，并且数组元素个数为2
                if (is_array($data) && count($data) === 2) {
                    // 分别将数据中的两个元素赋值给 $content, $placeholders
                    list ($content, $placeholders) = $data;
                    // 若 $placeholders 不为空数组
                    if (is_array($placeholders) && count($placeholders) > 0) {
                        // 若 $this->getView()->cacheStack 为空，则说明是最外层的缓存
                        if (empty($this->getView()->cacheStack)) {
                            // 通过运行动态语句的结果来替换内容中的占位符
                            // outermost cache: replace placeholder with dynamic content
                            $content = $this->updateDynamicContent($content, $placeholders);
                        }
                        // 遍历占位符数组
                        foreach ($placeholders as $name => $statements) {
                            // 为动态内容添加占位符。
                            $this->getView()->addDynamicPlaceholder($name, $statements);
                        }
                    }
                    // 若 $placeholders 为空数组，
                    $this->_content = $content;
                }
            }
        }

        return $this->_content;
    }

    /**
     * 通过运行动态语句的结果来替换内容中的占位符
     * Replaces placeholders in content by results of evaluated dynamic statements.
     *
     * @param string $content
     * @param array $placeholders
     * @return string final content
     */
    protected function updateDynamicContent($content, $placeholders)
    {
        // 遍历动态内容占位符， $name 为 占位符名称， $statements 为 PHP 语句
        foreach ($placeholders as $name => $statements) {
            // 运行给定的PHP语句。将返回结果赋给 $placeholders[$name]
            $placeholders[$name] = $this->getView()->evaluateDynamicContent($statements);
        }

        // 替换$content中的占位符
        return strtr($content, $placeholders);
    }

    /**
     * 生成用于在缓存中存储内容的惟一密钥。
     * 生成的键取决于[[id]]和[[variations]]。
     * Generates a unique key used for storing the content in cache.
     * The key generated depends on both [[id]] and [[variations]].
     * @return mixed a valid cache key
     */
    protected function calculateKey()
    {
        $factors = [__CLASS__, $this->getId()];
        if (is_array($this->variations)) {
            // 遍历 [[variations]]
            foreach ($this->variations as $factor) {
                $factors[] = $factor;
            }
        }

        return $factors;
    }
}
