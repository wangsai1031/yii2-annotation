<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\caching;

use Yii;
use yii\helpers\FileHelper;

/**
 * 使用标准文件存储缓存数据。
 * 这个特别适用于 缓存大块数据，例如一个整页的内容。
 * FileCache implements a cache component using files.
 *
 * 对于缓存的每个数据值，FileCache将把它存储在一个单独的文件中。
 * 缓存文件放在[[cachePath]]下面。
 * FileCache将自动执行垃圾回收，以删除过期的缓存文件。
 * For each data value being cached, FileCache will store it in a separate file.
 * The cache files are placed under [[cachePath]]. FileCache will perform garbage collection
 * automatically to remove expired cache files.
 *
 * Please refer to [[Cache]] for common cache operations that are supported by FileCache.
 *
 * For more details and usage information on Cache, see the [guide article on caching](guide:caching-overview).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class FileCache extends Cache
{
    /**
     * 每个缓存键的字符串前缀。
     * 当您为不同的应用程序存储缓存数据以避免冲突时，这是需要的。
     *
     * 为了确保互操作性，只能使用字母数字字符
     * @var string a string prefixed to every cache key. This is needed when you store
     * cache data under the same [[cachePath]] for different applications to avoid
     * conflict.
     *
     * To ensure interoperability, only alphanumeric characters should be used.
     */
    public $keyPrefix = '';
    /**
     * 存储缓存文件的目录。这里可以使用path别名。
     * 如果不设置，它将使用应用程序运行时路径下的 "cache" 子目录。
     * @var string the directory to store cache files. You may use [path alias](guide:concept-aliases) here.
     * If not set, it will use the "cache" subdirectory under the application runtime path.
     */
    public $cachePath = '@runtime/cache';
    /**
     * 缓存文件后缀。默认为 '.bin'
     * @var string cache file suffix. Defaults to '.bin'.
     */
    public $cacheFileSuffix = '.bin';
    /**
     * 存储缓存文件的子目录的级别. 默认是 1。
     * 如果系统有大量的缓存文件（例如100万），您可以使用更大的值(通常不大于3)。
     * 使用子目录主要是为了确保文件系统不会因为在单一目录中有太多文件的而负担过重。
     * @var int the level of sub-directories to store cache files. Defaults to 1.
     * If the system has huge number of cache files (e.g. one million), you may use a bigger value
     * (usually no bigger than 3). Using sub-directories is mainly to ensure the file system
     * is not over burdened with a single directory having too many files.
     */
    public $directoryLevel = 1;
    /**
     * 当在缓存中存储数据时，执行垃圾回收(GC)的概率(百万分之一)。
     * 默认是10,意味着 0.001% 的概率。
     * 这个数字应该在0到1000000之间。0意味着不执行垃圾回收。
     * @var int the probability (parts per million) that garbage collection (GC) should be performed
     * when storing a piece of data in the cache. Defaults to 10, meaning 0.001% chance.
     * This number should be between 0 and 1000000. A value 0 means no GC will be performed at all.
     */
    public $gcProbability = 10;
    /**
     * 为新创建的缓存文件设置的权限。
     * 这个值将被PHP chmod()函数使用。
     * 不会应用umask. 关于umask @link https://baike.baidu.com/item/umask/6048811?fr=aladdin
     * 如果不设置，则权限将由当前环境决定.
     * 
     * @var int the permission to be set for newly created cache files.
     * This value will be used by PHP chmod() function. No umask will be applied.
     * If not set, the permission will be determined by the current environment.
     */
    public $fileMode;
    /**
     * 为新创建的目录设置权限.
     * 这个值将被PHP chmod()函数使用.
     * 默认值为0775，这意味着该目录对于所有者和组的可读写的，但对于其他用户是只读的。
     * 
     * @var int the permission to be set for newly created directories.
     * This value will be used by PHP chmod() function. No umask will be applied.
     * Defaults to 0775, meaning the directory is read-writable by owner and group,
     * but read-only for other users.
     */
    public $dirMode = 0775;


    /**
     * 来初始化该组件，确保缓存路径存在。
     * Initializes this component by ensuring the existence of the cache path.
     */
    public function init()
    {
        parent::init();
        // 获取缓存文件路径
        $this->cachePath = Yii::getAlias($this->cachePath);
        // 判断文件路径是否存在，不存在则创建目录
        if (!is_dir($this->cachePath)) {
            FileHelper::createDirectory($this->cachePath, $this->dirMode, true);
        }
    }

    /**
     * 检查一个指定的键是否存在于缓存中.
     * 如果缓存的数据很大，这个方法比从缓存中获取值要快。
     * 如果使用的缓存组件支持这个特性，则应该使用缓存组件更加适用的方法覆盖本方法。
     * 如果一个缓存不支持这个特性，那么这个方法将尝试模拟它，但是在获得它的过程中没有性能上的改进。
     * 注意，该方法不检查与缓存数据相关的依赖关系是否已经发生了变化。
     * 因此，当该函数返回true时，调用[[get]]可能返回false。
     * Checks whether a specified key exists in the cache.
     * This can be faster than getting the value from the cache if the data is big.
     * Note that this method does not check whether the dependency associated
     * with the cached data, if there is any, has changed. So a call to [[get]]
     * may return false while exists returns true.
     * @param mixed $key a key identifying the cached value. This can be a simple string or
     * a complex data structure consisting of factors representing the key.
     * @return bool true if a value exists in cache, false if the value is not in the cache or expired.
     */
    public function exists($key)
    {
        // 获取缓存文件
        $cacheFile = $this->getCacheFile($this->buildKey($key));

        // 检查文件最后修改时间是否大于现在（即缓存是否过期）
        return @filemtime($cacheFile) > time();
    }

    /**
     * 使用指定的键从缓存中检索值
     * 这是在父类中声明的方法的实现
     * Retrieves a value from cache with a specified key.
     * This is the implementation of the method declared in the parent class.
     * @param string $key a unique key identifying the cached value
     * @return string|false the value stored in cache, false if the value is not in the cache or expired.
     */
    protected function getValue($key)
    {
        // 根据文件名获取缓存数据的文件
        $cacheFile = $this->getCacheFile($key);

        // 获取文件最后修改时间（即文件缓存过期时间），
        // 并判断是否大于现在，若大于，则说明该缓存没有过期
        if (@filemtime($cacheFile) > time()) {
            // 只读方式打开缓存文件
            $fp = @fopen($cacheFile, 'r');
            // 打开成功
            if ($fp !== false) {
                /**
                 * flock 锁定或释放文件
                 * 第二个参数：必需。规定要使用哪种锁定类型。
                 *
                 * 要取得共享锁定（读取的程序），将 lock 设为 LOCK_SH（PHP 4.0.1 以前的版本设置为 1）。
                 * 要取得独占锁定（写入的程序），将 lock 设为 LOCK_EX（PHP 4.0.1 以前的版本中设置为 2）。
                 * 要释放锁定（无论共享或独占），将 lock 设为 LOCK_UN（PHP 4.0.1 以前的版本中设置为 3）。
                 * 如果不希望 flock() 在锁定时堵塞，则给 lock 加上 LOCK_NB（PHP 4.0.1 以前的版本中设置为 4）。
                 *
                 * @see http://www.w3school.com.cn/php/func_filesystem_flock.asp
                 */
                @flock($fp, LOCK_SH);
                /**
                 * stream_get_contents() 读取资源流到一个字符串
                 * 与 file_get_contents() 一样，但是 stream_get_contents() 是对一个已经打开的资源流进行操作，
                 * 并将其内容写入一个字符串返回。 返回的内容取决于 maxlength 字节长度和 offset 指定的起始位置。
                 *
                 * @see http://php.net/manual/zh/function.stream-get-contents.php
                 */
                $cacheValue = @stream_get_contents($fp);
                // 释放锁定
                @flock($fp, LOCK_UN);
                // 关闭文件
                @fclose($fp);
                return $cacheValue;
            }
        }

        return false;
    }

    /**
     * 在缓存中存储一个键对应的值。
     * 这是在父类中声明的方法的实现.
     * Stores a value identified by a key in cache.
     * This is the implementation of the method declared in the parent class.
     *
     * @param string $key the key identifying the value to be cached
     * @param string $value the value to be cached. Other types (If you have disabled [[serializer]]) unable to get is
     * correct in [[getValue()]].
     * @param int $duration the number of seconds in which the cached value will expire. 0 means never expire.
     * @return bool true if the value is successfully stored into cache, false otherwise
     */
    protected function setValue($key, $value, $duration)
    {
        // 执行垃圾回收
        $this->gc();
        // 获取缓存文件名（包含路径）
        $cacheFile = $this->getCacheFile($key);
        // 若存储缓存文件的子目录的级别大于0
        if ($this->directoryLevel > 0) {
            // 先创建缓存文件目录
            @FileHelper::createDirectory(dirname($cacheFile), $this->dirMode, true);
        }
        // 使用独占锁将缓存值写入文件
        if (@file_put_contents($cacheFile, $value, LOCK_EX) !== false) {
            // 设置文件权限
            if ($this->fileMode !== null) {
                @chmod($cacheFile, $this->fileMode);
            }
            // 设置过期时间，当过期时间小于等于0时，默认是一年。
            if ($duration <= 0) {
                $duration = 31536000; // 1 year
            }

            // 通过设置文件最后修改时间的方式设置缓存过期时间
            return @touch($cacheFile, $duration + time());
        }

        // 获得最后一个发生错误
        $error = error_get_last();
        Yii::warning("Unable to write cache file '{$cacheFile}': {$error['message']}", __METHOD__);
        return false;
    }

    /**
     * 如果缓存不包含该键，则缓存该键和值。
     * 这是在父类中声明的方法的实现.
     * Stores a value identified by a key into cache if the cache does not contain this key.
     * This is the implementation of the method declared in the parent class.
     *
     * @param string $key the key identifying the value to be cached
     * @param string $value the value to be cached. Other types (if you have disabled [[serializer]]) unable to get is
     * correct in [[getValue()]].
     * @param int $duration the number of seconds in which the cached value will expire. 0 means never expire.
     * @return bool true if the value is successfully stored into cache, false otherwise
     */
    protected function addValue($key, $value, $duration)
    {
        // 获取缓存文件名（包含路径）
        $cacheFile = $this->getCacheFile($key);
        // 若缓存文件存在且未过期，则不再继续
        if (@filemtime($cacheFile) > time()) {
            return false;
        }

        // 通过调用 setValue() 设置缓存
        return $this->setValue($key, $value, $duration);
    }

    /**
     * 从缓存中删除指定键的值。
     * 这是在父类中声明的方法的实现.
     * Deletes a value with the specified key from cache
     * This is the implementation of the method declared in the parent class.
     * @param string $key the key of the value to be deleted
     * @return bool if no error happens during deletion
     */
    protected function deleteValue($key)
    {
        // 获取缓存文件名（包含路径）
        $cacheFile = $this->getCacheFile($key);

        // 删除缓存文件
        return @unlink($cacheFile);
    }

    /**
     * 根据给出缓存键返回缓存文件路径
     * Returns the cache file path given the cache key.
     * @param string $key cache key
     * @return string the cache file path
     */
    protected function getCacheFile($key)
    {
        // 若目录级数大于0
        if ($this->directoryLevel > 0) {
            // 获取缓存根目录
            $base = $this->cachePath;
            for ($i = 0; $i < $this->directoryLevel; ++$i) {
                // 每级截取缓存键的两个字符作为目录名
                if (($prefix = substr($key, $i + $i, 2)) !== false) {
                    $base .= DIRECTORY_SEPARATOR . $prefix;
                }
            }

            // 拼接缓存文件的路径
            return $base . DIRECTORY_SEPARATOR . $key . $this->cacheFileSuffix;
        }

        // 若目录级数为0,则直接在缓存根目录下创建缓存文件
        return $this->cachePath . DIRECTORY_SEPARATOR . $key . $this->cacheFileSuffix;
    }

    /**
     * 清空所有缓存
     * 这是在父类中声明的方法的实现.
     * Deletes all values from cache.
     * This is the implementation of the method declared in the parent class.
     * @return bool whether the flush operation was successful.
     */
    protected function flushValues()
    {
        // 强制执行垃圾回收，删除所有缓存文件
        $this->gc(true, false);

        return true;
    }

    /**
     * 删除过期的缓存文件
     * Removes expired cache files.
     * @param bool $force whether to enforce the garbage collection regardless of [[gcProbability]].
     * Defaults to false, meaning the actual deletion happens with the probability as specified by [[gcProbability]].
     * @param bool $expiredOnly whether to removed expired cache files only.
     * If false, all cache files under [[cachePath]] will be removed.
     */
    public function gc($force = false, $expiredOnly = true)
    {
        if ($force || mt_rand(0, 1000000) < $this->gcProbability) {
            // 递归地删除缓存根目录下的所有缓存文件。
            $this->gcRecursive($this->cachePath, $expiredOnly);
        }
    }

    /**
     * 递归地删除目录下的缓存文件。
     * 这个方法主要用于[[gc()]]。
     * Recursively removing expired cache files under a directory.
     * This method is mainly used by [[gc()]].
     * @param string $path the directory under which expired cache files are removed.
     * @param bool $expiredOnly whether to only remove expired cache files. If false, all files
     * under `$path` will be removed.
     */
    protected function gcRecursive($path, $expiredOnly)
    {
        // 打开目录
        if (($handle = opendir($path)) !== false) {
            /**
             * 读取目录下的文件列表，例子：
             *
             *  string '.' (length=1)
                string '..' (length=2)
                string 'sql' (length=3)
                string 'memory.sql' (length=10)
                string 'user.sql' (length=8)

             */
            while (($file = readdir($handle)) !== false) {
                // $file 是字符串，$file[0] 是取字符串的第一个字符
                // 即忽略掉 '.' 和 '..' 两个目录
                if ($file[0] === '.') {
                    continue;
                }
                // 拼接下一级文件名或目录名
                $fullPath = $path . DIRECTORY_SEPARATOR . $file;
                // 如果是目录，则递归地执行该方法
                if (is_dir($fullPath)) {
                    $this->gcRecursive($fullPath, $expiredOnly);
                    // 如果并非只删除过期文件，则连同空目录一起删除。
                    // rmdir() 函数删除空的目录。
                    if (!$expiredOnly) {
                        if (!@rmdir($fullPath)) {
                            $error = error_get_last();
                            Yii::warning("Unable to remove directory '{$fullPath}': {$error['message']}", __METHOD__);
                        }
                    }
                // 若 $expiredOnly 为 false 或者 缓存文件已经过期，则直接删除文件
                } elseif (!$expiredOnly || $expiredOnly && @filemtime($fullPath) < time()) {
                    if (!@unlink($fullPath)) {
                        $error = error_get_last();
                        Yii::warning("Unable to remove file '{$fullPath}': {$error['message']}", __METHOD__);
                    }
                }
            }
            closedir($handle);
        }
    }
}
