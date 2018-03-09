<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\widgets;

use Yii;
use yii\base\InvalidCallException;
use yii\base\Model;
use yii\base\Widget;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

/**
 * ActiveForm is a widget that builds an interactive HTML form for one or multiple data models.
 *
 * For more details and usage information on ActiveForm, see the [guide article on forms](guide:input-forms).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class ActiveForm extends Widget
{
    /**
     * Add validation state class to container tag
     * @since 2.0.14
     */
    const VALIDATION_STATE_ON_CONTAINER = 'container';
    /**
     * Add validation state class to input tag
     * @since 2.0.14
     */
    const VALIDATION_STATE_ON_INPUT = 'input';

    /**
     * 表单的 action URL, 改参数将会通过 [[\yii\helpers\Url::to()]] 处理
     *
     * @var array|string the form action URL. This parameter will be processed by [[\yii\helpers\Url::to()]].
     * @see method for specifying the HTTP method for this form.
     */
    public $action = '';
    /**
     * 表单提交方法['post' 或者 'get'], 默认是 post
     *
     * @var string the form submission method. This should be either `post` or `get`. Defaults to `post`.
     *
     * 如果将该参数设置为 get， 你可能会看到每次请求时，url 后会重复添加相同的参数
     * 这是因为 action 的默认值被设置为当前的请求url, 并且每个提交将添加新的参数，而不是替换现有的参数。
     * 您可以显式地设置 action 以避免这种情况发生
     *
     * When you set this to `get` you may see the url parameters repeated on each request.
     * This is because the default value of [[action]] is set to be the current request url and each submit
     * will add new parameters instead of replacing existing ones.
     * You may set [[action]] explicitly to avoid this:
     *
     * ```php
     * $form = ActiveForm::begin([
     *     'method' => 'get',
     *     'action' => ['controller/action'],
     * ]);
     * ```
     */
    public $method = 'post';
    /**
     * 表单标签的HTML属性(名称-值对)
     *
     * @var array the HTML attributes (name-value pairs) for the form tag.
     * @see \yii\helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $options = [];
    /**
     * 当调用[[field()]]来创建一个新字段时，默认使用的字段类名
     *
     * @var string the default field class name when calling [[field()]] to create a new field.
     * @see fieldConfig
     */
    public $fieldClass = 'yii\widgets\ActiveField';
    /**
     * 在创建新的字段对象时，[[field()]]使用的默认配置
     *
     * @var array|\Closure the default configuration used by [[field()]] when creating a new field object.
     *
     * 这可以是配置数组，也可以是返回配置数组的匿名函数。
     *
     * This can be either a configuration array or an anonymous function returning a configuration array.
     * If the latter, the signature should be as follows:
     *
     * ```php
     * function ($model, $attribute)
     * ```
     *
     * 该属性的值将被与传入[[field()]]的$options参数进行递归地合并
     * The value of this property will be merged recursively with the `$options` parameter passed to [[field()]].
     *
     * @see fieldClass
     */
    public $fieldConfig = [];
    /**
     * 是否执行编码错误信息
     * @var bool whether to perform encoding on the error summary.
     */
    public $encodeErrorSummary = true;
    /**
     * 错误摘要容器的默认CSS类
     *
     * @var string the default CSS class for the error summary container.
     * @see errorSummary()
     */
    public $errorSummaryCssClass = 'error-summary';
    /**
     * 当字段为必填项时添加到字段容器的Css Class
     * @var string the CSS class that is added to a field container when the associated attribute is required.
     */
    public $requiredCssClass = 'required';
    /**
     * 当字段出现验证错误时添加到字段容器的Css Class
     * @var string the CSS class that is added to a field container when the associated attribute has validation error.
     */
    public $errorCssClass = 'has-error';
    /**
     * 当字段验证成功时添加到字段容器的Css Class
     * @var string the CSS class that is added to a field container when the associated attribute is successfully validated.
     */
    public $successCssClass = 'has-success';
    /**
     * 当字段正在验证时添加到字段容器的Css Class
     * @var string the CSS class that is added to a field container when the associated attribute is being validated.
     */
    public $validatingCssClass = 'validating';
    /**
     * @var string where to render validation state class
     * Could be either "container" or "input".
     * Default is "container".
     * @since 2.0.14
     */
    public $validationStateOn = self::VALIDATION_STATE_ON_CONTAINER;
    /**
     * 是否启用客户端数据验证
     * @var bool whether to enable client-side data validation.
     * If [[ActiveField::enableClientValidation]] is set, its value will take precedence for that input field.
     *
     * 如果设置了 [[ActiveField::enableClientValidation]], 这里的设置将会被覆盖
     */
    public $enableClientValidation = true;
    /**
     * 是否启用基于ajax的数据验证
     * @var bool whether to enable AJAX-based data validation.
     * If [[ActiveField::enableAjaxValidation]] is set, its value will take precedence for that input field.
     *
     * 如果设置了 [[ActiveField::enableAjaxValidation]], 这里的设置将会被覆盖
     *
     */
    public $enableAjaxValidation = false;
    /**
     * 是否启用 `yii.activeForm` JavaScript 插件
     * @var bool whether to hook up `yii.activeForm` JavaScript plugin.
     *
     * 如果您想要支持客户端验证和/或AJAX验证，或者想要使用`yii.activeForm` 插件，则必须将此属性设置为true。
     * 当这是false时，表单将不会生成任何JavaScript代码。
     * 
     * This property must be set `true` if you want to support client validation and/or AJAX validation, or if you
     * want to take advantage of the `yii.activeForm` plugin. When this is `false`, the form will not generate
     * any JavaScript.
     * @see registerClientScript
     */
    public $enableClientScript = true;
    /**
     * 执行基于ajax的验证的URL，该属性将被[[Url::to()]]处理。有关如何配置该属性的详细信息，请参考Url::to()
     * @var array|string the URL for performing AJAX-based validation. This property will be processed by
     * [[Url::to()]]. Please refer to [[Url::to()]] for more details on how to configure this property.
     * If this property is not set, it will take the value of the form's action attribute.
     *
     * 如果未设置此属性，它将使用表单 action 属性的值。
     */
    public $validationUrl;
    /**
     * 是否在提交表单时执行验证
     * @var bool whether to perform validation when the form is submitted.
     */
    public $validateOnSubmit = true;
    /**
     * 当输入字段的值被更改时是否执行验证
     * @var bool whether to perform validation when the value of an input field is changed.
     * If [[ActiveField::validateOnChange]] is set, its value will take precedence for that input field.
     */
    public $validateOnChange = true;
    /**
     * 当输入字段失去焦点时是否执行验证
     * @var bool whether to perform validation when an input field loses focus.
     * If [[ActiveField::$validateOnBlur]] is set, its value will take precedence for that input field.
     */
    public $validateOnBlur = true;
    /**
     * 是否在用户在表单中输入内容时执行验证
     * @var bool whether to perform validation while the user is typing in an input field.
     * If [[ActiveField::validateOnType]] is set, its value will take precedence for that input field.
     * @see validationDelay
     */
    public $validateOnType = false;
    /**
     * 当用户在字段中输入时，验证应该被延迟的毫秒数
     *
     * @var int number of milliseconds that the validation should be delayed when the user types in the field
     * and [[validateOnType]] is set `true`.
     * If [[ActiveField::validationDelay]] is set, its value will take precedence for that input field.
     */
    public $validationDelay = 500;
    /**
     * 代表验证请求是 AJAX 请求的参数的名称
     * @var string the name of the GET parameter indicating the validation request is an AJAX request.
     */
    public $ajaxParam = 'ajax';
    /**
     * 您希望从服务器返回的数据类型
     * @var string the type of data that you're expecting back from the server.
     */
    public $ajaxDataType = 'json';
    /**
     * 是否在验证后滚动到第一个错误
     * @var bool whether to scroll to the first error after validation.
     * @since 2.0.6
     */
    public $scrollToError = true;
    /**
     * @var int offset in pixels that should be added when scrolling to the first error.
     * @since 2.0.11
     */
    public $scrollToErrorOffset = 0;
    /**
     * 用于单个属性的客户端验证选项，数组的每个元素都代表特定属性的验证选项
     * @var array the client validation options for individual attributes. Each element of the array
     * represents the validation options for a particular attribute.
     * @internal
     */
    public $attributes = [];

    /**
     * 当前激活的ActiveField对象
     * @var ActiveField[] the ActiveField objects that are currently active
     */
    private $_fields = [];


    /**
     * 初始化小部件，表单开始
     * Initializes the widget.
     * This renders the form open tag.
     *
     * @link http://php.net/manual/zh/function.ob-implicit-flush.php
     * ob_implicit_flush() 隐式刷送将导致在每次输出调用后有一次刷送操作，以便不再需要对 flush() 的显式调用。
     */
    public function init()
    {
        parent::init();
        if (!isset($this->options['id'])) {
            $this->options['id'] = $this->getId();
        }

        /**
         * 开启ob缓存:所有的echo输出都会保存到ob缓存中，可以使用ob系列函数进行操作。
         * 默认情况下，在程序执行结束，会把缓存中的数据发送给浏览器，
         * 如果，你使用ob_clean()类似的函数，会清空缓存中的内容，那么就不用有数据发送给浏览器
         * @link http://php.net/manual/zh/function.ob-start.php
         */
        ob_start();
        /**
         * @link http://php.net/manual/zh/function.ob-implicit-flush.php
         * 将打开或关闭绝对（隐式）刷送。
         * 绝对（隐式）刷送将导致在每次输出调用后有一次刷送操作，以便不再需要对 flush() 的显式调用。
         * 设为TRUE 打开绝对刷送，反之是 FALSE 。
         */
        ob_implicit_flush(false);
    }

    /**
     * 运行小部件
     * Runs the widget.
     * 
     * 这里注册了必要的JavaScript代码并将表单关闭
     * This registers the necessary JavaScript code and renders the form open and close tags.
     * @throws InvalidCallException if `beginField()` and `endField()` calls are not matching.
     */
    public function run()
    {
        /**
         * 如果还存在未关闭的 ActiveField 标签，则报错
         */
        if (!empty($this->_fields)) {
            throw new InvalidCallException('Each beginField() should have a matching endField() call.');
        }

        /**
         * ob_get_clean() 得到当前缓冲区的内容并删除当前输出缓
         * @link http://php.net/manual/zh/function.ob-get-clean.php
         */
        $content = ob_get_clean();
        // 表单开始标签
        echo Html::beginForm($this->action, $this->method, $this->options);
        // 表单内部内容
        echo $content;

        // 如果需要客户户端验证，则添加相应的JS代码
        if ($this->enableClientScript) {
            $this->registerClientScript();
        }

        echo Html::endForm();
    }

    /**
     * This registers the necessary JavaScript code.
     * @since 2.0.12
     */
    public function registerClientScript()
    {
        $id = $this->options['id'];
        $options = Json::htmlEncode($this->getClientOptions());
        $attributes = Json::htmlEncode($this->attributes);
        $view = $this->getView();
        ActiveFormAsset::register($view);
        $view->registerJs("jQuery('#$id').yiiActiveForm($attributes, $options);");
    }

    /**
     * 返回JS小部件的选项
     *
     * Returns the options for the form JS widget.
     * @return array the options.
     */
    protected function getClientOptions()
    {
        $options = [
            'encodeErrorSummary' => $this->encodeErrorSummary,
            'errorSummary' => '.' . implode('.', preg_split('/\s+/', $this->errorSummaryCssClass, -1, PREG_SPLIT_NO_EMPTY)),
            'validateOnSubmit' => $this->validateOnSubmit,
            'errorCssClass' => $this->errorCssClass,
            'successCssClass' => $this->successCssClass,
            'validatingCssClass' => $this->validatingCssClass,
            'ajaxParam' => $this->ajaxParam,
            'ajaxDataType' => $this->ajaxDataType,
            'scrollToError' => $this->scrollToError,
            'scrollToErrorOffset' => $this->scrollToErrorOffset,
            'validationStateOn' => $this->validationStateOn,
        ];
        if ($this->validationUrl !== null) {
            $options['validationUrl'] = Url::to($this->validationUrl);
        }

        // only get the options that are different from the default ones (set in yii.activeForm.js)
        return array_diff_assoc($options, [
            'encodeErrorSummary' => true,
            'errorSummary' => '.error-summary',
            'validateOnSubmit' => true,
            'errorCssClass' => 'has-error',
            'successCssClass' => 'has-success',
            'validatingCssClass' => 'validating',
            'ajaxParam' => 'ajax',
            'ajaxDataType' => 'json',
            'scrollToError' => true,
            'scrollToErrorOffset' => 0,
            'validationStateOn' => self::VALIDATION_STATE_ON_CONTAINER,
        ]);
    }

    /**
     * 生成验证错误信息
     * 如果没有验证错误，仍然会生成一个空的错误信息标记，但它将被隐藏。
     * Generates a summary of the validation errors.
     * If there is no validation error, an empty error summary markup will still be generated, but it will be hidden.
     * 
     * 关联该表单的model类
     * @param Model|Model[] $models the model(s) associated with this form.
     * @param array $options the tag options in terms of name-value pairs. The following options are specially handled:
     *
     * - `header`: string, the header HTML for the error summary. If not set, a default prompt string will be used.
     * - `footer`: string, the footer HTML for the error summary.
     *
     * 其余选项将作为容器标签的属性呈现，值将会通过 [[\yii\helpers\Html::encode()]] 进行 html 编码。
     * 如果值为 null, 相应的属性将不会被呈现
     * The rest of the options will be rendered as the attributes of the container tag. The values will
     * be HTML-encoded using [[\yii\helpers\Html::encode()]]. If a value is `null`, the corresponding attribute will not be rendered.
     * @return string the generated error summary.
     * @see errorSummaryCssClass
     */
    public function errorSummary($models, $options = [])
    {
        // 添加css Class
        Html::addCssClass($options, $this->errorSummaryCssClass);
        $options['encode'] = $this->encodeErrorSummary;
        // 设置错误信息
        return Html::errorSummary($models, $options);
    }

    /**
     * 生成一个表单字段
     * Generates a form field.
     *
     * 表单字段与模型和属性相关联
     * A form field is associated with a model and an attribute. It contains a label, an input and an error message
     * and use them to interact with end users to collect their inputs for the attribute.
     * 它包含标签、输入表单和错误消息，并使用它们与终端用户进行交互，以便为该属性收集他们的输入数据
     * 
     * 数据模型类
     * @param Model $model the data model.
     * 
     * 属性名或表达式，对于属性表达式的格式，见 [[Html::getAttributeName()]]
     * @param string $attribute the attribute name or expression. See [[Html::getAttributeName()]] for the format
     * about attribute expression.
     * 
     * 字段对象的附加配置，这些是ActiveField或子类的属性，取决于fieldClass的值
     * @param array $options the additional configurations for the field object. These are properties of [[ActiveField]]
     * or a subclass, depending on the value of [[fieldClass]].
     * 
     * ActiveField创建的ActiveField对象
     * @return ActiveField the created ActiveField object.
     * @see fieldConfig
     */
    public function field($model, $attribute, $options = [])
    {
        $config = $this->fieldConfig;
        if ($config instanceof \Closure) {
            $config = call_user_func($config, $model, $attribute);
        }
        if (!isset($config['class'])) {
            $config['class'] = $this->fieldClass;
        }

        return Yii::createObject(ArrayHelper::merge($config, $options, [
            'model' => $model,
            'attribute' => $attribute,
            'form' => $this,
        ]));
    }

    /**
     * 开始一个表单字段。
     * 这个方法将创建一个新的表单字段并返回它的开始标签。
     * 您应该在后面调用endField()
     * Begins a form field.
     * This method will create a new form field and returns its opening tag.
     * You should call [[endField()]] afterwards.
     * @param Model $model the data model.
     * @param string $attribute the attribute name or expression. See [[Html::getAttributeName()]] for the format
     * about attribute expression.
     * @param array $options the additional configurations for the field object.
     * @return string the opening tag.
     * @see endField()
     * @see field()
     */
    public function beginField($model, $attribute, $options = [])
    {
        $field = $this->field($model, $attribute, $options);
        $this->_fields[] = $field;
        return $field->begin();
    }

    /**
     * 结束一个表单字段。
     * 这个方法将返回一个由[[beginField()]]开始的活动表单字段的结束标记。
     * Ends a form field.
     * This method will return the closing tag of an active form field started by [[beginField()]].
     * @return string the closing tag of the form field.
     * @throws InvalidCallException if this method is called without a prior [[beginField()]] call.
     */
    public function endField()
    {
        $field = array_pop($this->_fields);
        if ($field instanceof ActiveField) {
            return $field->end();
        }

        throw new InvalidCallException('Mismatching endField() call.');
    }

    /**
     * 验证一个或多个模型，并返回一个由属性id索引的错误消息数组。
     * 这是一种简化了编写AJAX验证代码的方法的辅助方法。
     * Validates one or several models and returns an error message array indexed by the attribute IDs.
     * This is a helper method that simplifies the way of writing AJAX validation code.
     *
     * 例如，你可以在一个控制器action中使用下面的代码来响应 AJAX 请求验证
     * For example, you may use the following code in a controller action to respond
     * to an AJAX validation request:
     *
     * ```php
     * $model = new Post;
     * $model->load(Yii::$app->request->post());
     * if (Yii::$app->request->isAjax) {
     *     Yii::$app->response->format = Response::FORMAT_JSON;
     *     return ActiveForm::validate($model);
     * }
     * // ... respond to non-AJAX request ...
     * ```
     *
     * To validate multiple models, simply pass each model as a parameter to this method, like
     * the following:
     * 要验证多个模型，只需将每个模型作为参数传递给该方法，如下:
     * ```php
     * ActiveForm::validate($model1, $model2, ...);
     * ```
     *
     * @param Model $model the model to be validated.
     *
     * 需要验证的属性列表。
     * 如果该参数是空的，则意味着在适用的验证规则中列出的任何属性都应该被验证。
     * @param mixed $attributes list of attributes that should be validated.
     * If this parameter is empty, it means any attribute listed in the applicable
     * validation rules should be validated.
     *
     * 当使用该方法验证多个模型时，该参数将被解释为一个模型。
     * When this method is used to validate multiple models, this parameter will be interpreted
     * as a model.
     *
     * @return array the error message array indexed by the attribute IDs.
     */
    public static function validate($model, $attributes = null)
    {
        $result = [];
        if ($attributes instanceof Model) {
            // validating multiple models
            // 验证多个模型
            $models = func_get_args();
            $attributes = null;
        } else {
            $models = [$model];
        }
        /* @var $model Model */
        foreach ($models as $model) {
            $model->validate($attributes);
            foreach ($model->getErrors() as $attribute => $errors) {
                $result[Html::getInputId($model, $attribute)] = $errors;
            }
        }

        return $result;
    }

    /**
     * 验证一个模型实例数组，并返回一个由属性id索引的错误消息数组。
     * 这是一个帮助方法，简化为表格输入编写AJAX验证代码的方法
     * Validates an array of model instances and returns an error message array indexed by the attribute IDs.
     * This is a helper method that simplifies the way of writing AJAX validation code for tabular input.
     *
     * For example, you may use the following code in a controller action to respond
     * to an AJAX validation request:
     * 例如，您可以在控制器操作中使用以下代码来响应AJAX验证请求.
     * 
     * ```php
     * // ... load $models ...
     * if (Yii::$app->request->isAjax) {
     *     Yii::$app->response->format = Response::FORMAT_JSON;
     *     return ActiveForm::validateMultiple($models);
     * }
     * // ... respond to non-AJAX request ...
     * ```
     *
     * @param array $models an array of models to be validated.
     * @param mixed $attributes list of attributes that should be validated.
     * If this parameter is empty, it means any attribute listed in the applicable
     * validation rules should be validated.
     * @return array the error message array indexed by the attribute IDs.
     */
    public static function validateMultiple($models, $attributes = null)
    {
        $result = [];
        /* @var $model Model */
        foreach ($models as $i => $model) {
            $model->validate($attributes);
            foreach ($model->getErrors() as $attribute => $errors) {
                $result[Html::getInputId($model, "[$i]" . $attribute)] = $errors;
            }
        }

        return $result;
    }
}
