<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\validators;

use Yii;
use yii\base\Component;
use yii\base\NotSupportedException;

/**
 * Validator is the base class for all validators.
 * Validator是所有验证类的基类
 *
 * Child classes should override the [[validateValue()]] and/or [[validateAttribute()]] methods to provide the actual
 * logic of performing data validation. Child classes may also override [[clientValidateAttribute()]]
 * to provide client-side validation support.
 * 子类应该重写validateValue和(或)validateAttribute方法，以提供实际需要的数据验证逻辑。子类同样可以重写clientValidateAttribute(客户端验证属性)方法
 * 以提供客户端验证的支持。
 *
 * Validator declares a set of [[builtInValidators|built-in validators]] which can
 * be referenced using short names. They are listed as follows:
 * 验证器声明了一系列可以使用简称的内建验证器，请参考如下列表：
 *
 * - `boolean`: [[BooleanValidator]]
 * - `captcha`: [[\yii\captcha\CaptchaValidator]]  //验证码
 * - `compare`: [[CompareValidator]]
 * - `date`: [[DateValidator]]
 * - `default`: [[DefaultValueValidator]]
 * - `double`: [[NumberValidator]]
 * - `each`: [[EachValidator]]
 * - `email`: [[EmailValidator]]
 * - `exist`: [[ExistValidator]]
 * - `file`: [[FileValidator]]
 * - `filter`: [[FilterValidator]]
 * - `image`: [[ImageValidator]]
 * - `in`: [[RangeValidator]]
 * - `integer`: [[NumberValidator]]
 * - `match`: [[RegularExpressionValidator]]
 * - `required`: [[RequiredValidator]]
 * - `safe`: [[SafeValidator]]
 * - `string`: [[StringValidator]]
 * - `trim`: [[FilterValidator]]
 * - `unique`: [[UniqueValidator]]
 * - `url`: [[UrlValidator]]
 * - `ip`: [[IpValidator]]
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Validator extends Component
{
    /**
     * @var array list of built-in validators (name => class or configuration)
     * 变量 数组 内置验证器的列表（名称=>类或者配置）
     */
    public static $builtInValidators = [
        // （布尔型）
        'boolean' => 'yii\validators\BooleanValidator',
        // （验证码）
        'captcha' => 'yii\captcha\CaptchaValidator',
        // （比对）
        'compare' => 'yii\validators\CompareValidator',
        // （日期）
        'date' => 'yii\validators\DateValidator',
        // （默认值）
        'default' => 'yii\validators\DefaultValueValidator',
        // （双精度浮点型）
        'double' => 'yii\validators\NumberValidator',
        // （循环验证）
        'each' => 'yii\validators\EachValidator',
        // （电子邮件）
        'email' => 'yii\validators\EmailValidator',
        // （存在性）
        'exist' => 'yii\validators\ExistValidator',
        // （文件）
        'file' => 'yii\validators\FileValidator',
        // （过滤器）
        'filter' => 'yii\validators\FilterValidator',
        // （图片）
        'image' => 'yii\validators\ImageValidator',
        // （范围）
        'in' => 'yii\validators\RangeValidator',
        // （整数）
        'integer' => [
            'class' => 'yii\validators\NumberValidator',
            'integerOnly' => true,
        ],
        // （正则表达式）
        'match' => 'yii\validators\RegularExpressionValidator',
        // （数字）
        'number' => 'yii\validators\NumberValidator',
        // （必填）
        'required' => 'yii\validators\RequiredValidator',
        // （安全）
        'safe' => 'yii\validators\SafeValidator',
        // （字符串）
        'string' => 'yii\validators\StringValidator',
        // （修剪/裁边）
        'trim' => [
            'class' => 'yii\validators\FilterValidator',
            'filter' => 'trim',
            'skipOnArray' => true,
        ],
        // （唯一性）
        'unique' => 'yii\validators\UniqueValidator',
        // （网址）
        'url' => 'yii\validators\UrlValidator',
        // （IP地址）
        'ip' => 'yii\validators\IpValidator',
    ];
    /**
     * @var array|string attributes to be validated by this validator. For multiple attributes,
     * please specify them as an array; for single attribute, you may use either a string or an array.
     * 变量 数组 字符串 被验证器验证的属性。对于多属性，应以数组的方式指定。对于单个属性，字符串或数组都行。
     */
    public $attributes = [];
    /**
     * @var string the user-defined error message. It may contain the following placeholders which
     * will be replaced accordingly by the validator:
     * 属性 字符串 用户定义的报错信息。可以使用如下的占位符，占位符将会根据验证器被替换：
     *
     * - `{attribute}`: the label of the attribute being validated
     * - `{属性}`： 被验证的标签的属性
     * - `{value}`: the value of the attribute being validated
     * - `{值}`：被验证的值
     *
     * Note that some validators may introduce other properties for error messages used when specific
     * validation conditions are not met. Please refer to individual class API documentation for details
     * about these properties. By convention, this property represents the primary error message
     * used when the most important validation condition is not met.
     * 请注意：当指定的一些条件没有满足时，有些验证器可能使用其他属性的错误信息。请参考类API手册查看更多关于这些属性细节，
     * 根据惯例，该属性代表了当主要的验证条件没有通过时，而采用的主要错误信息
     */
    public $message;
    /**
     * @var array|string scenarios that the validator can be applied to. For multiple scenarios,
     * please specify them as an array; for single scenario, you may use either a string or an array.
     * 变量 数组 字符串 验证器可以运用的场景。对于多场景，请使用数组指定，对于单一场景，使用字符串或数组都可以。
     */
    public $on = [];
    /**
     * @var array|string scenarios that the validator should not be applied to. For multiple scenarios,
     * please specify them as an array; for single scenario, you may use either a string or an array.
     * 变量 数组 字符串 验证器不运用的场景。对于多场景，请使用数组指定，对于单一场景，使用字符串或者数组都行
     */
    public $except = [];
    /**
     * @var boolean whether this validation rule should be skipped if the attribute being validated already
     * has some validation error according to some previous rules. Defaults to true.
     * 变量 boolean 如果已经验证的属性已经有了一些验证错误，那么是否应该跳过这个验证规则。默认是true
     */
    public $skipOnError = true;
    /**
     * @var boolean whether this validation rule should be skipped if the attribute value
     * is null or an empty string.
     * 变量 boolean 当属性值为null或者空字符串的时候，是否跳过验证
     */
    public $skipOnEmpty = true;
    /**
     * @var boolean whether to enable client-side validation for this validator.
     * The actual client-side validation is done via the JavaScript code returned
     * by [[clientValidateAttribute()]]. If that method returns null, even if this property
     * is true, no client-side validation will be done by this validator.
     * 变量 boolean 是否为该验证器启用客户端验证功能。客户端验证已经通过clientValidateAttribute方法返回的JavaScript代码完成
     */
    public $enableClientValidation = true;
    /**
     * @var callable a PHP callable that replaces the default implementation of [[isEmpty()]].
     * If not set, [[isEmpty()]] will be used to check if a value is empty. The signature
     * of the callable should be `function ($value)` which returns a boolean indicating
     * whether the value is empty.
     * 变量 回调 一个代替默认isEmpty方法的PHP回调函数，如果不设置，isEmpty方法将会用于检测某个值是否为空。回调函数的标识应该是
     * function($value)并且返回一个表示该值是否为空的boolean值。
     */
    public $isEmpty;
    /**
     * @var callable a PHP callable whose return value determines whether this validator should be applied.
     * The signature of the callable should be `function ($model, $attribute)`, where `$model` and `$attribute`
     * refer to the model and the attribute currently being validated. The callable should return a boolean value.
     * 变量 回调 一个PHP回调函数。它的返回值决定了是否应用该验证器。该回调函数的标识应该是`function($model,$attribute)`,$model和$attribute
     * 代表当前被验证的模型和属性。回调函数应该返回一个boolean值
     *
     * This property is mainly provided to support conditional validation on the server-side.
     * If this property is not set, this validator will be always applied on the server-side.
     * 提供该属性主要是为了支持服务端的条件验证
     * 如果该属性没有设置，该验证器会一直应用于服务端。
     *
     * The following example will enable the validator only when the country currently selected is USA:
     * 如下的示例标识只有当前国家选择为USA时开启：
     *
     * ```php
     * function ($model) {
     *     return $model->country == Country::USA;
     * }
     * ```
     *
     * @see whenClient
     */
    public $when;
    /**
     * @var string a JavaScript function name whose return value determines whether this validator should be applied
     * on the client-side. The signature of the function should be `function (attribute, value)`, where
     * `attribute` is an object describing the attribute being validated (see [[clientValidateAttribute()]])
     * and `value` the current value of the attribute.
     * 变量 字符串 一个Javascript函数名，它的返回值决定了该验证器是否在客户端启用。该函数的标识应该是`function(attribute,value)`，attribute
     * 是一个描述被验证的属性的对象(请参考clientValidateAttribute()方法)，value表示当前属性的值。
     *
     * This property is mainly provided to support conditional validation on the client-side.
     * If this property is not set, this validator will be always applied on the client-side.
     * 提供该属性主要是为了支持客户端的条件验证。
     * 如果该属性没有设置，那么该验证器就会始终在客户端开启。
     *
     * The following example will enable the validator only when the country currently selected is USA:
     * 下边示例展示了只有当被选择的国家是美国时，才会启用验证器：
     *
     * ```javascript  //注意，这里是JavaScript代码
     * function (attribute, value) {
     *     return $('#country').val() === 'USA';
     * }
     * ```
     *
     * @see when
     */
    public $whenClient;


    /**
     * Creates a validator object.
     * 创建一个验证器对象
     * @param mixed $type the validator type. This can be a built-in validator name,
     * a method name of the model class, an anonymous function, or a validator class name.
     * 参数 验证器类型。可以是一个内置的验证器名称，某个模型类的方法名，一个匿名函数，或者一个验证器类名。
     * @param \yii\base\Model $model the data model to be validated.
     * 参数 被验证的数据模型
     * @param array|string $attributes list of attributes to be validated. This can be either an array of
     * the attribute names or a string of comma-separated attribute names.
     * 参数 数组 字符串 被验证的属性列表。该项可以是一个属性名组成的数组或者一个逗号分隔的属性名组成的字符串
     * @param array $params initial values to be applied to the validator properties
     * 参数 数组 用于验证器属性的初始值
     * @return Validator the validator
     * 返回值 验证器
     */
    public static function createValidator($type, $model, $attributes, $params = [])
    {
        $params['attributes'] = $attributes;

        // $type是 匿名函数，或者是当前类的方法
        if ($type instanceof \Closure || $model->hasMethod($type)) {
            // method-based validator
            // 基于方法的验证器，行内验证器
            $params['class'] = __NAMESPACE__ . '\InlineValidator';
            $params['method'] = $type;
        } else {
            // 若核心验证器中存在，则直接使用核心验证器
            if (isset(static::$builtInValidators[$type])) {
                $type = static::$builtInValidators[$type];
            }
            // todo?还可以是数组?什么情况下？
            if (is_array($type)) {
                $params = array_merge($type, $params);
            } else {
                // 是独立验证器，eg: ['country', CountryValidator::className()],
                $params['class'] = $type;
            }
        }
        // 根据配置数组创建验证器对象
        return Yii::createObject($params);
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        // 属性
        $this->attributes = (array) $this->attributes;
        // 应用场景
        $this->on = (array) $this->on;
        // 不应用场景
        $this->except = (array) $this->except;
    }

    /**
     * Validates the specified object.
     * 验证指定的对象
     * @param \yii\base\Model $model the data model being validated
     * 参数 被验证的数据模型
     * @param array|null $attributes the list of attributes to be validated.
     * Note that if an attribute is not associated with the validator, or is is prefixed with `!` char - it will be
     * ignored. If this parameter is null, every attribute listed in [[attributes]] will be validated.
     * 参数 数组或null 被验证的属性列表。
     * 请注意如果一个属性没有跟验证器关联，或者它的前缀是!字符，它将会被忽略。如果该参数是null，列表里边的每一个属性都会被验证。
     */
    public function validateAttributes($model, $attributes = null)
    {
        // 若属性是数组
        if (is_array($attributes)) {
            $newAttributes = [];
            // 遍历属性
            foreach ($attributes as $attribute) {
                // 该属性在当前验证器负责的属性中 或者 '!' . $attribute 在当前验证器负责的属性中
                if (in_array($attribute, $this->attributes) || in_array('!' . $attribute, $this->attributes)) {
                    $newAttributes[] = $attribute;
                }
            }
            $attributes = $newAttributes;
        } else {
            $attributes = [];
            // 遍历 当前验证器负责的属性。
            foreach ($this->attributes as $attribute) {
                // 若属性以 叹号开头，则去掉 叹号
                $attributes[] = $attribute[0] === '!' ? substr($attribute, 1) : $attribute;
            }
        }

        // 遍历属性
        foreach ($attributes as $attribute) {
            // 如果已经验证的属性已经有了一些验证错误，那么是否应该跳过这个验证规则
            $skip = $this->skipOnError && $model->hasErrors($attribute)
                    // 当属性值为null或者空字符串的时候，是否跳过验证
                || $this->skipOnEmpty && $this->isEmpty($model->$attribute);
            // 若没有跳过验证
            if (!$skip) {
                // when 为 null,或者 when 方法返回 true
                if ($this->when === null || call_user_func($this->when, $model, $attribute)) {
                    // 验证器执行属性验证
                    $this->validateAttribute($model, $attribute);
                }
            }
        }
    }

    /**
     * Validates a single attribute.
     * 验证单个属性
     * Child classes must implement this method to provide the actual validation logic.
     * 子类必须首先该方法，以提供实际的验证逻辑。
     * @param \yii\base\Model $model the data model to be validated
     * 参数 被验证的数据模型
     * @param string $attribute the name of the attribute to be validated.
     * 参数 字符串 被验证的属性的名称。
     */
    public function validateAttribute($model, $attribute)
    {
        // 验证值是否符合规则，若验证失败，则返回错误信息和错误信息中的参数
        $result = $this->validateValue($model->$attribute);
        // 若 $result 不为空，则说明验证失败
        if (!empty($result)) {
            // 向model中添加错误信息
            $this->addError($model, $attribute, $result[0], $result[1]);
        }
    }

    /**
     * Validates a given value.
     * 验证一个给定的值
     * You may use this method to validate a value out of the context of a data model.
     * 你可以使用该方法去验证不在数据模型环境中的值
     * @param mixed $value the data value to be validated.
     * 参数 混合型 被验证的数据模型
     * @param string $error the error message to be returned, if the validation fails.
     * 参数 字符串 如果验证失败，返回的错误信息
     * @return boolean whether the data is valid.
     * 返回值 boolean 该数据是否合法
     */
    public function validate($value, &$error = null)
    {
        // 验证值是否合法
        $result = $this->validateValue($value);
        // 若返回数据为空，则说明没有错误，直接返回true
        if (empty($result)) {
            return true;
        }

        // 获取错误信息和参数
        list($message, $params) = $result;
        $params['attribute'] = Yii::t('yii', 'the input value');
        if (is_array($value)) {
            $params['value'] = 'array()';
        } elseif (is_object($value)) {
            $params['value'] = 'object';
        } else {
            $params['value'] = $value;
        }
        $error = Yii::$app->getI18n()->format($message, $params, Yii::$app->language);

        return false;
    }

    /**
     * Validates a value.
     * 验证一个值
     * A validator class can implement this method to support data validation out of the context of a data model.
     * 一个验证类可以实现该方法以支持在一个数据模型之外的数据验证
     * @param mixed $value the data value to be validated.
     * 参数 混合型 被验证的数据
     * @return array|null the error message and the parameters to be inserted into the error message.
     * Null should be returned if the data is valid.
     * 返回值 数组 null 错误信息和错误信息中的参数
     * @throws NotSupportedException if the validator does not supporting data validation without a model
     * 如果验证器不支持模型外验证，抛出异常
     */
    protected function validateValue($value)
    {
        throw new NotSupportedException(get_class($this) . ' does not support validateValue().');
    }

    /**
     * Returns the JavaScript needed for performing client-side validation.
     * 返回客户端验证需要的JavaScript
     *
     * You may override this method to return the JavaScript validation code if
     * the validator can support client-side validation.
     * 如果验证器支持客户端验证，你可以重写此方法返回JavaScript的验证代码。
     *
     * The following JavaScript variables are predefined and can be used in the validation code:
     * 下面的JavaScript变量已经预先定义好了，可以直接用于验证代码：
     *
     * - `attribute`: an object describing the the attribute being validated.
     * - `属性`：描述将要被验证对象的属性
     * - `value`: the value being validated.
     * - `值`：被验证的值
     * - `messages`: an array used to hold the validation error messages for the attribute.
     * - `信息`： 用来存放属性错误信息的数组
     * - `deferred`: an array used to hold deferred objects for asynchronous validation
     * - `延迟`：用来存储异步验证对象的数组
     * - `$form`: a jQuery object containing the form element
     * - `$form`: 包含表单元的jQuery对象
     *
     * The `attribute` object contains the following properties:
     * 属性对象包含如下的属性：
     * - `id`: a unique ID identifying the attribute (e.g. "loginform-username") in the form
     * - `id`： 在表单中，用于区分不同属性的唯一id（例如loginform-username）
     * - `name`: attribute name or expression (e.g. "[0]content" for tabular input)
     * - `name`： 属性名或表达式（例如[0]content表示扁平输入）
     * - `container`: the jQuery selector of the container of the input field
     * - `container`：输入域的jQuery选择器容器
     * - `input`: the jQuery selector of the input field under the context of the form
     * - `input`：表单下输入域的jQuery选择器
     * - `error`: the jQuery selector of the error tag under the context of the container
     * - `错误`： 容器下的错误标签的jQuery选择器
     * - `status`: status of the input field, 0: empty, not entered before, 1: validated, 2: pending validation, 3: validating
     * - `状态`： 输入域的状态，0，空，之前没有输入，1，已验证，2，待验证，3正在验证
     *
     * @param \yii\base\Model $model the data model being validated
     * 参数 被验证的数据模型
     * @param string $attribute the name of the attribute to be validated.
     * 参数 字符串 被验证的属性名
     * @param \yii\web\View $view the view object that is going to be used to render views or view files
     * containing a model form with this validator applied.
     * 参数 用于渲染包含应用验证的模型表单都视图对象
     * @return string the client-side validation script. Null if the validator does not support
     * client-side validation.
     * 返回值 字符串 客户端验证的代码。如果验证器不支持客户端验证，就返回null
     * @see \yii\widgets\ActiveForm::enableClientValidation
     * 参考 \yii\widgets\ActiveForm::enableClientValidation
     */
    public function clientValidateAttribute($model, $attribute, $view)
    {
        return null;
    }

    /**
     * Returns a value indicating whether the validator is active for the given scenario and attribute.
     * 返回表示验证器是否可用于给定的场景和属性。
     *
     * A validator is active if
     * 如下情形激活验证器：
     *
     * - the validator's `on` property is empty, or
     * - 验证器的on属性为空，或者
     * - the validator's `on` property contains the specified scenario
     * - 验证器的on属性包含特定的场景
     *
     * @param string $scenario scenario name
     * 参数 字符串 场景名
     * @return boolean whether the validator applies to the specified scenario.
     * 返回值 boolean 验证器是否应用于当前场景。
     */
    public function isActive($scenario)
    {
        // 当前场景 $scenario  不在 except 属性中 && （没有 on 属性， || $scenario 在 on 属性中）
        return !in_array($scenario, $this->except, true) && (empty($this->on) || in_array($scenario, $this->on, true));
    }

    /**
     * Adds an error about the specified attribute to the model object.
     * 给模型对象的指定属性添加一个错误。
     * This is a helper method that performs message selection and internationalization.
     * 该方法是显示信息选择和国际化的帮助方法
     * @param \yii\base\Model $model the data model being validated
     * 参数 被验证的数据模型
     * @param string $attribute the attribute being validated
     * 参数 字符串 被验证的属性
     * @param string $message the error message
     * 参数 字符串 错误信息
     * @param array $params values for the placeholders in the error message
     * 参数 数组 错误信息的占位符
     */
    public function addError($model, $attribute, $message, $params = [])
    {
        // 获取属性的标签
        $params['attribute'] = $model->getAttributeLabel($attribute);
        // 不存在 value， 则使用属性值作为value
        if (!isset($params['value'])) {
            // 获取model对应属性的值
            $value = $model->$attribute;
            // 若值是数组，则直接用 'array()'
            if (is_array($value)) {
                $params['value'] = 'array()';
            // 若值是对象，且没有 __toString() 方法， 则直接用 (object)
            } elseif (is_object($value) && !method_exists($value, '__toString')) {
                $params['value'] = '(object)';
            } else {
                $params['value'] = $value;
            }
        }
        // 格式化错误消息
        $model->addError($attribute, Yii::$app->getI18n()->format($message, $params, Yii::$app->language));
    }

    /**
     * Checks if the given value is empty.
     * 检测给定的值是否为空
     * A value is considered empty if it is null, an empty array, or an empty string.
     * 如果一个值为null，空数组，空字符串，就会被认为空
     * Note that this method is different from PHP empty(). It will return false when the value is 0.
     * 请注意，该方法跟php的empty数组不同，当值为0的时候会返回false
     * @param mixed $value the value to be checked
     * 参数 混合型 被验证的值
     * @return boolean whether the value is empty
     * 返回值 boolean 该值是否为空
     */
    public function isEmpty($value)
    {
        // 若自定义了 空判定方法，则调用该方法判断
        if ($this->isEmpty !== null) {
            return call_user_func($this->isEmpty, $value);
        } else {
            // 否则，null 空数组，空字符串都被视为 空
            return $value === null || $value === [] || $value === '';
        }
    }
}
