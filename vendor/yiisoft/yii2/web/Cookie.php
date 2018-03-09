<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\web;

/**
 * Cookie表示与Cookie相关的信息，例如名称、值、域等
 * Cookie represents information related with a cookie, such as [[name]], [[value]], [[domain]], etc.
 *
 * For more details and usage information on Cookie, see the [guide article on handling cookies](guide:runtime-sessions-cookies).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Cookie extends \yii\base\BaseObject
{
    /**
     * cookie名称
     * @var string name of the cookie
     */
    public $name;
    /**
     * cookie值
     * @var string value of the cookie
     */
    public $value = '';
    /**
     * cookie的有效域
     * @var string domain of the cookie
     */
    public $domain = '';
    /**
     * cookie过期的时间戳。这是服务器时间戳。
     * 默认值为0，这意味着“直到浏览器关闭”cookie才会过期。
     * @var int the timestamp at which the cookie expires. This is the server timestamp.
     * Defaults to 0, meaning "until the browser is closed".
     */
    public $expire = 0;
    /**
     * cookie在服务器上的有效路径
     * @var string the path on the server in which the cookie will be available on. The default is '/'.
     */
    public $path = '/';
    /**
     * cookie是否应该通过安全连接发送
     * @var bool whether cookie should be sent via secure connection
     */
    public $secure = false;
    /**
     * cookie是否只能通过HTTP协议访问。
     * 通过将该属性设置为true。可以禁止通过脚本(例如JavaScript)直接获取cookie。
     * 这样可以有效地防止通过XSS攻击盗取用户身份信息。
     * @var bool whether the cookie should be accessible only through the HTTP protocol.
     * By setting this property to true, the cookie will not be accessible by scripting languages,
     * such as JavaScript, which can effectively help to reduce identity theft through XSS attacks.
     */
    public $httpOnly = true;


    /**
     * 将cookie对象转换为字符串的魔术方法，而无需显式访问值
     * Magic method to turn a cookie object into a string without having to explicitly access [[value]].
     *
     * ```php
     * if (isset($request->cookies['name'])) {
     *     $value = (string) $request->cookies['name'];
     * }
     * ```
     *
     * @return string The value of the cookie. If the value property is null, an empty string will be returned.
     */
    public function __toString()
    {
        return (string) $this->value;
    }
}
