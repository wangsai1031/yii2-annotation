<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\data;

use Yii;
use yii\base\Object;
use yii\web\Link;
use yii\web\Linkable;
use yii\web\Request;
use yii\widgets\LinkPager;

/**
 * Pagination表示与数据项分页相关的信息
 * Pagination represents information relevant to pagination of data items.
 *
 * When data needs to be rendered in multiple pages, Pagination can be used to
 * represent information such as [[totalCount|total item count]], [[pageSize|page size]],
 * [[page|current page]], etc. These information can be passed to [[\yii\widgets\LinkPager|pagers]]
 * to render pagination buttons or links.
 *
 * The following example shows how to create a pagination object and feed it
 * to a pager.
 *
 * Controller action:
 *
 * ```php
 * function actionIndex()
 * {
 *     $query = Article::find()->where(['status' => 1]);
 *     $countQuery = clone $query;
 *     $pages = new Pagination(['totalCount' => $countQuery->count()]);
 *     $models = $query->offset($pages->offset)
 *         ->limit($pages->limit)
 *         ->all();
 *
 *     return $this->render('index', [
 *          'models' => $models,
 *          'pages' => $pages,
 *     ]);
 * }
 * ```
 *
 * View:
 *
 * ```php
 * foreach ($models as $model) {
 *     // display $model here
 * }
 *
 * // display pagination
 * echo LinkPager::widget([
 *     'pagination' => $pages,
 * ]);
 * ```
 * @see LinkPager
 *
 * @property integer $limit The limit of the data. This may be used to set the LIMIT value for a SQL statement
 * for fetching the current page of data. Note that if the page size is infinite, a value -1 will be returned.
 * This property is read-only.
 * @property array $links The links for navigational purpose. The array keys specify the purpose of the links
 * (e.g. [[LINK_FIRST]]), and the array values are the corresponding URLs. This property is read-only.
 * @property integer $offset The offset of the data. This may be used to set the OFFSET value for a SQL
 * statement for fetching the current page of data. This property is read-only.
 * @property integer $page The zero-based current page number.
 * @property integer $pageCount Number of pages. This property is read-only.
 * @property integer $pageSize The number of items per page. If it is less than 1, it means the page size is
 * infinite, and thus a single page contains all items.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Pagination extends Object implements Linkable
{
    const LINK_NEXT = 'next';
    const LINK_PREV = 'prev';
    const LINK_FIRST = 'first';
    const LINK_LAST = 'last';

    /**
     * 存储当前页面页码的参数的名称
     * @var string name of the parameter storing the current page index.
     * @see params
     */
    public $pageParam = 'page';
    /**
     * 存储每页数据条数的参数的名称
     * @var string name of the parameter storing the page size.
     * @see params
     */
    public $pageSizeParam = 'per-page';
    /**
     * 是否总是在由createUrl()创建URL时添加页面参数
     * 如果设置为false并且page为0，则页面参数将不会放入URL中
     * @var boolean whether to always have the page parameter in the URL created by [[createUrl()]].
     * If false and [[page]] is 0, the page parameter will not be put in the URL.
     */
    public $forcePageParam = true;
    /**
     * 用于显示分页内容的控制器动作的路径
     * 如果不设置，则意味着使用当前请求的路由
     *
     *  $pagination->route = 'article/index';

        // 显示: /index.php?r=article%2Findex&page=100
        echo $pagination->createUrl(100);
     *
     * @var string the route of the controller action for displaying the paged contents.
     * If not set, it means using the currently requested route.
     */
    public $route;
    /**
     * 参数(name => value) 用于获取当前页面编号，并创建新的分页url
     * 如果不设置，则将使用$GET的参数
     * @var array parameters (name => value) that should be used to obtain the current page number and to create new pagination URLs.
     * If not set, all parameters from $_GET will be used instead.
     *
     * 为了将 锚点 加入到链接中，可以使用 array_merge($_GET, ['#' => 'my-hash'])
     * In order to add hash to all links use `array_merge($_GET, ['#' => 'my-hash'])`.
     *
     * 由[[pageParam]]索引的数组元素被认为是当前的页码(默认值为0)
     * 由[[pageSizeParam]]索引的元素被视为页面大小（每页显示条数）(默认为defaultPageSize)
     * The array element indexed by [[pageParam]] is considered to be the current page number (defaults to 0);
     * while the element indexed by [[pageSizeParam]] is treated as the page size (defaults to [[defaultPageSize]]).
     */
    public $params;
    /**
     * 用于创建分页URL的URL管理器
     * 如果没有设置,将使用“urlManager”应用程序组件。
     * @var \yii\web\UrlManager the URL manager used for creating pagination URLs. If not set,
     * the "urlManager" application component will be used.
     */
    public $urlManager;
    /**
     * 是否检查页面是否在有效范围内
     * @var boolean whether to check if [[page]] is within valid range.
     * When this property is true, the value of [[page]] will always be between 0 and ([[pageCount]]-1).
     * Because [[pageCount]] relies on the correct value of [[totalCount]] which may not be available
     * in some cases (e.g. MongoDB), you may want to set this property to be false to disable the page
     * number validation. By doing so, [[page]] will return the value indexed by [[pageParam]] in [[params]].
     */
    public $validatePage = true;
    /**
     * 数据总条数
     * @var integer total number of items.
     */
    public $totalCount = 0;
    /**
     * 默认每页显示条数
     * @var integer the default page size. This property will be returned by [[pageSize]] when page size
     * cannot be determined by [[pageSizeParam]] from [[params]].
     */
    public $defaultPageSize = 20;
    /**
     * 页面大小限制
     * 如果这是false，则意味着pageSize应该总是defaultPageSize的值
     * @var array|false the page size limits. The first array element stands for the minimal page size, and the second
     * the maximal page size. If this is false, it means [[pageSize]] should always return the value of [[defaultPageSize]].
     */
    public $pageSizeLimit = [1, 50];

    /**
     * 每个页面上的数据数量
     * @var integer number of items on each page.
     * If it is less than 1, it means the page size is infinite, and thus a single page contains all items.
     */
    private $_pageSize;


    /**
     * 总页数
     * @return integer number of pages
     */
    public function getPageCount()
    {
        $pageSize = $this->getPageSize();
        if ($pageSize < 1) {
            return $this->totalCount > 0 ? 1 : 0;
        } else {
            $totalCount = $this->totalCount < 0 ? 0 : (int) $this->totalCount;

            return (int) (($totalCount + $pageSize - 1) / $pageSize);
        }
    }

    private $_page;

    /**
     * 返回基于零的当前页码
     * Returns the zero-based current page number.
     * 是否根据页面大小和数据条数数重新计算当前页面
     * @param boolean $recalculate whether to recalculate the current page based on the page size and item count.
     * @return integer the zero-based current page number.
     */
    public function getPage($recalculate = false)
    {
        if ($this->_page === null || $recalculate) {
            $page = (int) $this->getQueryParam($this->pageParam, 1) - 1;
            $this->setPage($page, true);
        }

        return $this->_page;
    }

    /**
     * 设置当前页码
     * Sets the current page number.
     * @param integer $value the zero-based index of the current page.
     * @param boolean $validatePage whether to validate the page number. Note that in order
     * to validate the page number, both [[validatePage]] and this parameter must be true.
     */
    public function setPage($value, $validatePage = false)
    {
        if ($value === null) {
            $this->_page = null;
        } else {
            $value = (int) $value;
            if ($validatePage && $this->validatePage) {
                $pageCount = $this->getPageCount();
                if ($value >= $pageCount) {
                    $value = $pageCount - 1;
                }
            }
            if ($value < 0) {
                $value = 0;
            }
            $this->_page = $value;
        }
    }

    /**
     * 返回每个页面的数据条数
     *  默认情况下，该方法将尝试使用params中的pageSizeParam来确定页面大小。
        如果不能以这种方式确定页面大小，则将返回defaultPageSize。
     *
     * Returns the number of items per page.
     * By default, this method will try to determine the page size by [[pageSizeParam]] in [[params]].
     * If the page size cannot be determined this way, [[defaultPageSize]] will be returned.
     * @return integer the number of items per page. If it is less than 1, it means the page size is infinite,
     * and thus a single page contains all items.
     * @see pageSizeLimit
     */
    public function getPageSize()
    {
        if ($this->_pageSize === null) {
            if (empty($this->pageSizeLimit)) {
                $pageSize = $this->defaultPageSize;
                $this->setPageSize($pageSize);
            } else {
                $pageSize = (int) $this->getQueryParam($this->pageSizeParam, $this->defaultPageSize);
                $this->setPageSize($pageSize, true);
            }
        }

        return $this->_pageSize;
    }

    /**
     * 设置每页数据条数
     * @param integer $value the number of items per page.
     * @param boolean $validatePageSize whether to validate page size.
     */
    public function setPageSize($value, $validatePageSize = false)
    {
        if ($value === null) {
            $this->_pageSize = null;
        } else {
            $value = (int) $value;
            if ($validatePageSize && isset($this->pageSizeLimit[0], $this->pageSizeLimit[1]) && count($this->pageSizeLimit) === 2) {
                if ($value < $this->pageSizeLimit[0]) {
                    $value = $this->pageSizeLimit[0];
                } elseif ($value > $this->pageSizeLimit[1]) {
                    $value = $this->pageSizeLimit[1];
                }
            }
            $this->_pageSize = $value;
        }
    }

    /**
     * 创建适合分页的URL，并使用指定的页码
     * Creates the URL suitable for pagination with the specified page number.
     * This method is mainly called by pagers when creating URLs used to perform pagination.
     * @param integer $page the zero-based page number that the URL should point to.
     * @param integer $pageSize the number of items on each page. If not set, the value of [[pageSize]] will be used.
     * @param boolean $absolute whether to create an absolute URL. Defaults to `false`.
     * @return string the created URL
     * @see params
     * @see forcePageParam
     */
    public function createUrl($page, $pageSize = null, $absolute = false)
    {
        $page = (int) $page;
        $pageSize = (int) $pageSize;
        if (($params = $this->params) === null) {
            $request = Yii::$app->getRequest();
            $params = $request instanceof Request ? $request->getQueryParams() : [];
        }
        if ($page > 0 || $page >= 0 && $this->forcePageParam) {
            $params[$this->pageParam] = $page + 1;
        } else {
            unset($params[$this->pageParam]);
        }
        if ($pageSize <= 0) {
            $pageSize = $this->getPageSize();
        }
        if ($pageSize != $this->defaultPageSize) {
            $params[$this->pageSizeParam] = $pageSize;
        } else {
            unset($params[$this->pageSizeParam]);
        }
        $params[0] = $this->route === null ? Yii::$app->controller->getRoute() : $this->route;
        $urlManager = $this->urlManager === null ? Yii::$app->getUrlManager() : $this->urlManager;
        if ($absolute) {
            return $urlManager->createAbsoluteUrl($params);
        } else {
            return $urlManager->createUrl($params);
        }
    }

    /**
     * 数据的偏移量。
     * 这可以用来设置用于获取当前数据页的SQL语句的偏移值
     * @return integer the offset of the data. This may be used to set the
     * OFFSET value for a SQL statement for fetching the current page of data.
     */
    public function getOffset()
    {
        $pageSize = $this->getPageSize();

        return $pageSize < 1 ? 0 : $this->getPage() * $pageSize;
    }

    /**
     * @return integer the limit of the data. This may be used to set the
     * LIMIT value for a SQL statement for fetching the current page of data.
     * Note that if the page size is infinite, a value -1 will be returned.
     */
    public function getLimit()
    {
        $pageSize = $this->getPageSize();

        return $pageSize < 1 ? -1 : $pageSize;
    }

    /**
     * 返回一组链接，用于导航到第一个、最后一个、下一个和上一个页面
     * Returns a whole set of links for navigating to the first, last, next and previous pages.
     * 生成的url是否应该是绝对的
     * @param boolean $absolute whether the generated URLs should be absolute.
     * @return array the links for navigational purpose. The array keys specify the purpose of the links (e.g. [[LINK_FIRST]]),
     * and the array values are the corresponding URLs.
     */
    public function getLinks($absolute = false)
    {
        $currentPage = $this->getPage();
        $pageCount = $this->getPageCount();
        $links = [
            Link::REL_SELF => $this->createUrl($currentPage, null, $absolute),
        ];
        if ($currentPage > 0) {
            $links[self::LINK_FIRST] = $this->createUrl(0, null, $absolute);
            $links[self::LINK_PREV] = $this->createUrl($currentPage - 1, null, $absolute);
        }
        if ($currentPage < $pageCount - 1) {
            $links[self::LINK_NEXT] = $this->createUrl($currentPage + 1, null, $absolute);
            $links[self::LINK_LAST] = $this->createUrl($pageCount - 1, null, $absolute);
        }

        return $links;
    }

    /**
     * 返回指定的查询参数的值
     * Returns the value of the specified query parameter.
     * This method returns the named parameter value from [[params]]. Null is returned if the value does not exist.
     * @param string $name the parameter name
     * @param string $defaultValue the value to be returned when the specified parameter does not exist in [[params]].
     * @return string the parameter value
     */
    protected function getQueryParam($name, $defaultValue = null)
    {
        if (($params = $this->params) === null) {
            $request = Yii::$app->getRequest();
            $params = $request instanceof Request ? $request->getQueryParams() : [];
        }

        return isset($params[$name]) && is_scalar($params[$name]) ? $params[$name] : $defaultValue;
    }
}
