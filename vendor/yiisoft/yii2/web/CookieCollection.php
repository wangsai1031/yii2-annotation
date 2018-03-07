<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\web;

use Yii;
use ArrayIterator;
use yii\base\InvalidCallException;
use yii\base\Object;

/**
 * CookieCollection维护当前请求中可用的cookie
 * CookieCollection maintains the cookies available in the current request.
 *
 * 集合中的cookie数量。这个属性是只读的。
 * @property integer $count The number of cookies in the collection. This property is read-only.
 * 用于在集合中遍历cookie的迭代器。这个属性是只读的。
 * @property ArrayIterator $iterator An iterator for traversing the cookies in the collection. This property
 * is read-only.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class CookieCollection extends Object implements \IteratorAggregate, \ArrayAccess, \Countable
{
    /**
     * 是否只读取这个集合
     * @var boolean whether this collection is read only.
     */
    public $readOnly = false;

    /**
     * cookie的集合(由cookie名称索引)
     * @var Cookie[] the cookies in this collection (indexed by the cookie names)
     */
    private $_cookies = [];


    /**
     * Constructor.
     * @param array $cookies the cookies that this collection initially contains. This should be an array of name-value pairs.
     * @param array $config name-value pairs that will be used to initialize the object properties
     */
    public function __construct($cookies = [], $config = [])
    {
        $this->_cookies = $cookies;
        parent::__construct($config);
    }

    /**
     * 返回一个迭代器，用于遍历集合中的cookie。
     * 这个方法需要SPL接口[[\IteratorAggregate]]。
     * 当您使用foreach来遍历集合时，它将被隐式地调用。
     * Returns an iterator for traversing the cookies in the collection.
     * This method is required by the SPL interface [[\IteratorAggregate]].
     * It will be implicitly called when you use `foreach` to traverse the collection.
     * @return ArrayIterator an iterator for traversing the cookies in the collection.
     */
    public function getIterator()
    {
        // 将cookie放入一个数组迭代器对象中
        return new ArrayIterator($this->_cookies);
    }

    /**
     * 返回集合中cookie的数量。
     * 当使用 count($collection) 时会调用此方法。
     * Returns the number of cookies in the collection.
     * This method is required by the SPL `Countable` interface.
     * It will be implicitly called when you use `count($collection)`.
     * @return integer the number of cookies in the collection.
     */
    public function count()
    {
        return $this->getCount();
    }

    /**
     * 返回集合中cookie的数量。
     * Returns the number of cookies in the collection.
     * @return integer the number of cookies in the collection.
     */
    public function getCount()
    {
        return count($this->_cookies);
    }

    /**
     * 返回指定名称的cookie对象
     * Returns the cookie with the specified name.
     * @param string $name the cookie name
     * @return Cookie the cookie with the specified name. Null if the named cookie does not exist.
     * @see getValue()
     */
    public function get($name)
    {
        return isset($this->_cookies[$name]) ? $this->_cookies[$name] : null;
    }

    /**
     * 返回指定的cookie的值
     * Returns the value of the named cookie.
     * @param string $name the cookie name
     * @param mixed $defaultValue the value that should be returned when the named cookie does not exist.
     * @return mixed the value of the named cookie.
     * @see get()
     */
    public function getValue($name, $defaultValue = null)
    {
        return isset($this->_cookies[$name]) ? $this->_cookies[$name]->value : $defaultValue;
    }

    /**
     * 判断是否有指定名称的cookie。
     * 注意，如果一个cookie被标记为从浏览器中删除，这个方法将返回false。
     * Returns whether there is a cookie with the specified name.
     * Note that if a cookie is marked for deletion from browser, this method will return false.
     * @param string $name the cookie name
     * @return boolean whether the named cookie exists
     * @see remove()
     */
    public function has($name)
    {
        return isset($this->_cookies[$name]) && $this->_cookies[$name]->value !== ''
            && ($this->_cookies[$name]->expire === null || $this->_cookies[$name]->expire >= time());
    }

    /**
     * 在集合中添加一个cookie。
     * 如果在集合中已经有一个同名的cookie，那么它将首先被删除。
     * Adds a cookie to the collection.
     * If there is already a cookie with the same name in the collection, it will be removed first.
     * @param Cookie $cookie the cookie to be added
     * @throws InvalidCallException if the cookie collection is read only
     */
    public function add($cookie)
    {
        if ($this->readOnly) {
            throw new InvalidCallException('The cookie collection is read only.');
        }
        $this->_cookies[$cookie->name] = $cookie;
    }

    /**
     * 删除一个cookie
     * Removes a cookie.
     *
     * 若 `$removeFromBrowser` 是 true，则cookie将会从浏览器中删除。
     * 在这种情况下，将会把一个过期的cookie添加到集合中
     * If `$removeFromBrowser` is true, the cookie will be removed from the browser.
     * In this case, a cookie with outdated expiry will be added to the collection.
     *
     * cookie对象或要删除的cookie的名称
     * @param Cookie|string $cookie the cookie object or the name of the cookie to be removed.
     * 是否从浏览器中删除cookie
     * @param boolean $removeFromBrowser whether to remove the cookie from browser
     * @throws InvalidCallException if the cookie collection is read only
     */
    public function remove($cookie, $removeFromBrowser = true)
    {
        // 只读，则不允许删除
        if ($this->readOnly) {
            throw new InvalidCallException('The cookie collection is read only.');
        }
        // 若 $cookie 是对象，则将值设为空字符串，过期时间设为1（已过期）
        if ($cookie instanceof Cookie) {
            $cookie->expire = 1;
            $cookie->value = '';
        } else {
            // 否则，根据cookie名称实例化一个对象，
            // 将值设为空字符串，过期时间设为1（已过期）
            $cookie = new Cookie([
                'name' => $cookie,
                'expire' => 1,
            ]);
        }
        if ($removeFromBrowser) {
            // 从浏览器中删除cookie,把一个过期的cookie添加到集合中
            $this->_cookies[$cookie->name] = $cookie;
        } else {
            // todo 疑问？ 当 $removeFromBrowser 为false 时，只用到了 cookie 对象的 name 属性，则创建cookie对象的意义是什么？
            // 从cookie集合中删除该cookie
            unset($this->_cookies[$cookie->name]);
        }
    }

    /**
     * 删除所有cookie
     * Removes all cookies.
     * @throws InvalidCallException if the cookie collection is read only
     */
    public function removeAll()
    {
        if ($this->readOnly) {
            throw new InvalidCallException('The cookie collection is read only.');
        }
        $this->_cookies = [];
    }

    /**
     * 将集合作为一个PHP数组返回。
     * Returns the collection as a PHP array.
     * @return array the array representation of the collection.
     * The array keys are cookie names, and the array values are the corresponding cookie objects.
     */
    public function toArray()
    {
        return $this->_cookies;
    }

    /**
     * 从数组中填充cookie集合。
     * Populates the cookie collection from an array.
     * @param array $array the cookies to populate from
     * @since 2.0.3
     */
    public function fromArray(array $array)
    {
        $this->_cookies = $array;
    }

    /**
     * 判断指定名称的cookie是否存在。
     * 当使用 isset($collection[$name]) 时调用
     * Returns whether there is a cookie with the specified name.
     * This method is required by the SPL interface [[\ArrayAccess]].
     * It is implicitly called when you use something like `isset($collection[$name])`.
     * @param string $name the cookie name
     * @return boolean whether the named cookie exists
     */
    public function offsetExists($name)
    {
        return $this->has($name);
    }

    /**
     * 返回指定名称的cookie
     * Returns the cookie with the specified name.
     * This method is required by the SPL interface [[\ArrayAccess]].
     * It is implicitly called when you use something like `$cookie = $collection[$name];`.
     * This is equivalent to [[get()]].
     * @param string $name the cookie name
     * @return Cookie the cookie with the specified name, null if the named cookie does not exist.
     */
    public function offsetGet($name)
    {
        return $this->get($name);
    }

    /**
     * 将cookie添加到集合中。
     * Adds the cookie to the collection.
     * This method is required by the SPL interface [[\ArrayAccess]].
     * It is implicitly called when you use something like `$collection[$name] = $cookie;`.
     * This is equivalent to [[add()]].
     * @param string $name the cookie name
     * @param Cookie $cookie the cookie to be added
     */
    public function offsetSet($name, $cookie)
    {
        $this->add($cookie);
    }

    /**
     * 删除指定cookie
     * unset($collection[$name]) 时调用
     * Removes the named cookie.
     * This method is required by the SPL interface [[\ArrayAccess]].
     * It is implicitly called when you use something like `unset($collection[$name])`.
     * This is equivalent to [[remove()]].
     * @param string $name the cookie name
     */
    public function offsetUnset($name)
    {
        $this->remove($name);
    }
}
