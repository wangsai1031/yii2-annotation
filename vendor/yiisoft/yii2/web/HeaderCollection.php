<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\web;

use Yii;
use yii\base\Object;
use ArrayIterator;

/**
 * HeaderCollection用于[[Response]]来维护当前注册的HTTP头信息。
 * HeaderCollection is used by [[Response]] to maintain the currently registered HTTP headers.
 *
 * 集合中的头的数量.这个属性是只读的.
 * @property integer $count The number of headers in the collection. This property is read-only.
 * 用于遍历集合中的头部的迭代器.这个属性是只读的.
 * @property ArrayIterator $iterator An iterator for traversing the headers in the collection. This property
 * is read-only.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class HeaderCollection extends Object implements \IteratorAggregate, \ArrayAccess, \Countable
{
    /**
     * 集合中的HTTP头(由HTTP头名称索引)
     *
     *  {
            "content-type": [
                "text\/html; charset=UTF-8"
            ]
        }

        {
            "host": [
                "task.bluelive.o"
            ],
            "user-agent": [
                "Mozilla\/5.0 (Windows NT 10.0; WOW64; rv:49.0) Gecko\/20100101 Firefox\/49.0"
            ],
            "accept": [
                "text\/html,application\/xhtml+xml,application\/xml;q=0.9,*\/*;q=0.8"
            ],
            "accept-language": [
                "zh-CN,zh;q=0.8,en-US;q=0.5,en;q=0.3"
            ],
            "accept-encoding": [
                "gzip, deflate"
            ],
            "referer": [
                "http:\/\/user.bluelive.o\/login?return_url=http:\/\/task.bluelive.o\/site\/index"
            ],
            "cookie": [
                "_csrf=20b90eaafe72859e6ff50792212bf9340012f28e380efdaea654b8e680352cf1a%3A2%3A%7Bi%3A0%3Bs%3A5%3A%22_csrf%22%3Bi%3A1%3Bs%3A32%3A%22hSjYfCqYxRxY18MSsese6hoUWTTJ33YD%22%3B%7D; PHPSESSID=54pqrs48islpvsmnhu8qdesla1; _identity=4588675d6443115ae00699cf51b9df0b267788553c02293394215f6e2bc0070ca%3A2%3A%7Bi%3A0%3Bs%3A9%3A%22_identity%22%3Bi%3A1%3Bs%3A46%3A%22%5B32%2C%22K1r5cqGo9D6Rm8eDwBPs1SL4DSRRQhIO%22%2C604800%5D%22%3B%7D"
            ],
            "connection": [
                "keep-alive"
            ],
            "upgrade-insecure-requests": [
                "1"
            ],
            "cache-control": [
                "max-age=0"
            ]
        }
     *
     * @var array the headers in this collection (indexed by the header names)
     */
    private $_headers = [];


    /**
     * 返回一个迭代器，用于遍历集合中的HTTP头部.
     * 这个方法是实现迭代器[[\IteratorAggregate]]接口必须的。
     * 当您使用foreach来遍历集合时，它将被隐式地调用
     * Returns an iterator for traversing the headers in the collection.
     * This method is required by the SPL interface [[\IteratorAggregate]].
     * It will be implicitly called when you use `foreach` to traverse the collection.
     * @return ArrayIterator an iterator for traversing the headers in the collection.
     */
    public function getIterator()
    {
        return new ArrayIterator($this->_headers);
    }

    /**
     * 返回集合中HTTP头的数量.
     * 这个方法是由SPL`Countable`接口所必须的。
     * 当您使用count($collection)时，将隐式地调用它。
     * Returns the number of headers in the collection.
     * This method is required by the SPL `Countable` interface.
     * It will be implicitly called when you use `count($collection)`.
     * @return integer the number of headers in the collection.
     */
    public function count()
    {
        return $this->getCount();
    }

    /**
     * 返回集合中HTTP头的数量。
     * Returns the number of headers in the collection.
     * @return integer the number of headers in the collection.
     */
    public function getCount()
    {
        return count($this->_headers);
    }

    /**
     * 返回指定的HTTP头
     * Returns the named header(s).
     * @param string $name the name of the header to return
     * @param mixed $default the value to return in case the named header does not exist
     * 是否只返回指定名称的第一个HTTP头。
     * 如果是false，将返回指定名称的所有HTTP头部。
     * @param boolean $first whether to only return the first header of the specified name.
     * If false, all headers of the specified name will be returned.
     * @return string|array the named header(s). If `$first` is true, a string will be returned;
     * If `$first` is false, an array will be returned.
     */
    public function get($name, $default = null, $first = true)
    {
        // 将名称转为小写字母
        $name = strtolower($name);
        // 判断指定Http头是否存在
        if (isset($this->_headers[$name])) {
            // 是否只返回指定名称的第一个HTTP头。
            // todo ? 得很么情况下会指定名称下出现多个HTTP头
            return $first ? reset($this->_headers[$name]) : $this->_headers[$name];
        } else {
            return $default;
        }
    }

    /**
     * 添加一个新HTTP头.
     * 如果已经有一个同名的HTTP头，它将被替换
     * Adds a new header.
     * If there is already a header with the same name, it will be replaced.
     * @param string $name the name of the header
     * @param string $value the value of the header
     * @return $this the collection object itself
     */
    public function set($name, $value = '')
    {
        $name = strtolower($name);
        $this->_headers[$name] = (array) $value;

        return $this;
    }

    /**
     * 添加一个新HTTP头.
     * 如果已经有一个同名的HTTP头，将在它后面附加一条，而不是替换它
     * Adds a new header.
     * If there is already a header with the same name, the new one will be appended to it instead of replacing it.
     * @param string $name the name of the header
     * @param string $value the value of the header
     * @return $this the collection object itself
     */
    public function add($name, $value)
    {
        $name = strtolower($name);
        $this->_headers[$name][] = $value;

        return $this;
    }

    /**
     * 仅当指定名称的HTTP头不存在时，才设置一个新的HTTP头。
     * 如果已经有一个同名的头，那么新的就会被忽略。
     * Sets a new header only if it does not exist yet.
     * If there is already a header with the same name, the new one will be ignored.
     * @param string $name the name of the header
     * @param string $value the value of the header
     * @return $this the collection object itself
     */
    public function setDefault($name, $value)
    {
        $name = strtolower($name);
        if (empty($this->_headers[$name])) {
            $this->_headers[$name][] = $value;
        }

        return $this;
    }

    /**
     * 判断指定的HTTP头是否存在
     * Returns a value indicating whether the named header exists.
     * @param string $name the name of the header
     * @return boolean whether the named header exists
     */
    public function has($name)
    {
        $name = strtolower($name);

        return isset($this->_headers[$name]);
    }

    /**
     * 删除一个HTTP头
     * Removes a header.
     * @param string $name the name of the header to be removed.
     * 被删除的HTTP头的值。如果HTTP头不存在，则返回Null。
     * @return array the value of the removed header. Null is returned if the header does not exist.
     */
    public function remove($name)
    {
        $name = strtolower($name);
        if (isset($this->_headers[$name])) {
            $value = $this->_headers[$name];
            unset($this->_headers[$name]);
            return $value;
        } else {
            return null;
        }
    }

    /**
     * 删除所有HTTP头
     * Removes all headers.
     */
    public function removeAll()
    {
        $this->_headers = [];
    }

    /**
     * 将集合作为一个PHP数组返回。
     * 集合的数组表示。
     * 数组键是HTTP头名，数组值是对应的HTTP头值。
     * Returns the collection as a PHP array.
     * @return array the array representation of the collection.
     * The array keys are header names, and the array values are the corresponding header values.
     */
    public function toArray()
    {
        return $this->_headers;
    }

    /**
     * 使用一个数组的值填充HTTP头集合
     * Populates the header collection from an array.
     * @param array $array the headers to populate from
     * @since 2.0.3
     */
    public function fromArray(array $array)
    {
        $this->_headers = $array;
    }

    /**
     * 返回是否有一个带有指定名称的HTTP头
     * 这个方法是SPL接口[[\ArrayAccess]]必须的。
     * 当您使用`isset($collection[$name])`时，它会被隐式地调用。
     * Returns whether there is a header with the specified name.
     * This method is required by the SPL interface [[\ArrayAccess]].
     * It is implicitly called when you use something like `isset($collection[$name])`.
     * @param string $name the header name
     * @return boolean whether the named header exists
     */
    public function offsetExists($name)
    {
        return $this->has($name);
    }

    /**
     * 返回指定名称的HTTP头。
     * 这个方法是SPL接口[[\ArrayAccess]]必须的。
     * 当你使用类似 `$header = $collection[$name];` 时，它将被隐式的调用。
     * 这个方法相当于get().
     * Returns the header with the specified name.
     * This method is required by the SPL interface [[\ArrayAccess]].
     * It is implicitly called when you use something like `$header = $collection[$name];`.
     * This is equivalent to [[get()]].
     * @param string $name the header name
     * @return string the header value with the specified name, null if the named header does not exist.
     */
    public function offsetGet($name)
    {
        return $this->get($name);
    }

    /**
     * 将指定HTTP头添加到集合中.
     * 这个方法是SPL接口[[\ArrayAccess]]必须的。
     * 当你使用类似 `$collection[$name] = $header;`时，它会被隐式地调用。
     * 这个方法相当于[[set()]].(译者：原文写错了吧)
     * Adds the header to the collection.
     * This method is required by the SPL interface [[\ArrayAccess]].
     * It is implicitly called when you use something like `$collection[$name] = $header;`.
     * This is equivalent to [[add()]].
     * @param string $name the header name
     * @param string $value the header value to be added
     */
    public function offsetSet($name, $value)
    {
        $this->set($name, $value);
    }

    /**
     * 删除指定的HTTP头。
     * 这个方法是SPL接口[[\ArrayAccess]]必须的。
     * 当使用 `unset($collection[$name])` 时，它会被隐式地调用。
     * 这个方法相当于[[remove()]].
     * Removes the named header.
     * This method is required by the SPL interface [[\ArrayAccess]].
     * It is implicitly called when you use something like `unset($collection[$name])`.
     * This is equivalent to [[remove()]].
     * @param string $name the header name
     */
    public function offsetUnset($name)
    {
        $this->remove($name);
    }
}
