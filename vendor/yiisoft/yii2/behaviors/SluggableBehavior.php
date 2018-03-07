<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\behaviors;

use yii\base\InvalidConfigException;
use yii\db\BaseActiveRecord;
use yii\helpers\Inflector;
use yii\validators\UniqueValidator;
use Yii;

/**
 * 使用 SluggableBehavior 可以让我们的URL美化更加语义化。
 * e.g.   http://abc.com/member/view/3  => http://abc.com/member/zhang-san
 * 这个行为类有一个缺点就是不支持中文
 *
 * 使用方法：
 * 1. 首先我需要在member表中增加一个叫做 slug 的字段。
 *
 * ```
 *  // migrate 代码如下
    $this->addColumn('member','slug',$this->string(64)->notNull());
 * ```
 *
 * 2.将SluggableBehavior 注入到Member模型中，增强其功能。
 *
 * ```
    class Member extends \yii\db\ActiveRecord
    {
        ...

        public function behaviors(){
            return [
                [
                    'class' => \yii\behaviors\SluggableBehavior::className(),
                    'attribute' => 'username',
                    // 要注意的是，yii2框架的slugAttribute默认为slug，而我们刚刚在数据表中增加的字段也叫slug，因此不需要再设置slugAttribute了。
                    // 'slugAttribute' => 'slug',
                ],
            ];
        }
    }
 * ```
 *
 * 3. 接下来我们生成一个username=Zhang San的记录，你会发现该记录的slug自动被填充为zhang-san了
 * 4. 配置文件中 urlManager 添加配置
 *
 * ```
    'urlManager' => [
        'enablePrettyUrl' => true,
        'showScriptName' => false,
        'rules' => [
            'member/<slug>' => 'member/slug',
 *          ...
        ],
    ],
 * ```
 *
 * 5. 在MemberController控制器中添加 actionSlug()
 *
 * ```
 *  public function actionSlug($slug)
    {
        $model = Member::findOne(['slug' => $slug]);

 *      ... other ...
    }
 *
 * ```
 *
 *
 * SluggableBehavior automatically fills the specified attribute with a value that can be used a slug in a URL.
 *
 * To use SluggableBehavior, insert the following code to your ActiveRecord class:
 *
 * ```php
 * use yii\behaviors\SluggableBehavior;
 *
 * public function behaviors()
 * {
 *     return [
 *         [
 *             'class' => SluggableBehavior::className(),
 *             'attribute' => 'title',
 *             // 'slugAttribute' => 'slug',
 *         ],
 *     ];
 * }
 * ```
 *
 * By default, SluggableBehavior will fill the `slug` attribute with a value that can be used a slug in a URL
 * when the associated AR object is being validated.
 *
 * Because attribute values will be set automatically by this behavior, they are usually not user input and should therefore
 * not be validated, i.e. the `slug` attribute should not appear in the [[\yii\base\Model::rules()|rules()]] method of the model.
 *
 * If your attribute name is different, you may configure the [[slugAttribute]] property like the following:
 *
 * ```php
 * public function behaviors()
 * {
 *     return [
 *         [
 *             'class' => SluggableBehavior::className(),
 *             'slugAttribute' => 'alias',
 *         ],
 *     ];
 * }
 * ```
 *
 * @author Alexander Kochetov <creocoder@gmail.com>
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 */
class SluggableBehavior extends AttributeBehavior
{
    /**
     * @var string the attribute that will receive the slug value
     */
    public $slugAttribute = 'slug';
    /**
     * @var string|array the attribute or list of attributes whose value will be converted into a slug
     */
    public $attribute;
    /**
     * @var string|callable the value that will be used as a slug. This can be an anonymous function
     * or an arbitrary value. If the former, the return value of the function will be used as a slug.
     * The signature of the function should be as follows,
     *
     * ```php
     * function ($event)
     * {
     *     // return slug
     * }
     * ```
     */
    public $value;
    /**
     * immutable 此参数默认为假，当设置为真时，一旦一个记录被生成，以后就算更更新了 'attribute' => 'username' 字段，slug值也不会改变。
     * @var boolean whether to generate a new slug if it has already been generated before.
     * If true, the behavior will not generate a new slug even if [[attribute]] is changed.
     * @since 2.0.2
     */
    public $immutable = false;
    /**
     * ensureUnique 此参数默认为假，当设置为真时，可以有效避免slug的重复，如果两个username都叫做 zhang san，则生成的slug会是zhang-san 和 zhang-san-2
     * @var boolean whether to ensure generated slug value to be unique among owner class records.
     * If enabled behavior will validate slug uniqueness automatically. If validation fails it will attempt
     * generating unique slug value from based one until success.
     */
    public $ensureUnique = false;
    /**
     * @var array configuration for slug uniqueness validator. Parameter 'class' may be omitted - by default
     * [[UniqueValidator]] will be used.
     * @see UniqueValidator
     */
    public $uniqueValidator = [];
    /**
     * @var callable slug unique value generator. It is used in case [[ensureUnique]] enabled and generated
     * slug is not unique. This should be a PHP callable with following signature:
     *
     * ```php
     * function ($baseSlug, $iteration, $model)
     * {
     *     // return uniqueSlug
     * }
     * ```
     *
     * If not set unique slug will be generated adding incrementing suffix to the base slug.
     */
    public $uniqueSlugGenerator;


    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        if (empty($this->attributes)) {
            $this->attributes = [BaseActiveRecord::EVENT_BEFORE_VALIDATE => $this->slugAttribute];
        }

        if ($this->attribute === null && $this->value === null) {
            throw new InvalidConfigException('Either "attribute" or "value" property must be specified.');
        }
    }

    /**
     * @inheritdoc
     */
    protected function getValue($event)
    {
        if ($this->attribute !== null) {
            if ($this->isNewSlugNeeded()) {
                $slugParts = [];
                foreach ((array) $this->attribute as $attribute) {
                    $slugParts[] = $this->owner->{$attribute};
                }

                $slug = $this->generateSlug($slugParts);
            } else {
                return $this->owner->{$this->slugAttribute};
            }
        } else {
            $slug = parent::getValue($event);
        }

        return $this->ensureUnique ? $this->makeUnique($slug) : $slug;
    }

    /**
     * Checks whether the new slug generation is needed
     * This method is called by [[getValue]] to check whether the new slug generation is needed.
     * You may override it to customize checking.
     * @return boolean
     * @since 2.0.7
     */
    protected function isNewSlugNeeded()
    {
        if (empty($this->owner->{$this->slugAttribute})) {
            return true;
        }

        if ($this->immutable) {
            return false;
        }

        foreach ((array)$this->attribute as $attribute) {
            if ($this->owner->isAttributeChanged($attribute)) {
                return true;
            }
        }

        return false;
    }

    /**
     * This method is called by [[getValue]] to generate the slug.
     * You may override it to customize slug generation.
     * The default implementation calls [[\yii\helpers\Inflector::slug()]] on the input strings
     * concatenated by dashes (`-`).
     * @param array $slugParts an array of strings that should be concatenated and converted to generate the slug value.
     * @return string the conversion result.
     */
    protected function generateSlug($slugParts)
    {
        return Inflector::slug(implode('-', $slugParts));
    }

    /**
     * This method is called by [[getValue]] when [[ensureUnique]] is true to generate the unique slug.
     * Calls [[generateUniqueSlug]] until generated slug is unique and returns it.
     * @param string $slug basic slug value
     * @return string unique slug
     * @see getValue
     * @see generateUniqueSlug
     * @since 2.0.7
     */
    protected function makeUnique($slug)
    {
        $uniqueSlug = $slug;
        $iteration = 0;
        while (!$this->validateSlug($uniqueSlug)) {
            $iteration++;
            $uniqueSlug = $this->generateUniqueSlug($slug, $iteration);
        }
        return $uniqueSlug;
    }

    /**
     * Checks if given slug value is unique.
     * @param string $slug slug value
     * @return boolean whether slug is unique.
     */
    protected function validateSlug($slug)
    {
        /* @var $validator UniqueValidator */
        /* @var $model BaseActiveRecord */
        $validator = Yii::createObject(array_merge(
            [
                'class' => UniqueValidator::className(),
            ],
            $this->uniqueValidator
        ));

        $model = clone $this->owner;
        $model->clearErrors();
        $model->{$this->slugAttribute} = $slug;

        $validator->validateAttribute($model, $this->slugAttribute);
        return !$model->hasErrors();
    }

    /**
     * Generates slug using configured callback or increment of iteration.
     * @param string $baseSlug base slug value
     * @param integer $iteration iteration number
     * @return string new slug value
     * @throws \yii\base\InvalidConfigException
     */
    protected function generateUniqueSlug($baseSlug, $iteration)
    {
        if (is_callable($this->uniqueSlugGenerator)) {
            return call_user_func($this->uniqueSlugGenerator, $baseSlug, $iteration, $this->owner);
        }
        return $baseSlug . '-' . ($iteration + 1);
    }
}
