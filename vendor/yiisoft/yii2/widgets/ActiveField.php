<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\widgets;

use Yii;
use yii\base\Component;
use yii\base\ErrorHandler;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\base\Model;
use yii\web\JsExpression;

/**
 * ActiveField represents a form input field within an [[ActiveForm]].
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class ActiveField extends Component
{
    /**
     * 与该字段相关联的 ActiveForm
     *
     * @var ActiveForm the form that this field is associated with.
     */
    public $form;
    /**
     * 与该字段相关联的数据模型
     *
     * @var Model the data model that this field is associated with.
     */
    public $model;
    /**
     * 与该字段相关联的数据模型的属性
     *
     * @var string the model attribute that this field is associated with.
     */
    public $attribute;
    /**
     * 字段容器元素的 HTML 属性
     * @var array the HTML attributes (name-value pairs) for the field container tag.
     *
     * 值将会被 HTML 编码
     * The values will be HTML-encoded using [[Html::encode()]].
     *
     * 如果值设为 null ,相应的属性将不会被呈现
     *
     * If a value is `null`, the corresponding attribute will not be rendered.
     * The following special options are recognized:
     *
     * - `tag`: the tag name of the container element. Defaults to `div`. Setting it to `false` will not render a container tag.
     *   See also [[\yii\helpers\Html::tag()]].
     *
     * 如果您为容器元素设置了自定义id，那么您可能需要相应地调整 选择器
     * If you set a custom `id` for the container element, you may need to adjust the [[$selectors]] accordingly.
     *
     * @see \yii\helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $options = ['class' => 'form-group'];
    /**
     * 用于排列标签、输入表单、错误消息和提示文本的模板。
     * @var string the template that is used to arrange the label, the input field, the error message and the hint text.
     * The following tokens will be replaced when [[render()]] is called: `{label}`, `{input}`, `{error}` and `{hint}`.
     * 当调用 [[render()]]方法时，会替换 `{label}`, `{input}`, `{error}` and `{hint}`
     *
     * 标签， 表单， 提示， 所务信息
     */
    public $template = "{label}\n{input}\n{hint}\n{error}";
    /**
     * 输入表单的默认选项
     * 当渲染标签时，这个参数将会与 输入表单方法：例如textInput()中的选项合并。
     *
     * @var array the default options for the input tags. The parameter passed to individual input methods
     * (e.g. [[textInput()]]) will be merged with this property when rendering the input tag.
     *
     * If you set a custom `id` for the input element, you may need to adjust the [[$selectors]] accordingly.
     *
     * @see \yii\helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $inputOptions = ['class' => 'form-control'];
    /**
     * 错误信息标签的默认选项
     * 当渲染标签时，这个参数将会与 error() 方法中的参数合并。
     *
     * @var array the default options for the error tags. The parameter passed to [[error()]] will be
     * merged with this property when rendering the error tag.
     * The following special options are recognized:
     *
     * - `tag`: the tag name of the container element. Defaults to `div`. Setting it to `false` will not render a container tag.
     *   See also [[\yii\helpers\Html::tag()]].
     * - `encode`: whether to encode the error output. Defaults to `true`.
     *
     * If you set a custom `id` for the error element, you may need to adjust the [[$selectors]] accordingly.
     *
     * @see \yii\helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $errorOptions = ['class' => 'help-block'];
    /**
     * 标签属性的默认选项
     * 将会与label()中的选项合并
     *
     * @var array the default options for the label tags. The parameter passed to [[label()]] will be
     * merged with this property when rendering the label tag.
     * @see \yii\helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $labelOptions = ['class' => 'control-label'];
    /**
     * 提示标签的默认选项
     * 将会与 hint()方法中的选项合并
     *
     * @var array the default options for the hint tags. The parameter passed to [[hint()]] will be
     * merged with this property when rendering the hint tag.
     * The following special options are recognized:
     *
     * - `tag`: the tag name of the container element. Defaults to `div`. Setting it to `false` will not render a container tag.
     *   See also [[\yii\helpers\Html::tag()]].
     *
     * @see \yii\helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $hintOptions = ['class' => 'hint-block'];
    /**
     * 是否启用客户端数据验证
     *
     * @var boolean whether to enable client-side data validation.
     * If not set, it will take the value of [[ActiveForm::enableClientValidation]].
     *
     * 如果不设置，将会继承 [[ActiveForm::enableClientValidation]] 的值
     */
    public $enableClientValidation;
    /**
     * 是否启用基于ajax的数据验证
     * @var boolean whether to enable AJAX-based data validation.
     * If not set, it will take the value of [[ActiveForm::enableAjaxValidation]].
     *
     * 如果不设置，将会继承 [[ActiveForm::enableAjaxValidation]] 的值
     */
    public $enableAjaxValidation;
    /**
     * 当输入字段的值被更改时是否执行验证
     * @var boolean whether to perform validation when the value of the input field is changed.
     * If not set, it will take the value of [[ActiveForm::validateOnChange]].
     *
     * 如果不设置，将会继承 [[ActiveForm::validateOnChange]] 的值
     */
    public $validateOnChange;
    /**
     * 当输入字段失去焦点时是否执行验证
     *
     * @var boolean whether to perform validation when the input field loses focus.
     * If not set, it will take the value of [[ActiveForm::validateOnBlur]].
     *
     * 如果不设置，将会继承 [[ActiveForm::validateOnBlur]] 的值
     */
    public $validateOnBlur;
    /**
     * 是否在用户在字段表单中输入内容时执行验证
     * @var boolean whether to perform validation while the user is typing in the input field.
     * If not set, it will take the value of [[ActiveForm::validateOnType]].
     *
     * 如果不设置，将会继承 [[ActiveForm::validateOnType]] 的值
     * @see validationDelay
     */
    public $validateOnType;
    /**
     * 当用户在字段中输入时，验证应该被延迟的毫秒数
     *
     * @var integer number of milliseconds that the validation should be delayed when the user types in the field
     * and [[validateOnType]] is set `true`.
     * If not set, it will take the value of [[ActiveForm::validationDelay]].
     *
     * 如果不设置，将会继承 [[ActiveForm::validationDelay]] 的值
     */
    public $validationDelay;
    /**
     * 选择 容器，输入表单和错误标签的 jQuery选择器
     * 数组键应该是`container`, `input`, and/or `error`，
     * 数组值是相应的选择器。例如，`['input' => '#my-input']`。
     * @var array the jQuery selectors for selecting the container, input and error tags.
     * The array keys should be `container`, `input`, and/or `error`, and the array values are the corresponding selectors. For example, `['input' => '#my-input']`.
     *
     * 容器选择器在表单的上下文中使用，输入和错误选择器在容器的上下文中使用
     * The container selector is used under the context of the form,
     * while the input and the error selectors are used under the context of the container.
     *
     * You normally do not need to set this property as the default selectors should work well for most cases.
     */
    public $selectors = [];
    /**
     * 字段的不同部分(如输入、标签)。
     * 这将与模板一起使用，以生成最终的字段HTML代码
     * 键是模板中的令牌名称，而值是对应的HTML代码。
     * 有效的令牌包括`{input}`, `{label}` and `{error}`。
     * 注意，您通常不需要直接访问这个属性，因为它是由这个类的各种方法维护的。
     *
     * @var array different parts of the field (e.g. input, label).
     * This will be used together with [[template]] to generate the final field HTML code.
     * The keys are the token names in [[template]], while the values are the corresponding HTML code.
     * Valid tokens include `{input}`, `{label}` and `{error}`.
     * Note that you normally don't need to access this property directly as it is maintained by various methods of this class.
     */
    public $parts = [];

    /**
     * 该属性保存使用[[inputOptions]]或$options参数设置的自定义输入id
     * @var string this property holds a custom input id if it was set using [[inputOptions]] or in one of the `$options` parameters of the `input*` methods.
     */
    private $_inputId;

    /**
     * 如果“for”字段标签属性应该跳过
     * @var bool if "for" field label attribute should be skipped.
     */
    private $_skipLabelFor = false;


    /**
     * 返回该对象的字符串表示的PHP魔术方法
     * PHP magic method that returns the string representation of this object.
     * @return string the string representation of this object.
     */
    public function __toString()
    {
        // __toString cannot throw exception
        // use trigger_error to bypass this limitation
        try {
            return $this->render();
        } catch (\Exception $e) {
            ErrorHandler::convertExceptionToError($e);
            return '';
        }
    }

    /**
     * 渲染整个字段。
     * 该方法将生成标签、错误标记、输入标记和提示标记(如果有的话)，并根据[[template]]将它们组合成HTML
     * Renders the whole field.
     * This method will generate the label, error tag, input tag and hint tag (if any), and assemble them into HTML according to [[template]].
     *
     * @param string|callable $content the content within the field container.
     * If `null` (not set), the default methods will be called to generate the label, error tag and input tag, and use them as the content.
     * If a callable, it will be called to generate the content. The signature of the callable should be:
     *
     * 字段容器内的内容。
     * 如果是null(不设置)，将调用默认方法来生成标签、错误标记和输入标记，并将其作为内容使用。
     * 如果一个可调用的方法，它将被调用来生成内容。可调用方法的参数应该是：
     * ```php
     * function ($field) {
     *     return $html;
     * }
     * ```
     *
     * @return string the rendering result.
     */
    public function render($content = null)
    {
        if ($content === null) {
            if (!isset($this->parts['{input}'])) {
                $this->textInput();
            }
            if (!isset($this->parts['{label}'])) {
                $this->label();
            }
            if (!isset($this->parts['{error}'])) {
                $this->error();
            }
            if (!isset($this->parts['{hint}'])) {
                $this->hint(null);
            }
            $content = strtr($this->template, $this->parts);
        } elseif (!is_string($content)) {
            $content = call_user_func($content, $this);
        }

        return $this->begin() . "\n" . $content . "\n" . $this->end();
    }

    /**
     * 显示字段容器的开始标记
     * Renders the opening tag of the field container.
     * @return string the rendering result.
     */
    public function begin()
    {
        if ($this->form->enableClientScript) {
            $clientOptions = $this->getClientOptions();
            if (!empty($clientOptions)) {
                $this->form->attributes[] = $clientOptions;
            }
        }

        $inputID = $this->getInputId();
        $attribute = Html::getAttributeName($this->attribute);
        $options = $this->options;
        $class = isset($options['class']) ? [$options['class']] : [];
        $class[] = "field-$inputID";
        if ($this->model->isAttributeRequired($attribute)) {
            $class[] = $this->form->requiredCssClass;
        }
        if ($this->model->hasErrors($attribute)) {
            $class[] = $this->form->errorCssClass;
        }
        $options['class'] = implode(' ', $class);
        $tag = ArrayHelper::remove($options, 'tag', 'div');

        return Html::beginTag($tag, $options);
    }

    /**
     * 显示字段容器的结束标记
     * Renders the closing tag of the field container.
     * @return string the rendering result.
     */
    public function end()
    {
        return Html::endTag(ArrayHelper::keyExists('tag', $this->options) ? $this->options['tag'] : 'div');
    }

    /**
     * 为[[attribute]]生成标签标记
     * Generates a label tag for [[attribute]].
     * @param null|string|false $label the label to use. If `null`, the label will be generated via [[Model::getAttributeLabel()]].
     * If `false`, the generated field will not contain the label part.
     * Note that this will NOT be [[Html::encode()|encoded]].
     * @param null|array $options the tag options in terms of name-value pairs. It will be merged with [[labelOptions]].
     * The options will be rendered as the attributes of the resulting tag. The values will be HTML-encoded
     * using [[Html::encode()]]. If a value is `null`, the corresponding attribute will not be rendered.
     * @return $this the field object itself.
     */
    public function label($label = null, $options = [])
    {
        if ($label === false) {
            $this->parts['{label}'] = '';
            return $this;
        }

        $options = array_merge($this->labelOptions, $options);
        if ($label !== null) {
            $options['label'] = $label;
        }

        if ($this->_skipLabelFor) {
            $options['for'] = null;
        }

        $this->parts['{label}'] = Html::activeLabel($this->model, $this->attribute, $options);

        return $this;
    }

    /**
     * 生成该属性[[attribute]]包含的第一个验证错误的标签。
     * 注意，即使没有验证错误，这个方法仍然会返回一个空的错误标签。
     * Generates a tag that contains the first validation error of [[attribute]].
     * Note that even if there is no validation error, this method will still return an empty error tag.
     * @param array|false $options the tag options in terms of name-value pairs. It will be merged with [[errorOptions]].
     * The options will be rendered as the attributes of the resulting tag. The values will be HTML-encoded
     * using [[Html::encode()]]. If this parameter is `false`, no error tag will be rendered.
     *
     * The following options are specially handled:
     *
     * - `tag`: this specifies the tag name. If not set, `div` will be used.
     *   See also [[\yii\helpers\Html::tag()]].
     *
     * If you set a custom `id` for the error element, you may need to adjust the [[$selectors]] accordingly.
     * @see $errorOptions
     * @return $this the field object itself.
     */
    public function error($options = [])
    {
        if ($options === false) {
            $this->parts['{error}'] = '';
            return $this;
        }
        $options = array_merge($this->errorOptions, $options);
        $this->parts['{error}'] = Html::error($this->model, $this->attribute, $options);

        return $this;
    }

    /**
     * 显示提示标签
     * Renders the hint tag.
     * @param string|bool $content the hint content.
     * 如果是null，则提示将通过[[Model::getAttributeHint()]]生成。
     * 如果是false，生成的字段将不包含提示部分。
     * 注意，该提示不经过[[Html::encode()|encoded]]编码。
     * If `null`, the hint will be generated via [[Model::getAttributeHint()]].
     * If `false`, the generated field will not contain the hint part.
     * Note that this will NOT be [[Html::encode()|encoded]].
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the hint tag. The values will be HTML-encoded using [[Html::encode()]].
     *
     * The following options are specially handled:
     * 以下特殊选项被识别：
     * - `tag`: 指定了标签名. 如果未设置，则使用 div.
     *
     * - `tag`: this specifies the tag name. If not set, `div` will be used.
     *   See also [[\yii\helpers\Html::tag()]].
     *
     * @return $this the field object itself.
     */
    public function hint($content, $options = [])
    {
        if ($content === false) {
            $this->parts['{hint}'] = '';
            return $this;
        }

        $options = array_merge($this->hintOptions, $options);
        if ($content !== null) {
            $options['hint'] = $content;
        }
        $this->parts['{hint}'] = Html::activeHint($this->model, $this->attribute, $options);

        return $this;
    }

    /**
     * 呈现一个输入框标签
     * Renders an input tag.
     * @param string $type the input type (e.g. `text`, `password`)
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[Html::encode()]].
     *
     * 如果您为输入元素设置了自定义id，那么您可能需要相应地调整[[$selectors]]。
     * If you set a custom `id` for the input element, you may need to adjust the [[$selectors]] accordingly.
     *
     * @return $this the field object itself.
     */
    public function input($type, $options = [])
    {
        $options = array_merge($this->inputOptions, $options);
        $this->adjustLabelFor($options);
        $this->parts['{input}'] = Html::activeInput($type, $this->model, $this->attribute, $options);

        return $this;
    }

    /**
     * 呈现文本输入框。
     * 该方法将为模型属性自动生成`name`和`value`标记属性，除非它们在$options中显式指定。
     * Renders a text input.
     * This method will generate the `name` and `value` tag attributes automatically for the model attribute unless they are explicitly specified in `$options`.
     *
     * 标签选项的键值对。
     * 这些将呈现为所生成的标记的属性。
     * 这些值将使用[[Html::encode()]]进行HTML编码
     * @param array $options the tag options in terms of name-value pairs.
     * These will be rendered as the attributes of the resulting tag.
     * The values will be HTML-encoded using [[Html::encode()]].
     *
     * The following special options are recognized:
     * 以下特殊选项被识别：
     * - `maxlength` ：当`maxlength`属性设为 true,并且模型属性通过一个字符串验证器进行验证，
     *   `maxlength` 选项将使用[[\yii\validators\StringValidator::max]]验证。
     * - `maxlength`: integer|boolean, when `maxlength` is set `true` and the model attribute is validated by a string validator,
     * the `maxlength` option will take the value of [[\yii\validators\StringValidator::max]].
     *   This is available since version 2.0.3.
     *
     * 注意，如果为输入元素设置了自定义id，则可能需要相应地调整选择器的值
     * Note that if you set a custom `id` for the input element, you may need to adjust the value of [[selectors]] accordingly.
     *
     * @return $this the field object itself.
     */
    public function textInput($options = [])
    {
        $options = array_merge($this->inputOptions, $options);
        $this->adjustLabelFor($options);
        $this->parts['{input}'] = Html::activeTextInput($this->model, $this->attribute, $options);

        return $this;
    }

    /**
     * 呈现一个隐藏的输入
     * Renders a hidden input.
     *
     * 注意，这个方法是为完整性提供的.
     * 在大多数情况下，因为不需要验证隐藏的输入，所以不需要使用这种方法.
     * 相反，应该使用 [[\yii\helpers\Html::activeHiddenInput()]]
     * Note that this method is provided for completeness.
     * In most cases because you do not need to validate a hidden input, you should not need to use this method.
     * Instead, you should use [[\yii\helpers\Html::activeHiddenInput()]].
     *
     * 该方法将自动为model属性自动生成`name`和`value`标记属性，除非它们在$options中显式指定.
     * This method will generate the `name` and `value` tag attributes automatically for the model attribute unless they are explicitly specified in `$options`.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[Html::encode()]].
     *
     * If you set a custom `id` for the input element, you may need to adjust the [[$selectors]] accordingly.
     *
     * @return $this the field object itself.
     */
    public function hiddenInput($options = [])
    {
        $options = array_merge($this->inputOptions, $options);
        $this->adjustLabelFor($options);
        $this->parts['{input}'] = Html::activeHiddenInput($this->model, $this->attribute, $options);

        return $this;
    }

    /**
     * 呈现一个密码输入框。
     * 该方法将自动为model属性自动生成`name`和`value`标记属性，除非它们在$options中显式指定.
     * Renders a password input.
     * This method will generate the `name` and `value` tag attributes automatically for the model attribute
     * unless they are explicitly specified in `$options`.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[Html::encode()]].
     *
     * 注意，如果为输入元素设置了自定义id，则可能需要相应地调整选择器的值
     * If you set a custom `id` for the input element, you may need to adjust the [[$selectors]] accordingly.
     *
     * @return $this the field object itself.
     */
    public function passwordInput($options = [])
    {
        $options = array_merge($this->inputOptions, $options);
        $this->adjustLabelFor($options);
        $this->parts['{input}'] = Html::activePasswordInput($this->model, $this->attribute, $options);

        return $this;
    }

    /**
     * 呈现一个文件输入框。
     * 该方法将自动为model属性自动生成`name`和`value`标记属性，除非它们在$options中显式指定.
     * Renders a file input.
     * This method will generate the `name` and `value` tag attributes automatically for the model attribute
     * unless they are explicitly specified in `$options`.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[Html::encode()]].
     *
     * 如果为输入元素设置了自定义id，则可能需要相应地调整选择器的值
     * If you set a custom `id` for the input element, you may need to adjust the [[$selectors]] accordingly.
     *
     * @return $this the field object itself.
     */
    public function fileInput($options = [])
    {
        // https://github.com/yiisoft/yii2/pull/795
        if ($this->inputOptions !== ['class' => 'form-control']) {
            $options = array_merge($this->inputOptions, $options);
        }
        // https://github.com/yiisoft/yii2/issues/8779
        if (!isset($this->form->options['enctype'])) {
            $this->form->options['enctype'] = 'multipart/form-data';
        }
        $this->adjustLabelFor($options);
        $this->parts['{input}'] = Html::activeFileInput($this->model, $this->attribute, $options);

        return $this;
    }

    /**
     * 呈现一个文本域输入框。
     * 模型属性值将用作textarea中的内容
     * Renders a text area.
     * The model attribute value will be used as the content in the textarea.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[Html::encode()]].
     *
     * 如果为输入元素设置了自定义id，则可能需要相应地调整选择器的值
     * If you set a custom `id` for the textarea element, you may need to adjust the [[$selectors]] accordingly.
     *
     * @return $this the field object itself.
     */
    public function textarea($options = [])
    {
        $options = array_merge($this->inputOptions, $options);
        $this->adjustLabelFor($options);
        $this->parts['{input}'] = Html::activeTextarea($this->model, $this->attribute, $options);

        return $this;
    }

    /**
     * 呈现一个单选按钮。
     * 该方法将根据模型属性值生成选中的标记属性。
     * Renders a radio button.
     * This method will generate the `checked` tag attribute according to the model attribute value.
     *
     * 选项标签的键值对。
     * 以下选项将是特别处理的：
     *
     * - `uncheck`: string, 与单选按钮的未选中状态相关联的值. 若未设置，默认为 0。
     *    如果没有选中单选按钮的情况下提交，该方法将呈现一个隐藏的输入，该属性的值仍然会通过隐藏的输入提交给服务器。
     *    您不需要任何隐藏的输入，您应该显式地将该选项设置为null。
     * - `label`: string, 在单选按钮旁边显示的标签。它不会被html编码.因此，您可以传入HTML代码，例如图片标签。
     *    如果这是来自最终用户的，那么您应该使用[[Html::encode()|encode]]对其进行编码，以防止XSS攻击。
     *    当指定这个选项时，单选按钮将被一个标签标记所包围。
     *    如果您不想要任何标签，那么您应该显式地将该选项设置为null。
     * - `labelOptions`: array, label标签的HTML属性。只有在指定label选项时才使用它
     *
     * @param array $options the tag options in terms of name-value pairs. The following options are specially handled:
     *
     * - `uncheck`: string, the value associated with the uncheck state of the radio button. If not set,
     *   it will take the default value `0`. This method will render a hidden input so that if the radio button
     *   is not checked and is submitted, the value of this attribute will still be submitted to the server
     *   via the hidden input. If you do not want any hidden input, you should explicitly set this option as `null`.
     * - `label`: string, a label displayed next to the radio button. It will NOT be HTML-encoded. Therefore you can pass
     *   in HTML code such as an image tag. If this is coming from end users, you should [[Html::encode()|encode]] it to prevent XSS attacks.
     *   When this option is specified, the radio button will be enclosed by a label tag. If you do not want any label, you should
     *   explicitly set this option as `null`.
     * - `labelOptions`: array, the HTML attributes for the label tag. This is only used when the `label` option is specified.
     *
     * The rest of the options will be rendered as the attributes of the resulting tag. The values will be HTML-encoded using [[Html::encode()]].
     * If a value is `null`, the corresponding attribute will not be rendered.
     *
     * 如果为输入元素设置了自定义id，则可能需要相应地调整选择器的值
     * If you set a custom `id` for the input element, you may need to adjust the [[$selectors]] accordingly.
     *
     * @param boolean $enclosedByLabel whether to enclose the radio within the label.
     * If `true`, the method will still use [[template]] to layout the radio button and the error message
     * except that the radio is enclosed by the label tag.
     * @return $this the field object itself.
     */
    public function radio($options = [], $enclosedByLabel = true)
    {
        if ($enclosedByLabel) {
            $this->parts['{input}'] = Html::activeRadio($this->model, $this->attribute, $options);
            $this->parts['{label}'] = '';
        } else {
            if (isset($options['label']) && !isset($this->parts['{label}'])) {
                $this->parts['{label}'] = $options['label'];
                if (!empty($options['labelOptions'])) {
                    $this->labelOptions = $options['labelOptions'];
                }
            }
            unset($options['labelOptions']);
            $options['label'] = null;
            $this->parts['{input}'] = Html::activeRadio($this->model, $this->attribute, $options);
        }
        $this->adjustLabelFor($options);

        return $this;
    }

    /**
     * 呈现一个复选框。
     * 该方法将根据模型属性值生成选中的标记属性。
     * Renders a checkbox.
     * This method will generate the `checked` tag attribute according to the model attribute value.
     *
     * 选项标签的键值对。
     * 以下选项将是特别处理的：
     *
     * - `uncheck`: string, 与单选按钮的未选中状态相关联的值. 若未设置，默认为 0。
     *    如果没有选中单选按钮的情况下提交，该方法将呈现一个隐藏的输入，该属性的值仍然会通过隐藏的输入提交给服务器。
     *    您不需要任何隐藏的输入，您应该显式地将该选项设置为null。
     * - `label`: string, 在单选按钮旁边显示的标签。它不会被html编码.因此，您可以传入HTML代码，例如图片标签。
     *    如果这是来自最终用户的，那么您应该使用[[Html::encode()|encode]]对其进行编码，以防止XSS攻击。
     *    当指定这个选项时，单选按钮将被一个标签标记所包围。
     *    如果您不想要任何标签，那么您应该显式地将该选项设置为null。
     * - `labelOptions`: array, label标签的HTML属性。只有在指定label选项时才使用它
     *
     * @param array $options the tag options in terms of name-value pairs. The following options are specially handled:
     *
     * - `uncheck`: string, the value associated with the uncheck state of the radio button. If not set,
     *   it will take the default value `0`. This method will render a hidden input so that if the radio button
     *   is not checked and is submitted, the value of this attribute will still be submitted to the server
     *   via the hidden input. If you do not want any hidden input, you should explicitly set this option as `null`.
     * - `label`: string, a label displayed next to the checkbox. It will NOT be HTML-encoded. Therefore you can pass
     *   in HTML code such as an image tag. If this is coming from end users, you should [[Html::encode()|encode]] it to prevent XSS attacks.
     *   When this option is specified, the checkbox will be enclosed by a label tag. If you do not want any label, you should
     *   explicitly set this option as `null`.
     * - `labelOptions`: array, the HTML attributes for the label tag. This is only used when the `label` option is specified.
     *
     * The rest of the options will be rendered as the attributes of the resulting tag. The values will
     * be HTML-encoded using [[Html::encode()]]. If a value is `null`, the corresponding attribute will not be rendered.
     *
     * If you set a custom `id` for the input element, you may need to adjust the [[$selectors]] accordingly.
     *
     * @param boolean $enclosedByLabel whether to enclose the checkbox within the label.
     * If `true`, the method will still use [[template]] to layout the checkbox and the error message
     * except that the checkbox is enclosed by the label tag.
     * @return $this the field object itself.
     */
    public function checkbox($options = [], $enclosedByLabel = true)
    {
        if ($enclosedByLabel) {
            $this->parts['{input}'] = Html::activeCheckbox($this->model, $this->attribute, $options);
            $this->parts['{label}'] = '';
        } else {
            if (isset($options['label']) && !isset($this->parts['{label}'])) {
                $this->parts['{label}'] = $options['label'];
                if (!empty($options['labelOptions'])) {
                    $this->labelOptions = $options['labelOptions'];
                }
            }
            unset($options['labelOptions']);
            $options['label'] = null;
            $this->parts['{input}'] = Html::activeCheckbox($this->model, $this->attribute, $options);
        }
        $this->adjustLabelFor($options);

        return $this;
    }

    /**
     * 显示一个下拉列表
     * Renders a drop-down list.
     * The selection of the drop-down list is taken from the value of the model attribute.
     * @param array $items the option data items. The array keys are option values, and the array values
     * are the corresponding option labels. The array can also be nested (i.e. some array values are arrays too).
     * For each sub-array, an option group will be generated whose label is the key associated with the sub-array.
     * If you have a list of data models, you may convert them into the format described above using
     * [[ArrayHelper::map()]].
     *
     * Note, the values and labels will be automatically HTML-encoded by this method, and the blank spaces in
     * the labels will also be HTML-encoded.
     * @param array $options the tag options in terms of name-value pairs.
     *
     * For the list of available options please refer to the `$options` parameter of [[\yii\helpers\Html::activeDropDownList()]].
     *
     * If you set a custom `id` for the input element, you may need to adjust the [[$selectors]] accordingly.
     *
     * @return $this the field object itself.
     */
    public function dropDownList($items, $options = [])
    {
        $options = array_merge($this->inputOptions, $options);
        $this->adjustLabelFor($options);
        $this->parts['{input}'] = Html::activeDropDownList($this->model, $this->attribute, $items, $options);

        return $this;
    }

    /**
     * 呈现一个列表框
     * Renders a list box.
     * The selection of the list box is taken from the value of the model attribute.
     * @param array $items the option data items. The array keys are option values, and the array values
     * are the corresponding option labels. The array can also be nested (i.e. some array values are arrays too).
     * For each sub-array, an option group will be generated whose label is the key associated with the sub-array.
     * If you have a list of data models, you may convert them into the format described above using
     * [[\yii\helpers\ArrayHelper::map()]].
     *
     * Note, the values and labels will be automatically HTML-encoded by this method, and the blank spaces in
     * the labels will also be HTML-encoded.
     * @param array $options the tag options in terms of name-value pairs.
     *
     * For the list of available options please refer to the `$options` parameter of [[\yii\helpers\Html::activeListBox()]].
     *
     * If you set a custom `id` for the input element, you may need to adjust the [[$selectors]] accordingly.
     *
     * @return $this the field object itself.
     */
    public function listBox($items, $options = [])
    {
        $options = array_merge($this->inputOptions, $options);
        $this->adjustLabelFor($options);
        $this->parts['{input}'] = Html::activeListBox($this->model, $this->attribute, $items, $options);

        return $this;
    }

    /**
     * 显示一个复选框列表。
     * Renders a list of checkboxes.
     * A checkbox list allows multiple selection, like [[listBox()]].
     * As a result, the corresponding submitted value is an array.
     * The selection of the checkbox list is taken from the value of the model attribute.
     * @param array $items the data item used to generate the checkboxes.
     * The array values are the labels, while the array keys are the corresponding checkbox values.
     * @param array $options options (name => config) for the checkbox list.
     * For the list of available options please refer to the `$options` parameter of [[\yii\helpers\Html::activeCheckboxList()]].
     * @return $this the field object itself.
     */
    public function checkboxList($items, $options = [])
    {
        $this->adjustLabelFor($options);
        $this->_skipLabelFor = true;
        $this->parts['{input}'] = Html::activeCheckboxList($this->model, $this->attribute, $items, $options);

        return $this;
    }

    /**
     * 显示一个单选按钮列表。
     * 单选按钮列表就像一个复选框列表，除了它只允许单个选择
     * Renders a list of radio buttons.
     * A radio button list is like a checkbox list, except that it only allows single selection.
     * The selection of the radio buttons is taken from the value of the model attribute.
     * @param array $items the data item used to generate the radio buttons.
     * The array values are the labels, while the array keys are the corresponding radio values.
     * @param array $options options (name => config) for the radio button list.
     * For the list of available options please refer to the `$options` parameter of [[\yii\helpers\Html::activeRadioList()]].
     * @return $this the field object itself.
     */
    public function radioList($items, $options = [])
    {
        $this->adjustLabelFor($options);
        $this->_skipLabelFor = true;
        $this->parts['{input}'] = Html::activeRadioList($this->model, $this->attribute, $items, $options);

        return $this;
    }

    /**
     * 呈现出一个作为字段输入框的小部件
     *
     * Renders a widget as the input of the field.
     *
     * 注意，小部件必须具有`model` and `attribute`属性
     * Note that the widget must have both `model` and `attribute` properties. They will
     * be initialized with [[model]] and [[attribute]] of this field, respectively.
     *
     * If you want to use a widget that does not have `model` and `attribute` properties,
     * please use [[render()]] instead.
     *
     * For example to use the [[MaskedInput]] widget to get some date input, you can use
     * the following code, assuming that `$form` is your [[ActiveForm]] instance:
     *
     * ```php
     * $form->field($model, 'date')->widget(\yii\widgets\MaskedInput::className(), [
     *     'mask' => '99/99/9999',
     * ]);
     * ```
     *
     * If you set a custom `id` for the input element, you may need to adjust the [[$selectors]] accordingly.
     *
     * @param string $class the widget class name.
     * @param array $config name-value pairs that will be used to initialize the widget.
     * @return $this the field object itself.
     */
    public function widget($class, $config = [])
    {
        /* @var $class \yii\base\Widget */
        $config['model'] = $this->model;
        $config['attribute'] = $this->attribute;
        $config['view'] = $this->form->getView();
        $this->parts['{input}'] = $class::widget($config);

        return $this;
    }

    /**
     * 根据输入选项调整标签的`for`属性
     * Adjusts the `for` attribute for the label based on the input options.
     * @param array $options the input options.
     */
    protected function adjustLabelFor($options)
    {
        if (!isset($options['id'])) {
            return;
        }
        $this->_inputId = $options['id'];
        if (!isset($this->labelOptions['for'])) {
            $this->labelOptions['for'] = $options['id'];
        }
    }

    /**
     * 返回该字段的JS选项
     * Returns the JS options for the field.
     * @return array the JS options.
     */
    protected function getClientOptions()
    {
        $attribute = Html::getAttributeName($this->attribute);
        if (!in_array($attribute, $this->model->activeAttributes(), true)) {
            return [];
        }

        $enableClientValidation = $this->enableClientValidation || $this->enableClientValidation === null && $this->form->enableClientValidation;
        $enableAjaxValidation = $this->enableAjaxValidation || $this->enableAjaxValidation === null && $this->form->enableAjaxValidation;

        if ($enableClientValidation) {
            $validators = [];
            foreach ($this->model->getActiveValidators($attribute) as $validator) {
                /* @var $validator \yii\validators\Validator */
                $js = $validator->clientValidateAttribute($this->model, $attribute, $this->form->getView());
                if ($validator->enableClientValidation && $js != '') {
                    if ($validator->whenClient !== null) {
                        $js = "if (({$validator->whenClient})(attribute, value)) { $js }";
                    }
                    $validators[] = $js;
                }
            }
        }

        if (!$enableAjaxValidation && (!$enableClientValidation || empty($validators))) {
            return [];
        }

        $options = [];

        $inputID = $this->getInputId();
        $options['id'] = Html::getInputId($this->model, $this->attribute);
        $options['name'] = $this->attribute;

        $options['container'] = isset($this->selectors['container']) ? $this->selectors['container'] : ".field-$inputID";
        $options['input'] = isset($this->selectors['input']) ? $this->selectors['input'] : "#$inputID";
        if (isset($this->selectors['error'])) {
            $options['error'] = $this->selectors['error'];
        } elseif (isset($this->errorOptions['class'])) {
            $options['error'] = '.' . implode('.', preg_split('/\s+/', $this->errorOptions['class'], -1, PREG_SPLIT_NO_EMPTY));
        } else {
            $options['error'] = isset($this->errorOptions['tag']) ? $this->errorOptions['tag'] : 'span';
        }

        $options['encodeError'] = !isset($this->errorOptions['encode']) || $this->errorOptions['encode'];
        if ($enableAjaxValidation) {
            $options['enableAjaxValidation'] = true;
        }
        foreach (['validateOnChange', 'validateOnBlur', 'validateOnType', 'validationDelay'] as $name) {
            $options[$name] = $this->$name === null ? $this->form->$name : $this->$name;
        }

        if (!empty($validators)) {
            $options['validate'] = new JsExpression("function (attribute, value, messages, deferred, \$form) {" . implode('', $validators) . '}');
        }

        // only get the options that are different from the default ones (set in yii.activeForm.js)
        return array_diff_assoc($options, [
            'validateOnChange' => true,
            'validateOnBlur' => true,
            'validateOnType' => false,
            'validationDelay' => 500,
            'encodeError' => true,
            'error' => '.help-block',
        ]);
    }

    /**
     * 返回这个表单字段的输入元素的HTML id
     * Returns the HTML `id` of the input element of this form field.
     * @return string the input id.
     * @since 2.0.7
     */
    protected function getInputId()
    {
        return $this->_inputId ?: Html::getInputId($this->model, $this->attribute);
    }
}
