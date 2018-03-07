<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\widgets;

use Yii;
use yii\base\InvalidConfigException;
use yii\base\Widget;
use yii\data\Sort;
use yii\helpers\Html;

/**
 * linkSorter 呈现一个给定的排序定义的排序链接列表。
 * LinkSorter renders a list of sort links for the given sort definition.
 *
 * linkSorter 将为每一个在[[sort]]中声明的属性生成一个超链接。
 * LinkSorter will generate a hyperlink for every attribute declared in [[sort]].
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class LinkSorter extends Widget
{
    /**
     * Sort 类的定义
     * @var Sort the sort definition
     */
    public $sort;
    /**
     * 支持排序的属性列表。如果不设置，它将使用[[Sort::attributes]]来确定。
     * @var array list of the attributes that support sorting. If not set, it will be determined using [[Sort::attributes]].
     */
    public $attributes;
    /**
     * 排序容器标签的HTML属性
     * @var array HTML attributes for the sorter container tag.
     * @see \yii\helpers\Html::ul() for special attributes.
     * @see \yii\helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $options = ['class' => 'sorter'];
    /**
     * 在sorter容器标签中的链接的HTML属性，它被传递给Sort::link()。
     * @var array HTML attributes for the link in a sorter container tag which are passed to [[Sort::link()]].
     * @see \yii\helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     * @since 2.0.6
     */
    public $linkOptions = [];


    /**
     * 初始化
     * Initializes the sorter.
     */
    public function init()
    {
        if ($this->sort === null) {
            throw new InvalidConfigException('The "sort" property must be set.');
        }
    }

    /**
     * Executes the widget.
     * This method renders the sort links.
     *
     * 执行小部件
     * 这个方法呈现了排序链接
     */
    public function run()
    {
        echo $this->renderSortLinks();
    }

    /**
     * 呈现排序链接
     * Renders the sort links.
     * @return string the rendering result
     */
    protected function renderSortLinks()
    {
        /**
         * 如果 attributes 为空，则从 Sort 类 的 attributes 中获取
         * $this->sort->attributes
         * 'age',
         * 'name' => [
         *     'asc' => ['first_name' => SORT_ASC, 'last_name' => SORT_ASC],
         *     'desc' => ['first_name' => SORT_DESC, 'last_name' => SORT_DESC],
         *     'default' => SORT_DESC,
         *     'label' => 'Name',
         *  ],
         *
         * $attributes = ['age', 'name']
         */
        $attributes = empty($this->attributes) ? array_keys($this->sort->attributes) : $this->attributes;
        $links = [];
        foreach ($attributes as $name) {
            $links[] = $this->sort->link($name, $this->linkOptions);
        }

        return Html::ul($links, array_merge($this->options, ['encode' => false]));
    }
}
