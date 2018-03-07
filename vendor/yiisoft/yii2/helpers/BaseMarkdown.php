<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\helpers;

use Yii;
use yii\base\InvalidParamException;

/**
 * BaseMarkdown provides concrete implementation for [[Markdown]].
 *
 * Do not use BaseMarkdown. Use [[Markdown]] instead.
 *
 * @author Carsten Brandt <mail@cebe.cc>
 * @since 2.0
 */
class BaseMarkdown
{
    /**
     * 将markdown风格名称映射到对应的解析器类配置
     * @var array a map of markdown flavor names to corresponding parser class configurations.
     */
    public static $flavors = [
        'original' => [
            'class' => 'cebe\markdown\Markdown',
            'html5' => true,
        ],
        'gfm' => [
            'class' => 'cebe\markdown\GithubMarkdown',
            'html5' => true,
        ],
        'gfm-comment' => [
            'class' => 'cebe\markdown\GithubMarkdown',
            'html5' => true,
            'enableNewlines' => true,
        ],
        'extra' => [
            'class' => 'cebe\markdown\MarkdownExtra',
            'html5' => true,
        ],
    ];
    /**
     * 当没有明确指定的时候要使用的标记风格
     * @var string the markdown flavor to use when none is specified explicitly.
     * Defaults to `original`.
     * @see $flavors
     */
    public static $defaultFlavor = 'original';


    /**
     * 将 MarkDown 转换为HTML
     * Converts markdown into HTML.
     *
     * @param string $markdown the markdown text to parse
     * @param string $flavor the markdown flavor to use. See [[$flavors]] for available values.
     * Defaults to [[$defaultFlavor]], if not set.
     * @return string the parsed HTML output
     * @throws \yii\base\InvalidParamException when an undefined flavor is given.
     */
    public static function process($markdown, $flavor = null)
    {
        $parser = static::getParser($flavor);

        return $parser->parse($markdown);
    }

    /**
     * 将markdown转换为HTML，但只解析内联元素
     * Converts markdown into HTML but only parses inline elements.
     *
     * This can be useful for parsing small comments or description lines.
     *
     * @param string $markdown the markdown text to parse
     * @param string $flavor the markdown flavor to use. See [[$flavors]] for available values.
     * Defaults to [[$defaultFlavor]], if not set.
     * @return string the parsed HTML output
     * @throws \yii\base\InvalidParamException when an undefined flavor is given.
     */
    public static function processParagraph($markdown, $flavor = null)
    {
        $parser = static::getParser($flavor);

        return $parser->parseParagraph($markdown);
    }

    /**
     * markdown所使用的风格，如果没设置，使用[[$defaultFlavor]]
     * @param string $flavor the markdown flavor to use. See [[$flavors]] for available values.
     * Defaults to [[$defaultFlavor]], if not set.
     * @return \cebe\markdown\Parser
     * @throws \yii\base\InvalidParamException when an undefined flavor is given.
     */
    protected static function getParser($flavor)
    {
        if ($flavor === null) {
            $flavor = static::$defaultFlavor;
        }
        /* @var $parser \cebe\markdown\Markdown */
        if (!isset(static::$flavors[$flavor])) {
            throw new InvalidParamException("Markdown flavor '$flavor' is not defined.'");
        } elseif (!is_object($config = static::$flavors[$flavor])) {
            static::$flavors[$flavor] = Yii::createObject($config);
        }

        return static::$flavors[$flavor];
    }
}
