<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\db;

use yii\base\BaseObject;
use yii\helpers\StringHelper;

/**
 * 生成字段信息
 *
 * yii\db\ColumnSchema 保存了一个字段的各种相关信息，包括字段类型等。
 *
 * ColumnSchema class describes the metadata of a column in a database table.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class ColumnSchema extends BaseObject
{
    /**
     * @var string name of this column (without quotes).
     */
    public $name;
    /**
     * 这个列是否允许为空
     * @var bool whether this column can be null.
     */
    public $allowNull;
    /**
     * 这一列的抽象类型。
     * 可能的抽象类型包括：
     * char, string, text, boolean, smallint, integer, bigint, float,
     * decimal, datetime, timestamp, time, date, binary, and money.
     *
     * @var string abstract type of this column. Possible abstract types include:
     * char, string, text, boolean, smallint, integer, bigint, float, decimal, datetime,
     * timestamp, time, date, binary, and money.
     */
    public $type;
    /**
     * 这一列的PHP类型。
     * 可能的PHP类型包括：
     * `string`, `boolean`, `integer`, `double`.
     *
     * @var string the PHP type of this column. Possible PHP types include:
     * `string`, `boolean`, `integer`, `double`, `array`.
     */
    public $phpType;
    /**
     * 这一列的DB类型。可能的DB类型根据DBMS的类型而有所不同。
     * @var string the DB type of this column. Possible DB types vary according to the type of DBMS.
     */
    public $dbType;
    /**
     * 这一列的默认值
     * @var mixed default value of this column
     */
    public $defaultValue;
    /**
     * 枚举值。
     * 只有在将列声明为可枚举类型时才会设置这个。
     * @var array enumerable values. This is set only if the column is declared to be an enumerable type.
     */
    public $enumValues;
    /**
     * 列的显示大小。
     * @var int display size of the column.
     */
    public $size;
    /**
     * 列数据的精度，如果是数字的话。
     * @var int precision of the column data, if it is numeric.
     */
    public $precision;
    /**
     * 列数据的数值范围，如果是数字的话。
     * @var int scale of the column data, if it is numeric.
     */
    public $scale;
    /**
     * 这一列是否是主键
     * @var bool whether this column is a primary key
     */
    public $isPrimaryKey;
    /**
     * 这一列是否自动递增
     * @var bool whether this column is auto-incremental
     */
    public $autoIncrement = false;
    /**
     * 这一列是否是无符号。
     * 只有当类型[[type]]是`smallint`, `integer` or `bigint`时才有意义。
     * @var bool whether this column is unsigned. This is only meaningful
     * when [[type]] is `smallint`, `integer` or `bigint`.
     */
    public $unsigned;
    /**
     * 列的注释。不是所有的DBMS都支持此项。
     * @var string comment of this column. Not all DBMS support this.
     */
    public $comment;


    /**
     * 在从数据库中检索后，将输入值转换为[[phpType]]。
     * 如果该值为null或表达式[[Expression]]，则不会进行转换。
     *
     * Converts the input value according to [[phpType]] after retrieval from the database.
     * If the value is null or an [[Expression]], it will not be converted.
     * @param mixed $value input value
     * @return mixed converted value
     */
    public function phpTypecast($value)
    {
        return $this->typecast($value);
    }

    /**
     * 在db查询中根据[[type]] and [[dbType]]来转换输入值。
     * 如果该值为null或表达式，则不会进行转换。
     * Converts the input value according to [[type]] and [[dbType]] for use in a db query.
     * If the value is null or an [[Expression]], it will not be converted.
     * @param mixed $value input value
     * @return mixed converted value. This may also be an array containing the value as the first element
     * and the PDO type as the second element.
     */
    public function dbTypecast($value)
    {
        // the default implementation does the same as casting for PHP, but it should be possible
        // to override this with annotation of explicit PDO type.
        // 默认的实现与对PHP的转换相同，但是应该可以使用显式PDO类型的注释来覆盖这个方法。
        return $this->typecast($value);
    }

    /**
     * 该方法用于把 $value 转换成 php 变量，其中 $value 是PDO从数据库中读取的内容
     *
     * 主要参考的是字段的抽象类型 $type 和 PHP类型 $phpType
     * 如果该值为null或表达式[[Expression]]，则不会进行转换。
     *
     * Converts the input value according to [[phpType]] after retrieval from the database.
     * If the value is null or an [[Expression]], it will not be converted.
     * @param mixed $value input value
     * @return mixed converted value
     * @since 2.0.3
     */
    protected function typecast($value)
    {
        // 内容为空时，若不是字符串或二进制抽象类型，则 全部转换为 NULL。
        if ($value === ''
            && !in_array(
                $this->type,
                [
                    Schema::TYPE_TEXT,
                    Schema::TYPE_STRING,
                    Schema::TYPE_BINARY,
                    Schema::TYPE_CHAR
                ],
                true)
        ) {
            return null;
        }

        // 内容为null，或者 $value 的类型与PHP类型一致，或者 $value 是一个数据库表达式，
        // 那么可以直接返回
        if ($value === null
            || gettype($value) === $this->phpType
            || $value instanceof ExpressionInterface
            || $value instanceof Query
        ) {
            return $value;
        }

        if (is_array($value)
            && count($value) === 2
            && isset($value[1])
            && in_array($value[1], $this->getPdoParamTypes(), true)
        ) {
            return new PdoValue($value[0], $value[1]);
        }

        // 否则，需要根据PHP类型来完成类型转换
        switch ($this->phpType) {
            case 'resource':
            case 'string':
                if (is_resource($value)) {
                    return $value;
                }
                // 是 float 类型： '1.5' false, 1.5 true
                if (is_float($value)) {
                    // ensure type cast always has . as decimal separator in all locales
                    // 确保类型铸件总是有 '.' 作为所有地区的十进制分隔符
                    return StringHelper::floatToString($value);
                }
                return (string) $value;
            case 'integer':
                return (int) $value;
            case 'boolean':
                // 将0比特值视为false
                // treating a 0 bit value as false too
                // https://github.com/yiisoft/yii2/issues/9006
                return (bool) $value && $value !== "\0";
            case 'double':
                return (float) $value;
        }

        return $value;
    }

    /**
     * @return int[] array of numbers that represent possible PDO parameter types
     */
    private function getPdoParamTypes()
    {
        return [\PDO::PARAM_BOOL, \PDO::PARAM_INT, \PDO::PARAM_STR, \PDO::PARAM_LOB, \PDO::PARAM_NULL, \PDO::PARAM_STMT];
    }
}
