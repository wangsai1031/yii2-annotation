<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\data;

use Yii;
use yii\base\BaseObject;
use yii\base\InvalidConfigException;
use yii\helpers\Html;
use yii\helpers\Inflector;
use yii\web\Request;

/**
 * Sort代表与排序相关的信息
 * Sort represents information relevant to sorting.
 *
 * 当需要根据一个或多个属性对数据进行排序时，我们可以使用Sort来表示排序信息，并生成适当的超链接，从而完成排序操作。
 * When data needs to be sorted according to one or several attributes,
 * we can use Sort to represent the sorting information and generate
 * appropriate hyperlinks that can lead to sort actions.
 *
 * A typical usage example is as follows,
 * 典型的用法示例如下:
 *
 * ```php
 * public function actionIndex()
 * {
 *     $sort = new Sort([
 *         'attributes' => [
 *             'age',
 *             'name' => [
 *                 'asc' => ['first_name' => SORT_ASC, 'last_name' => SORT_ASC],
 *                 'desc' => ['first_name' => SORT_DESC, 'last_name' => SORT_DESC],
 *                 'default' => SORT_DESC,
 *                 'label' => 'Name',
 *             ],
 *         ],
 *     ]);
 *
 *     $models = Article::find()
 *         ->where(['status' => 1])
 *         ->orderBy($sort->orders)
 *         ->all();
 *
 *     return $this->render('index', [
 *          'models' => $models,
 *          'sort' => $sort,
 *     ]);
 * }
 * ```
 *
 * View:
 *
 * ```php
 * // display links leading to sort actions
 * echo $sort->link('name') . ' | ' . $sort->link('age');
 *
 * foreach ($models as $model) {
 *     // display $model here
 * }
 * ```
 * 在上面，我们声明了两个支持排序的属性[[attributes]]:'age','name'。
 * 我们将排序信息传递给Article query，以便查询结果按照Sort对象指定的顺序进行排序。
 * 在这个视图中，我们展示了两个超链接，它可以引导页面使用相应的属性进行排序。
 * In the above, we declare two [[attributes]] that support sorting: name and age.
 * We pass the sort information to the Article query so that the query results are sorted by the orders specified by the Sort object.
 * In the view, we show two hyperlinks that can lead to pages with the data sorted by the corresponding attributes.
 *
 * In the above, we declare two [[attributes]] that support sorting: `name` and `age`.
 * We pass the sort information to the Article query so that the query results are
 * sorted by the orders specified by the Sort object. In the view, we show two hyperlinks
 * that can lead to pages with the data sorted by the corresponding attributes.
 *
 * For more details and usage information on Sort, see the [guide article on sorting](guide:output-sorting).
 *
 * $attributeOrders 由属性名索引的排序方向。排序方向可以是`SORT_ASC`：用于升序或`SORT_DESC`：降序。
 * @property array $attributeOrders Sort directions indexed by attribute names. Sort direction can be either
 * `SORT_ASC` for ascending order or `SORT_DESC` for descending order. Note that the type of this property
 * differs in getter and setter. See [[getAttributeOrders()]] and [[setAttributeOrders()]] for details.
 * 
 * $orders 列(键)及其对应的排序方向(值)，这可以传递给 [[\yii\db\Query::orderBy()]]来构造一个db查询。这个属性是只读的。
 * @property array $orders The columns (keys) and their corresponding sort directions (values). This can be
 * passed to [[\yii\db\Query::orderBy()]] to construct a DB query. This property is read-only.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Sort extends BaseObject
{
    /**
     * 排序是否可以同时应用于多个属性
     * 默认是false,也就是说，每次数据只能由一个属性来排序
     * @var bool whether the sorting can be applied to multiple attributes simultaneously.
     * Defaults to `false`, which means each time the data can only be sorted by one attribute.
     */
    public $enableMultiSort = false;
    /**
     * 可以排序的属性列表,
     * 一个属性可以就是简单的一个 model attribute，也可以是结合了多个 model 属性或者 DB 列的复合属性
     * 它的语法可以使用以下示例进行描述
     * 
     * @var array list of attributes that are allowed to be sorted. Its syntax can be
     * described using the following example:
     *
     * ```php
     * [
     *     'age',
     *     'name' => [
     *         'asc' => ['first_name' => SORT_ASC, 'last_name' => SORT_ASC],
     *         'desc' => ['first_name' => SORT_DESC, 'last_name' => SORT_DESC],
     *         'default' => SORT_DESC,
     *         'label' => 'Name',
     *     ],
     * ]
     * ```
     *
     * 上述例子中，为 Sort 对象声明了两个属性： age 和 name。
     * "age"属性是一个简单的属性，它等价于以下内容:
     * In the above, two attributes are declared: `age` and `name`. The `age` attribute is
     * a simple attribute which is equivalent to the following:
     *
     * ```php
     * 'age' => [
     *     'asc' => ['age' => SORT_ASC],
     *     'desc' => ['age' => SORT_DESC],
     *     'default' => SORT_ASC,
     *     'label' => Inflector::camel2words('age'),
     * ]
     * ```
     *
     * Since 2.0.12 particular sort direction can be also specified as direct sort expression, like following:
     *
     * ```php
     * 'name' => [
     *     'asc' => '[[last_name]] ASC NULLS FIRST', // PostgreSQL specific feature
     *     'desc' => '[[last_name]] DESC NULLS LAST',
     * ]
     * ```
     * name 属性是由 Article 的 firsr_name 和 last_name 定义的一个复合属性。
     * 使用下面的数组结构来对它进行声明：
     *
     * - asc 和 desc 元素指定了如何按照该属性进行升序和降序的排序。
     *      它们的值代表数据真正地应该按照什么列和方向进行排序。
     *      你可以指定一列或多列来指出到底是简单排序还是多重排序。
     * - default 元素指定了当一次请求时，属性应该按照什么方向来进行排序。
     *      它默认为升序方向，意味着如果之前没有进行排序，'
     *      并且 你请求按照该属性进行排序，那么数据将按照该属性来进行升序排序。
     * - label 元素指定了调用 yii\data\Sort::link() 来创建一个排序链接时应该使用什么标签，
     *      如果不设置，将调用 yii\helpers\Inflector::camel2words() 来通过属性名生成一个标签。
     *      注意，它并不是 HTML编码的。
     * 
     * The `name` attribute is a composite attribute:
     *
     * - The `name` key represents the attribute name which will appear in the URLs leading
     *   to sort actions.
     * - The `asc` and `desc` elements specify how to sort by the attribute in ascending
     *   and descending orders, respectively. Their values represent the actual columns and
     *   the directions by which the data should be sorted by.
     * - The `default` element specifies by which direction the attribute should be sorted
     *   if it is not currently sorted (the default value is ascending order).
     * - The `label` element specifies what label should be used when calling [[link()]] to create
     *   a sort link. If not set, [[Inflector::camel2words()]] will be called to get a label.
     *   Note that it will not be HTML-encoded.
     *
     * Note that if the Sort object is already created, you can only use the full format
     * to configure every attribute. Each attribute must include these elements: `asc` and `desc`.
     */
    public $attributes = [];
    /**
     * 指定要排序的属性的参数的名称
     * @var string the name of the parameter that specifies which attributes to be sorted
     * in which direction. Defaults to `sort`.
     * @see params
     */
    public $sortParam = 'sort';
    /**
     * 当前请求没有指定任何顺序时应该使用的顺序
     * @var array the order that should be used when the current request does not specify any order.
     * The array keys are attribute names and the array values are the corresponding sort directions. For example,
     *
     * ```php
     * [
     *     'name' => SORT_ASC,
     *     'created_at' => SORT_DESC,
     * ]
     * ```
     *
     * @see attributeOrders
     */
    public $defaultOrder;
    /**
     *  // 指定被创建的 URL 应该使用的路由
        // 如果你没有指定，将使用当前被请求的路由
        $sort->route = 'article/index';

        // 显示链接，链接分别指向以 name 和 age 进行排序
        echo $sort->link('name') . ' | ' . $sort->link('age');

        // 显示: /index.php?r=article%2Findex&sort=age
        echo $sort->createUrl('age');
     *
     * @var string the route of the controller action for displaying the sorted contents.
     * If not set, it means using the currently requested route.
     */
    public $route;
    /**
     * 来分隔需要排序的不同属性
     * @var string the character used to separate different attributes that need to be sorted by.
     */
    public $separator = ',';
    /**
     * @var array parameters (name => value) that should be used to obtain the current sort directions
     * and to create new sort URLs. If not set, `$_GET` will be used instead.
     *
     * In order to add hash to all links use `array_merge($_GET, ['#' => 'my-hash'])`.
     *
     * The array element indexed by [[sortParam]] is considered to be the current sort directions.
     * If the element does not exist, the [[defaultOrder|default order]] will be used.
     *
     * @see sortParam
     * @see defaultOrder
     */
    public $params;
    /**
     * 用于创建排序URL的URL管理器。如果不设置，将使用“urlManager”应用程序组件。
     * @var \yii\web\UrlManager the URL manager used for creating sort URLs. If not set,
     * the `urlManager` application component will be used.
     */
    public $urlManager;


    /**
     * Normalizes the [[attributes]] property.
     */
    public function init()
    {
        $attributes = [];
        foreach ($this->attributes as $name => $attribute) {
            if (!is_array($attribute)) {
                $attributes[$attribute] = [
                    'asc' => [$attribute => SORT_ASC],
                    'desc' => [$attribute => SORT_DESC],
                ];
            } elseif (!isset($attribute['asc'], $attribute['desc'])) {
                $attributes[$name] = array_merge([
                    'asc' => [$name => SORT_ASC],
                    'desc' => [$name => SORT_DESC],
                ], $attribute);
            } else {
                $attributes[$name] = $attribute;
            }
        }
        $this->attributes = $attributes;
    }

    /**
     * 按照低级列的方式给出排序方向。
     *
     * Info: 你可以将 yii\data\Sort::$orders 的值直接提供给数据库查询来构建其 ORDER BY 子句。
     * 不要使用 yii\data\Sort::$attributeOrders ，因为一些属性可能是复合的，是不能被数据库查询识别的。
     *
     * Returns the columns and their corresponding sort directions.
     * @param bool $recalculate whether to recalculate the sort directions
     * @return array the columns (keys) and their corresponding sort directions (values).
     * This can be passed to [[\yii\db\Query::orderBy()]] to construct a DB query.
     */
    public function getOrders($recalculate = false)
    {
        $attributeOrders = $this->getAttributeOrders($recalculate);
        $orders = [];
        foreach ($attributeOrders as $attribute => $direction) {
            $definition = $this->attributes[$attribute];
            $columns = $definition[$direction === SORT_ASC ? 'asc' : 'desc'];
            if (is_array($columns) || $columns instanceof \Traversable) {
                foreach ($columns as $name => $dir) {
                    $orders[$name] = $dir;
                }
            } else {
                $orders[] = $columns;
            }
        }

        return $orders;
    }

    /**
     * 当前请求的排序顺序，由[[getAttributeOrders]]计算。
     * @var array the currently requested sort order as computed by [[getAttributeOrders]].
     */
    private $_attributeOrders;

    /**
     * 给出每个属性当前设置的排序方向
     *
     * Info: 你可以将 yii\data\Sort::$orders 的值直接提供给数据库查询来构建其 ORDER BY 子句。
     * 不要使用 yii\data\Sort::$attributeOrders ，因为一些属性可能是复合的，是不能被数据库查询识别的。
     *
     * Returns the currently requested sort information.
     * 
     * 是否重新计算排序方向
     * @param bool $recalculate whether to recalculate the sort directions
     * @return array sort directions indexed by attribute names.
     * Sort direction can be either `SORT_ASC` for ascending order or
     * `SORT_DESC` for descending order.
     */
    public function getAttributeOrders($recalculate = false)
    {
        if ($this->_attributeOrders === null || $recalculate) {
            $this->_attributeOrders = [];
            if (($params = $this->params) === null) {
                $request = Yii::$app->getRequest();
                $params = $request instanceof Request ? $request->getQueryParams() : [];
            }
            if (isset($params[$this->sortParam])) {
                $attributes = $this->parseSortParam($params[$this->sortParam]);
                foreach ($attributes as $attribute) {
                    $descending = false;
                    if (strncmp($attribute, '-', 1) === 0) {
                        $descending = true;
                        $attribute = substr($attribute, 1);
                    }

                    if (isset($this->attributes[$attribute])) {
                        $this->_attributeOrders[$attribute] = $descending ? SORT_DESC : SORT_ASC;
                        if (!$this->enableMultiSort) {
                            return $this->_attributeOrders;
                        }
                    }
                }
            }
            if (empty($this->_attributeOrders) && is_array($this->defaultOrder)) {
                $this->_attributeOrders = $this->defaultOrder;
            }
        }

        return $this->_attributeOrders;
    }

    /**
     * Parses the value of [[sortParam]] into an array of sort attributes.
     *
     * The format must be the attribute name only for ascending
     * or the attribute name prefixed with `-` for descending.
     *
     * For example the following return value will result in ascending sort by
     * `category` and descending sort by `created_at`:
     *
     * ```php
     * [
     *     'category',
     *     '-created_at'
     * ]
     * ```
     *
     * @param string $param the value of the [[sortParam]].
     * @return array the valid sort attributes.
     * @since 2.0.12
     * @see $separator for the attribute name separator.
     * @see $sortParam
     */
    protected function parseSortParam($param)
    {
        return is_scalar($param) ? explode($this->separator, $param) : [];
    }

    /**
     * Sets up the currently sort information.
     * @param array|null $attributeOrders sort directions indexed by attribute names.
     * Sort direction can be either `SORT_ASC` for ascending order or
     * `SORT_DESC` for descending order.
     * @param bool $validate whether to validate given attribute orders against [[attributes]] and [[enableMultiSort]].
     * If validation is enabled incorrect entries will be removed.
     * @since 2.0.10
     */
    public function setAttributeOrders($attributeOrders, $validate = true)
    {
        if ($attributeOrders === null || !$validate) {
            $this->_attributeOrders = $attributeOrders;
        } else {
            $this->_attributeOrders = [];
            foreach ($attributeOrders as $attribute => $order) {
                if (isset($this->attributes[$attribute])) {
                    $this->_attributeOrders[$attribute] = $order;
                    if (!$this->enableMultiSort) {
                        break;
                    }
                }
            }
        }
    }

    /**
     * 返回当前请求中指定属性的排序方向。
     * Returns the sort direction of the specified attribute in the current request.
     * @param string $attribute the attribute name
     * 属性的排序方向。可以是升序`SORT_ASC`或降序`SORT_DESC`。
     * 如果属性无效或不需要排序，则返回Null。
     * @return bool|null Sort direction of the attribute. Can be either `SORT_ASC`
     * for ascending order or `SORT_DESC` for descending order. Null is returned
     * if the attribute is invalid or does not need to be sorted.
     */
    public function getAttributeOrder($attribute)
    {
        $orders = $this->getAttributeOrders();

        return isset($orders[$attribute]) ? $orders[$attribute] : null;
    }

    /**
     * 生成链接到排序操作的超链接，按指定的属性进行排序
     * 基于排序方向，生成的超链接的CSS类将被附加到“asc”或“desc”。
     *
     *  // 指定被创建的 URL 应该使用的路由
        // 如果你没有指定，将使用当前被请求的路由
        $sort->route = 'article/index';

        // 显示链接，链接分别指向以 name 和 age 进行排序
        echo $sort->link('name') . ' | ' . $sort->link('age');
     *
     * Generates a hyperlink that links to the sort action to sort by the specified attribute.
     * Based on the sort direction, the CSS class of the generated hyperlink will be appended
     * with "asc" or "desc".
     * $attribute 数据应该由哪个属性名称来排序
     * @param string $attribute the attribute name by which the data should be sorted by.
     * $options 超链接标签的附加HTML属性
     * @param array $options additional HTML attributes for the hyperlink tag.
     * 有一个特殊的属性`label`，它将被用作超链接的标签。
     * 如果没有设置，将使用在属性中定义的标签。
     * 如果没有定义标签，将会调用[[\yii\helpers\Inflector::camel2words()]]来获得标签。
     * 注意，它不会经过html编码
     * There is one special attribute `label` which will be used as the label of the hyperlink.
     * If this is not set, the label defined in [[attributes]] will be used.
     * If no label is defined, [[\yii\helpers\Inflector::camel2words()]] will be called to get a label.
     * Note that it will not be HTML-encoded.
     * @return string the generated hyperlink
     * @throws InvalidConfigException if the attribute is unknown
     */
    public function link($attribute, $options = [])
    {
        // 返回当前请求中指定属性的排序方向。
        if (($direction = $this->getAttributeOrder($attribute)) !== null) {
            $class = $direction === SORT_DESC ? 'desc' : 'asc';
            if (isset($options['class'])) {
                $options['class'] .= ' ' . $class;
            } else {
                $options['class'] = $class;
            }
        }

        $url = $this->createUrl($attribute);
        $options['data-sort'] = $this->createSortParam($attribute);

        if (isset($options['label'])) {
            $label = $options['label'];
            unset($options['label']);
        } else {
            if (isset($this->attributes[$attribute]['label'])) {
                $label = $this->attributes[$attribute]['label'];
            } else {
                $label = Inflector::camel2words($attribute);
            }
        }

        return Html::a($label, $url, $options);
    }

    /**
     * Creates a URL for sorting the data by the specified attribute.
     * This method will consider the current sorting status given by [[attributeOrders]].
     * For example, if the current page already sorts the data by the specified attribute in ascending order,
     * then the URL created will lead to a page that sorts the data by the specified attribute in descending order.
     * @param string $attribute the attribute name
     * @param bool $absolute whether to create an absolute URL. Defaults to `false`.
     * @return string the URL for sorting. False if the attribute is invalid.
     * @throws InvalidConfigException if the attribute is unknown
     * @see attributeOrders
     * @see params
     */
    public function createUrl($attribute, $absolute = false)
    {
        if (($params = $this->params) === null) {
            $request = Yii::$app->getRequest();
            $params = $request instanceof Request ? $request->getQueryParams() : [];
        }
        $params[$this->sortParam] = $this->createSortParam($attribute);
        $params[0] = $this->route === null ? Yii::$app->controller->getRoute() : $this->route;
        $urlManager = $this->urlManager === null ? Yii::$app->getUrlManager() : $this->urlManager;
        if ($absolute) {
            return $urlManager->createAbsoluteUrl($params);
        }

        return $urlManager->createUrl($params);
    }

    /**
     * Creates the sort variable for the specified attribute.
     * The newly created sort variable can be used to create a URL that will lead to
     * sorting by the specified attribute.
     * @param string $attribute the attribute name
     * @return string the value of the sort variable
     * @throws InvalidConfigException if the specified attribute is not defined in [[attributes]]
     */
    public function createSortParam($attribute)
    {
        if (!isset($this->attributes[$attribute])) {
            throw new InvalidConfigException("Unknown attribute: $attribute");
        }
        $definition = $this->attributes[$attribute];
        $directions = $this->getAttributeOrders();
        if (isset($directions[$attribute])) {
            $direction = $directions[$attribute] === SORT_DESC ? SORT_ASC : SORT_DESC;
            unset($directions[$attribute]);
        } else {
            $direction = isset($definition['default']) ? $definition['default'] : SORT_ASC;
        }

        if ($this->enableMultiSort) {
            $directions = array_merge([$attribute => $direction], $directions);
        } else {
            $directions = [$attribute => $direction];
        }

        $sorts = [];
        foreach ($directions as $attribute => $direction) {
            $sorts[] = $direction === SORT_DESC ? '-' . $attribute : $attribute;
        }

        return implode($this->separator, $sorts);
    }

    /**
     * Returns a value indicating whether the sort definition supports sorting by the named attribute.
     * @param string $name the attribute name
     * @return bool whether the sort definition supports sorting by the named attribute.
     */
    public function hasAttribute($name)
    {
        return isset($this->attributes[$name]);
    }
}
