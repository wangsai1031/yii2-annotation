<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\rbac;

use yii\base\Object;

/**
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Item extends Object
{
    const TYPE_ROLE = 1;
    const TYPE_PERMISSION = 2;

    /**
     * 项目的类型。应该是TYPE_ROLE类型或TYPE_PERMISSION类型
     * @var integer the type of the item. This should be either [[TYPE_ROLE]] or [[TYPE_PERMISSION]].
     */
    public $type;
    /**
     * 名称。这必须是全局唯一的
     * @var string the name of the item. This must be globally unique.
     */
    public $name;
    /**
     * 描述
     * @var string the item description
     */
    public $description;
    /**
     * 与该条目相关的规则的名称
     * @var string name of the rule associated with this item
     */
    public $ruleName;
    /**
     * 与此条目相关的附加数据
     * @var mixed the additional data associated with this item
     */
    public $data;
    /**
     * 表示的条目创建时间的UNIX 时间戳
     * @var integer UNIX timestamp representing the item creation time
     */
    public $createdAt;
    /**
     * 代表条目更新时间的UNIX时间戳
     * @var integer UNIX timestamp representing the item updating time
     */
    public $updatedAt;
}
