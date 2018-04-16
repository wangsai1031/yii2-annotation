<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\helpers;

use Yii;
use yii\base\ErrorException;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;

/**
 * BaseFileHelper为FileHelper提供具体的实现。
 * BaseFileHelper provides concrete implementation for [[FileHelper]].
 *
 * Do not use BaseFileHelper. Use [[FileHelper]] instead.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Alex Makarov <sam@rmcreative.ru>
 * @since 2.0
 */
class BaseFileHelper
{
    const PATTERN_NODIR = 1;
    const PATTERN_ENDSWITH = 4;
    const PATTERN_MUSTBEDIR = 8;
    const PATTERN_NEGATIVE = 16;
    const PATTERN_CASE_INSENSITIVE = 32;

    /**
     * 包含MIME类型信息映射关系的PHP文件的路径(或别名)
     * @var string the path (or alias) of a PHP file containing MIME type information.
     */
    public static $mimeMagicFile = '@yii/helpers/mimeTypes.php';
    /**
     * @var string the path (or alias) of a PHP file containing MIME aliases.
     * @since 2.0.14
     */
    public static $mimeAliasesFile = '@yii/helpers/mimeAliases.php';


    /**
     * 规范化文件/目录路径。
     * Normalizes a file/directory path.
     *
     * The normalization does the following work:
     *
     * - 将所有目录分隔符转换为 '/' (e.g. "\a/b\c" becomes "/a/b/c")
     * - 删除末尾的目录分隔符(e.g. "/a/b/c/" becomes "/a/b/c")
     * - 将多个连续的斜杠转换成单个 (e.g. "/a///b/c" becomes "/a/b/c")
     * - 基于他们的意义，删除'..'和 '.'
     * - Convert all directory separators into `DIRECTORY_SEPARATOR` (e.g. "\a/b\c" becomes "/a/b/c")
     * - Remove trailing directory separators (e.g. "/a/b/c/" becomes "/a/b/c")
     * - Turn multiple consecutive slashes into a single one (e.g. "/a///b/c" becomes "/a/b/c")
     * - Remove ".." and "." based on their meanings (e.g. "/a/./b/../c" becomes "/a/c")
     *
     * @param string $path the file/directory path to be normalized
     * @param string $ds the directory separator to be used in the normalized result. Defaults to `DIRECTORY_SEPARATOR`.
     * @return string the normalized file/directory path
     */
    public static function normalizePath($path, $ds = DIRECTORY_SEPARATOR)
    {
        // 将路径中的 '/' 和 '\' 全部替换为 DIRECTORY_SEPARATOR（根据当前系统决定）
        $path = rtrim(strtr($path, '/\\', $ds . $ds), $ds);

        if (strpos($ds . $path, "{$ds}.") === false && strpos($path, "{$ds}{$ds}") === false) {
            return $path;
        }
        // the path may contain ".", ".." or double slashes, need to clean them up
        if (strpos($path, "{$ds}{$ds}") === 0 && $ds == '\\') {
            $parts = [$ds];
        } else {
            $parts = [];
        }
        foreach (explode($ds, $path) as $part) {
            if ($part === '..' && !empty($parts) && end($parts) !== '..') {
                array_pop($parts);
            } elseif ($part === '.' || $part === '' && !empty($parts)) {
                continue;
            } else {
                $parts[] = $part;
            }
        }
        $path = implode($ds, $parts);
        return $path === '' ? '.' : $path;
    }

    /**
     * 返回指定文件的本地化版本
     * Returns the localized version of a specified file.
     *
     * 搜索是基于指定的语言代码的。
     * The searching is based on the specified language code. In particular,
     * a file with the same name will be looked for under the subdirectory
     * whose name is the same as the language code. For example, given the file "path/to/view.php"
     * and language code "zh-CN", the localized file will be looked for as
     * "path/to/zh-CN/view.php". If the file is not found, it will try a fallback with just a language code that is
     * "zh" i.e. "path/to/zh/view.php". If it is not found as well the original file will be returned.
     *
     * If the target and the source language codes are the same,
     * the original file will be returned.
     *
     * @param string $file the original file
     * @param string $language the target language that the file should be localized to.
     * If not set, the value of [[\yii\base\Application::language]] will be used.
     * @param string $sourceLanguage the language that the original file is in.
     * If not set, the value of [[\yii\base\Application::sourceLanguage]] will be used.
     * @return string the matching localized file, or the original file if the localized version is not found.
     * If the target and the source language codes are the same, the original file will be returned.
     */
    public static function localize($file, $language = null, $sourceLanguage = null)
    {
        if ($language === null) {
            $language = Yii::$app->language;
        }
        if ($sourceLanguage === null) {
            $sourceLanguage = Yii::$app->sourceLanguage;
        }
        if ($language === $sourceLanguage) {
            return $file;
        }
        $desiredFile = dirname($file) . DIRECTORY_SEPARATOR . $language . DIRECTORY_SEPARATOR . basename($file);
        if (is_file($desiredFile)) {
            return $desiredFile;
        }

        $language = substr($language, 0, 2);
        if ($language === $sourceLanguage) {
            return $file;
        }
        $desiredFile = dirname($file) . DIRECTORY_SEPARATOR . $language . DIRECTORY_SEPARATOR . basename($file);

        return is_file($desiredFile) ? $desiredFile : $file;
    }

    /**
     * 判定指定文件的MIME类型
     * Determines the MIME type of the specified file.
     * This method will first try to determine the MIME type based on
     * [finfo_open](http://php.net/manual/en/function.finfo-open.php). If the `fileinfo` extension is not installed,
     * it will fall back to [[getMimeTypeByExtension()]] when `$checkExtension` is true.
     * @param string $file the file name.
     * @param string $magicFile name of the optional magic database file (or alias), usually something like `/path/to/magic.mime`.
     * This will be passed as the second parameter to [finfo_open()](http://php.net/manual/en/function.finfo-open.php)
     * when the `fileinfo` extension is installed. If the MIME type is being determined based via [[getMimeTypeByExtension()]]
     * and this is null, it will use the file specified by [[mimeMagicFile]].
     * @param bool $checkExtension whether to use the file extension to determine the MIME type in case
     * `finfo_open()` cannot determine it.
     * @return string the MIME type (e.g. `text/plain`). Null is returned if the MIME type cannot be determined.
     * @throws InvalidConfigException when the `fileinfo` PHP extension is not installed and `$checkExtension` is `false`.
     */
    public static function getMimeType($file, $magicFile = null, $checkExtension = true)
    {
        if ($magicFile !== null) {
            $magicFile = Yii::getAlias($magicFile);
        }
        if (!extension_loaded('fileinfo')) {
            if ($checkExtension) {
                return static::getMimeTypeByExtension($file, $magicFile);
            }

            throw new InvalidConfigException('The fileinfo PHP extension is not installed.');
        }
        $info = finfo_open(FILEINFO_MIME_TYPE, $magicFile);

        if ($info) {
            $result = finfo_file($info, $file);
            finfo_close($info);

            if ($result !== false) {
                return $result;
            }
        }

        return $checkExtension ? static::getMimeTypeByExtension($file, $magicFile) : null;
    }

    /**
     * 根据指定文件的扩展名来确定MIME类型
     * Determines the MIME type based on the extension name of the specified file.
     * This method will use a local map between extension names and MIME types.
     * @param string $file the file name.
     * @param string $magicFile the path (or alias) of the file that contains all available MIME type information.
     * If this is not set, the file specified by [[mimeMagicFile]] will be used.
     * @return string|null the MIME type. Null is returned if the MIME type cannot be determined.
     */
    public static function getMimeTypeByExtension($file, $magicFile = null)
    {
        $mimeTypes = static::loadMimeTypes($magicFile);

        if (($ext = pathinfo($file, PATHINFO_EXTENSION)) !== '') {
            $ext = strtolower($ext);
            if (isset($mimeTypes[$ext])) {
                return $mimeTypes[$ext];
            }
        }

        return null;
    }

    /**
     * 通过给定的MIME类型来确定扩展名
     * Determines the extensions by given MIME type.
     * This method will use a local map between extension names and MIME types.
     * @param string $mimeType file MIME type.
     * @param string $magicFile the path (or alias) of the file that contains all available MIME type information.
     * If this is not set, the file specified by [[mimeMagicFile]] will be used.
     * @return array the extensions corresponding to the specified MIME type
     */
    public static function getExtensionsByMimeType($mimeType, $magicFile = null)
    {
        $aliases = static::loadMimeAliases(static::$mimeAliasesFile);
        if (isset($aliases[$mimeType])) {
            $mimeType = $aliases[$mimeType];
        }

        $mimeTypes = static::loadMimeTypes($magicFile);
        return array_keys($mimeTypes, mb_strtolower($mimeType, 'UTF-8'), true);
    }

    private static $_mimeTypes = [];

    /**
     * 从指定文件加载MIME类型映射关系
     * Loads MIME types from the specified file.
     *
     * 包含所有可用MIME类型信息的文件的路径(或别名)。
     * 如果未设置，则默认加载[[mimeMagicFile]]指定的文件
     * @param string $magicFile the path (or alias) of the file that contains all available MIME type information.
     * If this is not set, the file specified by [[mimeMagicFile]] will be used.
     * @return array the mapping from file extensions to MIME types
     */
    protected static function loadMimeTypes($magicFile)
    {
        if ($magicFile === null) {
            $magicFile = static::$mimeMagicFile;
        }
        $magicFile = Yii::getAlias($magicFile);
        if (!isset(self::$_mimeTypes[$magicFile])) {
            self::$_mimeTypes[$magicFile] = require $magicFile;
        }

        return self::$_mimeTypes[$magicFile];
    }

    private static $_mimeAliases = [];

    /**
     * Loads MIME aliases from the specified file.
     * @param string $aliasesFile the path (or alias) of the file that contains MIME type aliases.
     * If this is not set, the file specified by [[mimeAliasesFile]] will be used.
     * @return array the mapping from file extensions to MIME types
     * @since 2.0.14
     */
    protected static function loadMimeAliases($aliasesFile)
    {
        if ($aliasesFile === null) {
            $aliasesFile = static::$mimeAliasesFile;
        }
        $aliasesFile = Yii::getAlias($aliasesFile);
        if (!isset(self::$_mimeAliases[$aliasesFile])) {
            self::$_mimeAliases[$aliasesFile] = require $aliasesFile;
        }

        return self::$_mimeAliases[$aliasesFile];
    }

    /**
     * 将整个目录复制为另一个目录
     * 文件和子目录也将被复制
     * Copies a whole directory as another one.
     * The files and sub-directories will also be copied over.
     * @param string $src the source directory
     * 源目录
     * @param string $dst the destination directory
     * 目标目录
     * @param array $options options for directory copy. Valid options are:
     * 目录复制选项
     *
     * - dirMode: integer, 为新复制的目录设置的权限。默认为0775。
     * - fileMode:  integer, 为新复制的文件设置的权限。默认值根据当前环境设置。
     * - filter: callback, 为每个目录或文件调用的PHP回调。
     *   回调的签名应该是:`function ($path)`，$path指的是被过滤的完整路径。
     *   回调可以返回下列值之一：
     *
     *   * true: 目录或文件将被复制("only" and "except"选项将被忽略)
     *   * false: 目录或文件将不会被复制("only" and "except"选项将被忽略)
     *   * null: "only" and "except"选项将决定是否要复制目录或文件。
     * 
     * 
     * - dirMode: integer, the permission to be set for newly copied directories. Defaults to 0775.
     * - fileMode:  integer, the permission to be set for newly copied files. Defaults to the current environment setting.
     * - filter: callback, a PHP callback that is called for each directory or file.
     *   The signature of the callback should be: `function ($path)`, where `$path` refers the full path to be filtered.
     *   The callback can return one of the following values:
     *
     *   * true: the directory or file will be copied (the "only" and "except" options will be ignored)
     *   * false: the directory or file will NOT be copied (the "only" and "except" options will be ignored)
     *   * null: the "only" and "except" options will determine whether the directory or file should be copied
     *
     * - only: array, list of patterns that the file paths should match if they want to be copied.
     *   A path matches a pattern if it contains the pattern string at its end.
     *   For example, '.php' matches all file paths ending with '.php'.
     *   Note, the '/' characters in a pattern matches both '/' and '\' in the paths.
     *   If a file path matches a pattern in both "only" and "except", it will NOT be copied.
     * - except: array, list of patterns that the files or directories should match if they want to be excluded from being copied.
     *   A path matches a pattern if it contains the pattern string at its end.
     *   Patterns ending with '/' apply to directory paths only, and patterns not ending with '/'
     *   apply to file paths only. For example, '/a/b' matches all file paths ending with '/a/b';
     *   and '.svn/' matches directory paths ending with '.svn'. Note, the '/' characters in a pattern matches
     *   both '/' and '\' in the paths.
     * - caseSensitive: boolean, whether patterns specified at "only" or "except" should be case sensitive. Defaults to true.
     * - recursive: boolean, whether the files under the subdirectories should also be copied. Defaults to true.
     * - beforeCopy: callback, a PHP callback that is called before copying each sub-directory or file.
     *   If the callback returns false, the copy operation for the sub-directory or file will be cancelled.
     *   The signature of the callback should be: `function ($from, $to)`, where `$from` is the sub-directory or
     *   file to be copied from, while `$to` is the copy target.
     * - afterCopy: callback, a PHP callback that is called after each sub-directory or file is successfully copied.
     *   The signature of the callback should be: `function ($from, $to)`, where `$from` is the sub-directory or
     *   file copied from, while `$to` is the copy target.
     * - copyEmptyDirectories: boolean, whether to copy empty directories. Set this to false to avoid creating directories
     *   that do not contain files. This affects directories that do not contain files initially as well as directories that
     *   do not contain files at the target destination because files have been filtered via `only` or `except`.
     *   Defaults to true. This option is available since version 2.0.12. Before 2.0.12 empty directories are always copied.
     * @throws InvalidArgumentException if unable to open directory
     */
    public static function copyDirectory($src, $dst, $options = [])
    {
        $src = static::normalizePath($src);
        $dst = static::normalizePath($dst);

        if ($src === $dst || strpos($dst, $src . DIRECTORY_SEPARATOR) === 0) {
            throw new InvalidArgumentException('Trying to copy a directory to itself or a subdirectory.');
        }
        $dstExists = is_dir($dst);
        if (!$dstExists && (!isset($options['copyEmptyDirectories']) || $options['copyEmptyDirectories'])) {
            static::createDirectory($dst, isset($options['dirMode']) ? $options['dirMode'] : 0775, true);
            $dstExists = true;
        }

        $handle = opendir($src);
        if ($handle === false) {
            throw new InvalidArgumentException("Unable to open directory: $src");
        }
        if (!isset($options['basePath'])) {
            // this should be done only once
            $options['basePath'] = realpath($src);
            $options = static::normalizeOptions($options);
        }
        while (($file = readdir($handle)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $from = $src . DIRECTORY_SEPARATOR . $file;
            $to = $dst . DIRECTORY_SEPARATOR . $file;
            if (static::filterPath($from, $options)) {
                if (isset($options['beforeCopy']) && !call_user_func($options['beforeCopy'], $from, $to)) {
                    continue;
                }
                if (is_file($from)) {
                    if (!$dstExists) {
                        // delay creation of destination directory until the first file is copied to avoid creating empty directories
                        static::createDirectory($dst, isset($options['dirMode']) ? $options['dirMode'] : 0775, true);
                        $dstExists = true;
                    }
                    copy($from, $to);
                    if (isset($options['fileMode'])) {
                        @chmod($to, $options['fileMode']);
                    }
                } else {
                    // recursive copy, defaults to true
                    if (!isset($options['recursive']) || $options['recursive']) {
                        static::copyDirectory($from, $to, $options);
                    }
                }
                if (isset($options['afterCopy'])) {
                    call_user_func($options['afterCopy'], $from, $to);
                }
            }
        }
        closedir($handle);
    }

    /**
     * 递归地删除一个目录(及其所有内容)
     * Removes a directory (and all its content) recursively.
     *
     * @param string $dir the directory to be deleted recursively.
     * 删除目录的配置选项，有效的选项有：
     * @param array $options options for directory remove. Valid options are:
     *
     *   是否遍历删除文件夹中通过软链接连接到的目录。默认是false，只会删除该软链接。
     * - traverseSymlinks: boolean, whether symlinks to the directories should be traversed too.
     *   Defaults to `false`, meaning the content of the symlinked directory would not be deleted.
     *   Only symlink would be removed in that default case.
     *
     * @throws ErrorException in case of failure
     */
    public static function removeDirectory($dir, $options = [])
    {
        // 判断是否是目录。不是则直接返回。
        if (!is_dir($dir)) {
            return;
        }
        // 判断 是否遍历删除文件夹中通过软链接连接到的目录，is_link() 判断是否是软链接。
        if (!empty($options['traverseSymlinks']) || !is_link($dir)) {
            // opendir() 打开目录句柄。成功则返回目录句柄资源。失败则返回 FALSE。
            if (!($handle = opendir($dir))) {
                return;
            }
            // 返回目录句柄中的当前文件。
            while (($file = readdir($handle)) !== false) {
                // 文件名不能是 '.' 或 '..'
                if ($file === '.' || $file === '..') {
                    continue;
                }
                // 拼接文件目录
                $path = $dir . DIRECTORY_SEPARATOR . $file;
                // 若是目录，则递归地调用当前函数
                if (is_dir($path)) {
                    static::removeDirectory($path, $options);
                } else {
                    // 删除文件
                    static::unlink($path);
                }
            }
            // 关闭目录句柄
            closedir($handle);
        }
        // 是软链接，直接删除
        if (is_link($dir)) {
            static::unlink($dir);
        } else {
            // 是目录的话，经过 !is_link($dir)，已经删除了该目录下所有文件，该目录也可以删除了。
            rmdir($dir);
        }
    }

    /**
     * Removes a file or symlink in a cross-platform way
     *
     * @param string $path
     * @return bool
     *
     * @since 2.0.14
     */
    public static function unlink($path)
    {
        // 过目录分割符是 '\'， 则说明是windows目录
        $isWindows = DIRECTORY_SEPARATOR === '\\';

        if (!$isWindows) {
            return unlink($path);
        }

        if (is_link($path) && is_dir($path)) {
            return rmdir($path);
        }

        try {
            return unlink($path);
        } catch (ErrorException $e) {
            // last resort measure for Windows
            $lines = [];
            /**
             * http://www.php.net/manual/zh/function.exec.php
             * exec — 执行一个外部程序命令
             *
             * DOS 命令
             * DEL 删除多个文件
             * /F 强制删除只读文件
             * /Q 安静模式。删除全局通配符时，不要求确认。
             */
            exec('DEL /F/Q ' . escapeshellarg($path), $lines, $deleteError);
            return $deleteError !== 0;
        }
    }

    /**
     * 返回在指定目录和子目录下找到的文件
     * Returns the files found under the specified directory and subdirectories.
     * @param string $dir the directory under which the files will be looked for.
     * @param array $options options for file searching. Valid options are:
     *
     * - `filter`: callback, a PHP callback that is called for each directory or file.
     *   The signature of the callback should be: `function ($path)`, where `$path` refers the full path to be filtered.
     *   The callback can return one of the following values:
     *
     *   * `true`: the directory or file will be returned (the `only` and `except` options will be ignored)
     *   * `false`: the directory or file will NOT be returned (the `only` and `except` options will be ignored)
     *   * `null`: the `only` and `except` options will determine whether the directory or file should be returned
     *
     * - `except`: array, list of patterns excluding from the results matching file or directory paths.
     *   Patterns ending with slash ('/') apply to directory paths only, and patterns not ending with '/'
     *   apply to file paths only. For example, '/a/b' matches all file paths ending with '/a/b';
     *   and `.svn/` matches directory paths ending with `.svn`.
     *   If the pattern does not contain a slash (`/`), it is treated as a shell glob pattern
     *   and checked for a match against the pathname relative to `$dir`.
     *   Otherwise, the pattern is treated as a shell glob suitable for consumption by `fnmatch(3)`
     *   with the `FNM_PATHNAME` flag: wildcards in the pattern will not match a `/` in the pathname.
     *   For example, `views/*.php` matches `views/index.php` but not `views/controller/index.php`.
     *   A leading slash matches the beginning of the pathname. For example, `/*.php` matches `index.php` but not `views/start/index.php`.
     *   An optional prefix `!` which negates the pattern; any matching file excluded by a previous pattern will become included again.
     *   If a negated pattern matches, this will override lower precedence patterns sources. Put a backslash (`\`) in front of the first `!`
     *   for patterns that begin with a literal `!`, for example, `\!important!.txt`.
     *   Note, the '/' characters in a pattern matches both '/' and '\' in the paths.
     * - `only`: array, list of patterns that the file paths should match if they are to be returned. Directory paths
     *   are not checked against them. Same pattern matching rules as in the `except` option are used.
     *   If a file path matches a pattern in both `only` and `except`, it will NOT be returned.
     * - `caseSensitive`: boolean, whether patterns specified at `only` or `except` should be case sensitive. Defaults to `true`.
     * - `recursive`: boolean, whether the files under the subdirectories should also be looked for. Defaults to `true`.
     * @return array files found under the directory, in no particular order. Ordering depends on the files system used.
     * @throws InvalidArgumentException if the dir is invalid.
     */
    public static function findFiles($dir, $options = [])
    {
        $dir = self::clearDir($dir);
        $options = self::setBasePath($dir, $options);
        $list = [];
        $handle = self::openDir($dir);
        while (($file = readdir($handle)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (static::filterPath($path, $options)) {
                if (is_file($path)) {
                    $list[] = $path;
                } elseif (is_dir($path) && (!isset($options['recursive']) || $options['recursive'])) {
                    $list = array_merge($list, static::findFiles($path, $options));
                }
            }
        }
        closedir($handle);

        return $list;
    }

    /**
     * Returns the directories found under the specified directory and subdirectories.
     * @param string $dir the directory under which the files will be looked for.
     * @param array $options options for directory searching. Valid options are:
     *
     * - `filter`: callback, a PHP callback that is called for each directory or file.
     *   The signature of the callback should be: `function ($path)`, where `$path` refers the full path to be filtered.
     *   The callback can return one of the following values:
     *
     *   * `true`: the directory will be returned
     *   * `false`: the directory will NOT be returned
     *
     * - `recursive`: boolean, whether the files under the subdirectories should also be looked for. Defaults to `true`.
     * @return array directories found under the directory, in no particular order. Ordering depends on the files system used.
     * @throws InvalidArgumentException if the dir is invalid.
     * @since 2.0.14
     */
    public static function findDirectories($dir, $options = [])
    {
        $dir = self::clearDir($dir);
        $options = self::setBasePath($dir, $options);
        $list = [];
        $handle = self::openDir($dir);
        while (($file = readdir($handle)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path) && static::filterPath($path, $options)) {
                $list[] = $path;
                if (!isset($options['recursive']) || $options['recursive']) {
                    $list = array_merge($list, static::findDirectories($path, $options));
                }
            }
        }
        closedir($handle);

        return $list;
    }

    /*
     * @param string $dir
     */
    private static function setBasePath($dir, $options)
    {
        if (!isset($options['basePath'])) {
            // this should be done only once
            $options['basePath'] = realpath($dir);
            $options = static::normalizeOptions($options);
        }

        return $options;
    }

    /*
     * @param string $dir
     */
    private static function openDir($dir)
    {
        $handle = opendir($dir);
        if ($handle === false) {
            throw new InvalidArgumentException("Unable to open directory: $dir");
        }
        return $handle;
    }

    /*
     * @param string $dir
     */
    private static function clearDir($dir)
    {
        if (!is_dir($dir)) {
            throw new InvalidArgumentException("The dir argument must be a directory: $dir");
        }
        return rtrim($dir, DIRECTORY_SEPARATOR);
    }

    /**
     * 检查给定的文件路径是否满足筛选选项
     * Checks if the given file path satisfies the filtering options.
     * @param string $path the path of the file or directory to be checked
     * @param array $options the filtering options. See [[findFiles()]] for explanations of
     * the supported options.
     * @return bool whether the file or directory satisfies the filtering options.
     */
    public static function filterPath($path, $options)
    {
        if (isset($options['filter'])) {
            $result = call_user_func($options['filter'], $path);
            if (is_bool($result)) {
                return $result;
            }
        }

        if (empty($options['except']) && empty($options['only'])) {
            return true;
        }

        $path = str_replace('\\', '/', $path);

        if (!empty($options['except'])) {
            if (($except = self::lastExcludeMatchingFromList($options['basePath'], $path, $options['except'])) !== null) {
                return $except['flags'] & self::PATTERN_NEGATIVE;
            }
        }

        if (!empty($options['only']) && !is_dir($path)) {
            if (($except = self::lastExcludeMatchingFromList($options['basePath'], $path, $options['only'])) !== null) {
                // don't check PATTERN_NEGATIVE since those entries are not prefixed with !
                return true;
            }

            return false;
        }

        return true;
    }

    /**
     * 创建一个新的目录
     * Creates a new directory.
     *
     * 这个方法类似于PHP mkdir()函数，除了使用chmod()设置创建的目录的权限，为了避免umask设置的影响。
     * This method is similar to the PHP `mkdir()` function except that
     * it uses `chmod()` to set the permission of the created directory
     * in order to avoid the impact of the `umask` setting.
     *
     * 要创建的目录的路径
     * @param string $path path of the directory to be created.
     * 为创建的目录设置的权限
     * @param int $mode the permission to be set for the created directory.
     * 是否递归创建目录，即：如果父目录不存在，是否创建父目录。
     * @param bool $recursive whether to create parent directories if they do not exist.
     * 返回是否成功创建了目录
     * @return bool whether the directory is created successfully
     * @throws \yii\base\Exception if the directory could not be created (i.e. php error due to parallel changes)
     */
    public static function createDirectory($path, $mode = 0775, $recursive = true)
    {
        // 判断文件名是否为一个目录，若是，说明目录已存在，直接返回 true
        if (is_dir($path)) {
            return true;
        }
        // 获取父级目录
        $parentDir = dirname($path);
        // recurse if parent dir does not exist and we are not at the root of the file system.
        // 如果父目录不存在，并且我们不是在文件系统的根目录，则递归调用本方法创建父级目录
        if ($recursive && !is_dir($parentDir) && $parentDir !== $path) {
            static::createDirectory($parentDir, $mode, true);
        }
        try {
            // 创建目录，若不成功则返回false
            if (!mkdir($path, $mode)) {
                return false;
            }
        } catch (\Exception $e) {
            /**
             * @see https://github.com/yiisoft/yii2/issues/9288
             * issue 中提到这样一种情况：
             *
             * 在多个PHP进程并行状态下。
             * 当检测目录是否存在时，目录不存在。
             * 但是当运行到创建目录函数时，该目录已经被其他PHP进程创建了。
             * 这时可能会抛异常。
             *
             * 因此，在抛出异常前，再检查一遍目录是否存在
             */
            if (!is_dir($path)) {// https://github.com/yiisoft/yii2/issues/9288
                throw new \yii\base\Exception("Failed to create directory \"$path\": " . $e->getMessage(), $e->getCode(), $e);
            }
        }
        try {
            // 修改目录权限
            return chmod($path, $mode);
        } catch (\Exception $e) {
            throw new \yii\base\Exception("Failed to change permissions for directory \"$path\": " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * 对文件或目录名进行简单的比较
     * Performs a simple comparison of file or directory names.
     *
     * Based on match_basename() from dir.c of git 1.8.5.3 sources.
     *
     * @param string $baseName file or directory name to compare with the pattern
     * @param string $pattern the pattern that $baseName will be compared against
     * @param int|bool $firstWildcard location of first wildcard character in the $pattern
     * @param int $flags pattern flags
     * @return bool whether the name matches against pattern
     */
    private static function matchBasename($baseName, $pattern, $firstWildcard, $flags)
    {
        if ($firstWildcard === false) {
            if ($pattern === $baseName) {
                return true;
            }
        } elseif ($flags & self::PATTERN_ENDSWITH) {
            /* "*literal" matching against "fooliteral" */
            $n = StringHelper::byteLength($pattern);
            if (StringHelper::byteSubstr($pattern, 1, $n) === StringHelper::byteSubstr($baseName, -$n, $n)) {
                return true;
            }
        }

        $matchOptions = [];
        if ($flags & self::PATTERN_CASE_INSENSITIVE) {
            $matchOptions['caseSensitive'] = false;
        }

        return StringHelper::matchWildcard($pattern, $baseName, $matchOptions);
    }

    /**
     * 将路径部分与带有可选通配符的模型进行比较
     * Compares a path part against a pattern with optional wildcards.
     *
     * Based on match_pathname() from dir.c of git 1.8.5.3 sources.
     *
     * @param string $path full path to compare
     * @param string $basePath base of path that will not be compared
     * @param string $pattern the pattern that path part will be compared against
     * @param int|bool $firstWildcard location of first wildcard character in the $pattern
     * @param int $flags pattern flags
     * @return bool whether the path part matches against pattern
     */
    private static function matchPathname($path, $basePath, $pattern, $firstWildcard, $flags)
    {
        // match with FNM_PATHNAME; the pattern has base implicitly in front of it.
        if (isset($pattern[0]) && $pattern[0] === '/') {
            $pattern = StringHelper::byteSubstr($pattern, 1, StringHelper::byteLength($pattern));
            if ($firstWildcard !== false && $firstWildcard !== 0) {
                $firstWildcard--;
            }
        }

        $namelen = StringHelper::byteLength($path) - (empty($basePath) ? 0 : StringHelper::byteLength($basePath) + 1);
        $name = StringHelper::byteSubstr($path, -$namelen, $namelen);

        if ($firstWildcard !== 0) {
            if ($firstWildcard === false) {
                $firstWildcard = StringHelper::byteLength($pattern);
            }
            // if the non-wildcard part is longer than the remaining pathname, surely it cannot match.
            if ($firstWildcard > $namelen) {
                return false;
            }

            if (strncmp($pattern, $name, $firstWildcard)) {
                return false;
            }
            $pattern = StringHelper::byteSubstr($pattern, $firstWildcard, StringHelper::byteLength($pattern));
            $name = StringHelper::byteSubstr($name, $firstWildcard, $namelen);

            // If the whole pattern did not have a wildcard, then our prefix match is all we need; we do not need to call fnmatch at all.
            if (empty($pattern) && empty($name)) {
                return true;
            }
        }

        $matchOptions = [
            'filePath' => true
        ];
        if ($flags & self::PATTERN_CASE_INSENSITIVE) {
            $matchOptions['caseSensitive'] = false;
        }

        return StringHelper::matchWildcard($pattern, $name, $matchOptions);
    }

    /**
     * 对给定的排除列表进行反向扫描，以查看是否应该忽略路径名
     * 匹配的第一项（即，列表的最后一项），如果有的话，不再继续
     * 返回匹配的元素，未找到返回null
     * Scan the given exclude list in reverse to see whether pathname
     * should be ignored.  The first match (i.e. the last on the list), if
     * any, determines the fate.  Returns the element which
     * matched, or null for undecided.
     *
     * Based on last_exclude_matching_from_list() from dir.c of git 1.8.5.3 sources.
     *
     * @param string $basePath
     * @param string $path
     * @param array $excludes list of patterns to match $path against
     * @return array|null null or one of $excludes item as an array with keys: 'pattern', 'flags'
     * @throws InvalidArgumentException if any of the exclude patterns is not a string or an array with keys: pattern, flags, firstWildcard.
     */
    private static function lastExcludeMatchingFromList($basePath, $path, $excludes)
    {
        foreach (array_reverse($excludes) as $exclude) {
            if (is_string($exclude)) {
                $exclude = self::parseExcludePattern($exclude, false);
            }
            if (!isset($exclude['pattern']) || !isset($exclude['flags']) || !isset($exclude['firstWildcard'])) {
                throw new InvalidArgumentException('If exclude/include pattern is an array it must contain the pattern, flags and firstWildcard keys.');
            }
            if ($exclude['flags'] & self::PATTERN_MUSTBEDIR && !is_dir($path)) {
                continue;
            }

            if ($exclude['flags'] & self::PATTERN_NODIR) {
                if (self::matchBasename(basename($path), $exclude['pattern'], $exclude['firstWildcard'], $exclude['flags'])) {
                    return $exclude;
                }
                continue;
            }

            if (self::matchPathname($path, $basePath, $exclude['pattern'], $exclude['firstWildcard'], $exclude['flags'])) {
                return $exclude;
            }
        }

        return null;
    }

    /**
     * 处理模型，剥离特殊字符，如/和！。
     * Processes the pattern, stripping special characters like / and ! from the beginning and settings flags instead.
     * @param string $pattern
     * @param bool $caseSensitive
     * @throws InvalidArgumentException
     * @return array with keys: (string) pattern, (int) flags, (int|bool) firstWildcard
     */
    private static function parseExcludePattern($pattern, $caseSensitive)
    {
        if (!is_string($pattern)) {
            throw new InvalidArgumentException('Exclude/include pattern must be a string.');
        }

        $result = [
            'pattern' => $pattern,
            'flags' => 0,
            'firstWildcard' => false,
        ];

        if (!$caseSensitive) {
            $result['flags'] |= self::PATTERN_CASE_INSENSITIVE;
        }

        if (!isset($pattern[0])) {
            return $result;
        }

        if ($pattern[0] === '!') {
            $result['flags'] |= self::PATTERN_NEGATIVE;
            $pattern = StringHelper::byteSubstr($pattern, 1, StringHelper::byteLength($pattern));
        }
        if (StringHelper::byteLength($pattern) && StringHelper::byteSubstr($pattern, -1, 1) === '/') {
            $pattern = StringHelper::byteSubstr($pattern, 0, -1);
            $result['flags'] |= self::PATTERN_MUSTBEDIR;
        }
        if (strpos($pattern, '/') === false) {
            $result['flags'] |= self::PATTERN_NODIR;
        }
        $result['firstWildcard'] = self::firstWildcardInPattern($pattern);
        if ($pattern[0] === '*' && self::firstWildcardInPattern(StringHelper::byteSubstr($pattern, 1, StringHelper::byteLength($pattern))) === false) {
            $result['flags'] |= self::PATTERN_ENDSWITH;
        }
        $result['pattern'] = $pattern;

        return $result;
    }

    /**
     * 在模型中搜索第一个通配符字符
     * Searches for the first wildcard character in the pattern.
     * @param string $pattern the pattern to search in
     * @return int|bool position of first wildcard character or false if not found
     */
    private static function firstWildcardInPattern($pattern)
    {
        $wildcards = ['*', '?', '[', '\\'];
        $wildcardSearch = function ($r, $c) use ($pattern) {
            $p = strpos($pattern, $c);

            return $r === false ? $p : ($p === false ? $r : min($r, $p));
        };

        return array_reduce($wildcards, $wildcardSearch, false);
    }

    /**
     * 规格化 Option
     * @param array $options raw options
     * @return array normalized options
     * @since 2.0.12
     */
    protected static function normalizeOptions(array $options)
    {
        if (!array_key_exists('caseSensitive', $options)) {
            $options['caseSensitive'] = true;
        }
        if (isset($options['except'])) {
            foreach ($options['except'] as $key => $value) {
                if (is_string($value)) {
                    $options['except'][$key] = self::parseExcludePattern($value, $options['caseSensitive']);
                }
            }
        }
        if (isset($options['only'])) {
            foreach ($options['only'] as $key => $value) {
                if (is_string($value)) {
                    $options['only'][$key] = self::parseExcludePattern($value, $options['caseSensitive']);
                }
            }
        }

        return $options;
    }
}
