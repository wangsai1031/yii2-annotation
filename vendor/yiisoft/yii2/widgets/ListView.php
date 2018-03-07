<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\widgets;

use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/**
 * The ListView widget is used to display data from data
 * provider. Each data model is rendered using the view
 * specified.
 *
 * ListView 小部件用于显示数据提供者 data provider 提供的数据。
 * 每个数据模型用指定的视图文件 view file 来渲染。
 * 因为它提供开箱即用式的（译者注：封装好的）分页、排序以及过滤这样一些特性，
 * 所以它可以很方便地为最终用户显示信息并同时创建数据管理界面。
 *
 * 一个典型的用法如下例所示：

 * ```
    $dataProvider = new ActiveDataProvider([
        'query' => Post::find(),
        'pagination' => [
            'pageSize' => 20,
        ],
    ]);
    echo ListView::widget([
        'dataProvider' => $dataProvider,
        'itemView' => '_post', // 用于呈现每个数据项的视图的名称，或者用于呈现每个数据项的回调方法(例如一个匿名函数)。
    ]);
 * ```
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class ListView extends BaseListView
{
    /**
     * @var array the HTML attributes for the container of the rendering result of each data model.
     * The "tag" element specifies the tag name of the container element and defaults to "div".
     * If "tag" is false, it means no container element will be rendered.
     * @see \yii\helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $itemOptions = [];
    /**
     * 用于呈现每个数据项的视图的名称，或者用于呈现每个数据项的回调方法(例如一个匿名函数)。
     * 如果它指定了一个视图名，那么在视图中可以看到以下变量:
     * - $model 当前的数据模型 $model
     * - $key：混合类型，键的值与数据项相关联。
       - $index：整型，是由数据提供者返回的数组中以0起始的数据项的索引。
       - $widget：类型是ListView，是小部件的实例。
     *
     * 注意，视图名称通过[[view]]对象的当前上下文解析到视图文件中
     * @var string|callable the name of the view for rendering each data item, or a callback (e.g. an anonymous function) for rendering each data item.
     * If it specifies a view name, the following variables will be available in the view:
     *
     * - `$model`: mixed, the data model
     * - `$key`: mixed, the key value associated with the data item
     * - `$index`: integer, the zero-based index of the data item in the items array returned by [[dataProvider]].
     * - `$widget`: ListView, this widget instance
     *
     * Note that the view name is resolved into the view file by the current context of the [[view]] object.
     *
     * If this property is specified as a callback, it should have the following signature:
     * 如果该属性被指定为回调函数，那么它应该具有以下参数：
     * ```php
     * function ($model, $key, $index, $widget)
     * ```
     */
    public $itemView;
    /**
     * 假如你需要传递附加数据到每一个视图中，你可以像下面这样用 $viewParams 属性传递键值对：
     * echo ListView::widget([
            'dataProvider' => $dataProvider,
            'itemView' => '_post',
            'viewParams' => [
                'fullView' => true,
                'context' => 'main-page',
                // ...
            ],
        ]);
     * 在视图中，上述这些附加数据也是可以作为变量来使用的。
     * @var array additional parameters to be passed to [[itemView]] when it is being rendered.
     * This property is used only when [[itemView]] is a string representing a view name.
     */
    public $viewParams = [];
    /**
     * 在任何两个连续项之间显示的HTML代码
     * @var string the HTML code to be displayed between any two consecutive items.
     */
    public $separator = "\n";
    /**
     * 列表视图的容器的HTML属性标签
     * @var array the HTML attributes for the container tag of the list view.
     * The "tag" element specifies the tag name of the container element and defaults to "div".
     * @see \yii\helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $options = ['class' => 'list-view'];


    /**
     * Renders all data models.
     * @return string the rendering result
     */
    public function renderItems()
    {
        $models = $this->dataProvider->getModels();
        $keys = $this->dataProvider->getKeys();
        $rows = [];
        foreach (array_values($models) as $index => $model) {
            $rows[] = $this->renderItem($model, $keys[$index], $index);
        }

        return implode($this->separator, $rows);
    }

    /**
     * Renders a single data model.
     * @param mixed $model the data model to be rendered
     * @param mixed $key the key value associated with the data model
     * @param integer $index the zero-based index of the data model in the model array returned by [[dataProvider]].
     * @return string the rendering result
     */
    public function renderItem($model, $key, $index)
    {
        if ($this->itemView === null) {
            $content = $key;
        } elseif (is_string($this->itemView)) {
            $content = $this->getView()->render($this->itemView, array_merge([
                'model' => $model,
                'key' => $key,
                'index' => $index,
                'widget' => $this,
            ], $this->viewParams));
        } else {
            $content = call_user_func($this->itemView, $model, $key, $index, $this);
        }
        $options = $this->itemOptions;
        $tag = ArrayHelper::remove($options, 'tag', 'div');
        $options['data-key'] = is_array($key) ? json_encode($key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $key;

        return Html::tag($tag, $content, $options);
    }
}
