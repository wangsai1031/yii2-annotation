<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\helpers;

use yii\base\Arrayable;
use yii\base\InvalidArgumentException;
use yii\web\JsExpression;
use yii\base\Model;

/**
 * BaseJson 为 [[Json]] 提供具体的实现
 * BaseJson provides concrete implementation for [[Json]].
 *
 * 不要直接使用 BaseJson, 使用 Json
 * Do not use BaseJson. Use [[Json]] instead.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class BaseJson
{
    /**
     * List of JSON Error messages assigned to constant names for better handling of version differences.
     * @var array
     * @since 2.0.7
     */
    public static $jsonErrorMessages = [
        // 超过最大深度
        'JSON_ERROR_DEPTH' => 'The maximum stack depth has been exceeded.',
        // 无效或畸形的JSON
        'JSON_ERROR_STATE_MISMATCH' => 'Invalid or malformed JSON.',
        // 控制字符错误，可能使用了不正确的编码
        'JSON_ERROR_CTRL_CHAR' => 'Control character error, possibly incorrectly encoded.',
        // json 语法错误
        'JSON_ERROR_SYNTAX' => 'Syntax error.',
        // 畸形的utf - 8字符，可能使用了不正确的编码
        'JSON_ERROR_UTF8' => 'Malformed UTF-8 characters, possibly incorrectly encoded.', // PHP 5.3.3
        // 编码值中存在一个或多个递归引用
        'JSON_ERROR_RECURSION' => 'One or more recursive references in the value to be encoded.', // PHP 5.5.0
        'JSON_ERROR_INF_OR_NAN' => 'One or more NAN or INF values in the value to be encoded', // PHP 5.5.0
        // 一个不能被编码的类型的值
        'JSON_ERROR_UNSUPPORTED_TYPE' => 'A value of a type that cannot be encoded was given', // PHP 5.5.0
    ];


    /**
     * 将给定值编码为JSON字符串
     * Encodes the given value into a JSON string.
     *
     * 该方法比 `json_encode()` 增加了支持 JavaScript 表达式的功能
     * The method enhances `json_encode()` by supporting JavaScript expressions.
     * 该方法不会对使用JsExpression 对象的 JavaScript表达式进行编码
     * In particular, the method will not encode a JavaScript expression that is
     * represented in terms of a [[JsExpression]] object.
     *
     * Note that data encoded as JSON must be UTF-8 encoded according to the JSON specification.
     * You must ensure strings passed to this method have proper encoding before passing them.
     *
     * @param mixed $value the data to be encoded.
     * 编码选项
     * @param int $options the encoding options. For more details please refer to
     * <http://www.php.net/manual/en/function.json-encode.php>. Default is `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`.
     * @return string the encoding result.
     * @throws InvalidArgumentException if there is any encoding error.
     */
    public static function encode($value, $options = 320)
    {
        $expressions = [];

        /**
         * uniqid() 函数基于以微秒计的当前时间，生成一个唯一的 ID。
         * @link http://www.w3school.com.cn/php/func_misc_uniqid.asp
         * 在发送数据给 `json_encode()` 之前对数据进行预处理
         */
        $value = static::processData($value, $expressions, uniqid('', true));

        /**
         * set_error_handler() 设置用户自定义的错误处理函数
         * @link http://php.net/manual/zh/function.set-error-handler.php
         */
        set_error_handler(function () {
            static::handleJsonError(JSON_ERROR_SYNTAX);
        }, E_WARNING);
        $json = json_encode($value, $options);
        /**
         * restore_error_handler() 函数恢复之前的错误处理程序
         * 在使用 set_error_handler() 改变错误处理函数之后，此函数可以用于还原之前的错误处理程序。
         */
        restore_error_handler();

        // 获取错误信息
        static::handleJsonError(json_last_error());

        /** strtr() 函数转换字符串中特定的字符 */
        return $expressions === [] ? $json : strtr($json, $expressions);
    }

    /**
     * 将给定的值编码为一个JSON字符串的HTML转义实体，这样就可以安全地嵌入到HTML代码中了
     * Encodes the given value into a JSON string HTML-escaping entities so it is safe to be embedded in HTML code.
     *
     * 该方法比 `json_encode()` 增加了支持 JavaScript 表达式的功能
     * The method enhances `json_encode()` by supporting JavaScript expressions.
     * 该方法不会对使用JsExpression 对象的 JavaScript表达式进行编码
     * In particular, the method will not encode a JavaScript expression that is
     * represented in terms of a [[JsExpression]] object.
     *
     * Note that data encoded as JSON must be UTF-8 encoded according to the JSON specification.
     * You must ensure strings passed to this method have proper encoding before passing them.
     *
     * @param mixed $value the data to be encoded
     * @return string the encoding result
     * @since 2.0.4
     * @throws InvalidArgumentException if there is any encoding error
     */
    public static function htmlEncode($value)
    {
        return static::encode($value, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
    }

    /**
     * 将给定的JSON字符串解码成PHP数组或对象（默认是数组）
     *
     * Decodes the given JSON string into a PHP data structure.
     * @param string $json the JSON string to be decoded
     * 是否用关联数组来返回对象
     * @param bool $asArray whether to return objects in terms of associative arrays.
     * @return mixed the PHP data
     * 
     * 发生任何解码错误都会抛出异常。
     * @throws InvalidArgumentException if there is any decoding error
     */
    public static function decode($json, $asArray = true)
    {
        // 不能是数组或空值
        if (is_array($json)) {
            throw new InvalidArgumentException('Invalid JSON data.');
        } elseif ($json === null || $json === '') {
            return null;
        }
        $decode = json_decode((string) $json, $asArray);

        // 自定义异常处理
        static::handleJsonError(json_last_error());

        return $decode;
    }

    /**
     * 通过抛出相应的错误消息来处理[[encode()]] and [[decode()]]错误。
     *
     * Handles [[encode()]] and [[decode()]] errors by throwing exceptions with the respective error message.
     *
     * @param int $lastError error code from [json_last_error()](http://php.net/manual/en/function.json-last-error.php).
     * @throws InvalidArgumentException if there is any encoding/decoding error.
     * @since 2.0.6
     */
    protected static function handleJsonError($lastError)
    {
        // 若没有错误，则无视
        if ($lastError === JSON_ERROR_NONE) {
            return;
        }

        $availableErrors = [];
        foreach (static::$jsonErrorMessages as $const => $message) {
            /**
             * defined() 检查某个名称的常量是否存在
             */
            if (defined($const)) {
                /**
                 * constant() 通过 name 返回常量的值。也就是常量名储存在一个变量里，或者由函数返回常量名
                 * @link http://php.net/manual/zh/function.constant.php
                 */
                $availableErrors[constant($const)] = $message;
            }
        }

        /**
         * 如果错误类型在设定好的错误数组中，这直接抛出相应的异常
         */
        if (isset($availableErrors[$lastError])) {
            throw new InvalidArgumentException($availableErrors[$lastError], $lastError);
        }

        throw new InvalidArgumentException('Unknown JSON encoding/decoding error.');
    }

    /**
     * 在发送数据给 `json_encode()` 之前对数据进行预处理
     * Pre-processes the data before sending it to `json_encode()`.
     * 要处理的数据
     * @param mixed $data the data to be processed
     * JavaScript表达式集合
     * @param array $expressions collection of JavaScript expressions
     * 内部用于处理JS表达式的前缀
     * @param string $expPrefix a prefix internally used to handle JS expressions
     * @return mixed the processed data
     */
    protected static function processData($data, &$expressions, $expPrefix)
    {
        // 判断是否是对象
        if (is_object($data)) {
            // 若是 JsExpression 对象
            if ($data instanceof JsExpression) {
                $token = "!{[$expPrefix=" . count($expressions) . ']}!';
                $expressions['"' . $token . '"'] = $data->expression;

                return $token;
            } elseif ($data instanceof \JsonSerializable) {
                // $data->jsonSerialize() 指定应该序列化为JSON的数据
                return static::processData($data->jsonSerialize(), $expressions, $expPrefix);
            } elseif ($data instanceof Arrayable) {
                // 将对象转换为数组
                $data = $data->toArray();
            } elseif ($data instanceof \SimpleXMLElement) {
                // 将 XML 对象转换为数组
                $data = (array) $data;
            } else {
                // 遍历将对象转换为数组
                $result = [];
                foreach ($data as $name => $value) {
                    $result[$name] = $value;
                }
                $data = $result;
            }

            if ($data === []) {
                return new \stdClass();
            }
        }

        // 判断是否是数组
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                // 递归地执行预处理方法
                if (is_array($value) || is_object($value)) {
                    $data[$key] = static::processData($value, $expressions, $expPrefix);
                }
            }
        }

        // 若以上都不是，就是普通字符串，直接返回就可以
        return $data;
    }

    /**
     * Generates a summary of the validation errors.
     * @param Model|Model[] $models the model(s) whose validation errors are to be displayed.
     * @param array $options the tag options in terms of name-value pairs. The following options are specially handled:
     *
     * - showAllErrors: boolean, if set to true every error message for each attribute will be shown otherwise
     *   only the first error message for each attribute will be shown. Defaults to `false`.
     *
     * @return string the generated error summary
     * @since 2.0.14
     */
    public static function errorSummary($models, $options = [])
    {
        $showAllErrors = ArrayHelper::remove($options, 'showAllErrors', false);
        $lines = self::collectErrors($models, $showAllErrors);

        return json_encode($lines);
    }

    /**
     * Return array of the validation errors
     * @param Model|Model[] $models the model(s) whose validation errors are to be displayed.
     * @param $showAllErrors boolean, if set to true every error message for each attribute will be shown otherwise
     * only the first error message for each attribute will be shown.
     * @return array of the validation errors
     * @since 2.0.14
     */
    private static function collectErrors($models, $showAllErrors)
    {
        $lines = [];
        if (!is_array($models)) {
            $models = [$models];
        }

        foreach ($models as $model) {
            $lines = array_unique(array_merge($lines, $model->getErrorSummary($showAllErrors)));
        }

        return $lines;
    }
}
