<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\captcha;

use Yii;
use yii\base\InvalidConfigException;
use yii\validators\ValidationAsset;
use yii\validators\Validator;

/**
 * 验证属性值是否与验证码中显示的验证码相同
 * CaptchaValidator validates that the attribute value is the same as the verification code displayed in the CAPTCHA.
 *
 * 该验证器通常配合 yii\captcha\CaptchaAction 以及 yii\captcha\Captcha 使用，以确保某一输入与 CAPTCHA 小部件所显示的验证代码（verification code）相同。
 * CaptchaValidator should be used together with [[CaptchaAction]].
 *
 * 注意，一旦验证码验证成功，就会自动生成一个新的验证码。
 * 因此，CAPTCHA验证不应该在AJAX验证模式下使用，
 * 即使用户输入的代码和CAPTCHA图像中显示的一样,实际上不同于服务器最新生成的验证码,验证码也依然会失败。
 * Note that once CAPTCHA validation succeeds, a new CAPTCHA will be generated automatically. As a result,
 * CAPTCHA validation should not be used in AJAX validation mode because it may fail the validation
 * even if a user enters the same code as shown in the CAPTCHA image which is actually different from the latest CAPTCHA code.
 *
 * ['verificationCode', 'captcha'],
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class CaptchaValidator extends Validator
{
    /**
     * 当输入为空时，是否跳过验证。 默认为 false，也就是输入值为必需项。
     * @var bool whether to skip this validator if the input is empty.
     */
    public $skipOnEmpty = false;
    /**
     * 对验证代码的比对是否要求大小写敏感。默认为 false。
     * @var bool whether the comparison is case sensitive. Defaults to false.
     */
    public $caseSensitive = false;
    /**
     * 指向用于渲染 CAPTCHA 图片的 CAPTCHA action 的 路由。 默认为 'site/captcha'
     * @var string the route of the controller action that renders the CAPTCHA image.
     */
    public $captchaAction = 'site/captcha';


    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();
        if ($this->message === null) {
            $this->message = Yii::t('yii', 'The verification code is incorrect.');
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function validateValue($value)
    {
        $captcha = $this->createCaptchaAction();
        $valid = !is_array($value) && $captcha->validate($value, $this->caseSensitive);

        return $valid ? null : [$this->message, []];
    }

    /**
     * Creates the CAPTCHA action object from the route specified by [[captchaAction]].
     * @return \yii\captcha\CaptchaAction the action object
     * @throws InvalidConfigException
     */
    public function createCaptchaAction()
    {
        $ca = Yii::$app->createController($this->captchaAction);
        if ($ca !== false) {
            /* @var $controller \yii\base\Controller */
            list($controller, $actionID) = $ca;
            $action = $controller->createAction($actionID);
            if ($action !== null) {
                return $action;
            }
        }
        throw new InvalidConfigException('Invalid CAPTCHA action ID: ' . $this->captchaAction);
    }

    /**
     * {@inheritdoc}
     */
    public function clientValidateAttribute($model, $attribute, $view)
    {
        ValidationAsset::register($view);
        $options = $this->getClientOptions($model, $attribute);

        return 'yii.validation.captcha(value, messages, ' . json_encode($options, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ');';
    }

    /**
     * {@inheritdoc}
     */
    public function getClientOptions($model, $attribute)
    {
        $captcha = $this->createCaptchaAction();
        $code = $captcha->getVerifyCode(false);
        $hash = $captcha->generateValidationHash($this->caseSensitive ? $code : strtolower($code));
        $options = [
            'hash' => $hash,
            'hashKey' => 'yiiCaptcha/' . $captcha->getUniqueId(),
            'caseSensitive' => $this->caseSensitive,
            'message' => Yii::$app->getI18n()->format($this->message, [
                'attribute' => $model->getAttributeLabel($attribute),
            ], Yii::$app->language),
        ];
        if ($this->skipOnEmpty) {
            $options['skipOnEmpty'] = 1;
        }

        return $options;
    }
}
