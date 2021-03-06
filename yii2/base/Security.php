<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\base;

use Yii;
use yii\helpers\StringHelper;

/**
 * Security provides a set of methods to handle common security-related tasks.
 * Security提供了一系列的方法来处理公共的安全相关的任务。
 *
 * In particular, Security supports the following features:
 * 特别的，Security支持如下的特性：
 *
 * - Encryption/decryption: [[encryptByKey()]], [[decryptByKey()]], [[encryptByPassword()]] and [[decryptByPassword()]]
 * - 加密/解密: [[encryptByKey()]], [[decryptByKey()]], [[encryptByPassword()]] 和 [[decryptByPassword()]]
 *
 * - Key derivation using standard algorithms: [[pbkdf2()]] and [[hkdf()]]
 * - 使用标准算法的密钥导出：[[pbkdf2()]] 和 [[hkdf()]]
 *
 * - Data tampering prevention: [[hashData()]] and [[validateData()]]
 * - 预防数据篡改：[[hashData()]] 和 [[validateData()]]
 *
 * - Password validation: [[generatePasswordHash()]] and [[validatePassword()]]
 * - 密码验证：[[generatePasswordHash()]] 和 [[validatePassword()]]
 *
 * > Note: this class requires 'OpenSSL' PHP extension for random key/string generation on Windows and
 * for encryption/decryption on all platforms. For the highest security level PHP version >= 5.5.0 is recommended.
 * > 注意：该类需要开启OpenSSL PHP扩展，为windows平台生成随机的key/字符串，还有所有平台加密和解密。为了最高的安全级别，推荐php的版本大于等于5.5.0
 *
 * For more details and usage information on Security, see the [guide article on security](guide:security-overview).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Tom Worster <fsb@thefsb.org>
 * @author Klimov Paul <klimov.paul@gmail.com>
 * @since 2.0
 */
class Security extends Component
{
    /**
     * @var string The cipher to use for encryption and decryption.
     * 属性 字符串 用来加密解密的密码。
     */
    public $cipher = 'AES-128-CBC';
    /**
     * @var array[] Look-up table of block sizes and key sizes for each supported OpenSSL cipher.
     * 属性 数组 每个支持OpenSSL密码的块尺寸和键尺寸的查询表。
     *
     * In each element, the key is one of the ciphers supported by OpenSSL (@see openssl_get_cipher_methods()).
     * The value is an array of two integers, the first is the cipher's block size in bytes and the second is
     * the key size in bytes.
     *
     * > Warning: All OpenSSL ciphers that we recommend are in the default value, i.e. AES in CBC mode.
     *
     * > Note: Yii's encryption protocol uses the same size for cipher key, HMAC signature key and key
     * derivation salt.
     */
    public $allowedCiphers = [
        'AES-128-CBC' => [16, 16],
        'AES-192-CBC' => [16, 24],
        'AES-256-CBC' => [16, 32],
    ];
    /**
     * @var string Hash algorithm for key derivation. Recommend sha256, sha384 or sha512.
     * @see [hash_algos()](http://php.net/manual/en/function.hash-algos.php)
     */
    public $kdfHash = 'sha256';
    /**
     * 消息认证的散列算法。推荐sha256、sha384或sha512
     * @var string Hash algorithm for message authentication. Recommend sha256, sha384 or sha512.
     * @see [hash_algos()](http://php.net/manual/en/function.hash-algos.php)
     */
    public $macHash = 'sha256';
    /**
     * @var string HKDF info value for derivation of message authentication key.
     * @see hkdf()
     */
    public $authKeyInfo = 'AuthorizationKey';
    /**
     * @var int derivation iterations count.
     * Set as high as possible to hinder dictionary password attacks.
     */
    public $derivationIterations = 100000;
    /**
     * @var string strategy, which should be used to generate password hash.
     * Available strategies:
     * - 'password_hash' - use of PHP `password_hash()` function with PASSWORD_DEFAULT algorithm.
     *   This option is recommended, but it requires PHP version >= 5.5.0
     * - 'crypt' - use PHP `crypt()` function.
     * @deprecated since version 2.0.7, [[generatePasswordHash()]] ignores [[passwordHashStrategy]] and
     * uses `password_hash()` when available or `crypt()` when not.
     */
    public $passwordHashStrategy;
    /**
     * @var int Default cost used for password hashing.
     * Allowed value is between 4 and 31.
     * @see generatePasswordHash()
     * @since 2.0.6
     */
    public $passwordHashCost = 13;


    /**
     * Encrypts data using a password.
     * Derives keys for encryption and authentication from the password using PBKDF2 and a random salt,
     * which is deliberately slow to protect against dictionary attacks. Use [[encryptByKey()]] to
     * encrypt fast using a cryptographic key rather than a password. Key derivation time is
     * determined by [[$derivationIterations]], which should be set as high as possible.
     * The encrypted data includes a keyed message authentication code (MAC) so there is no need
     * to hash input or output data.
     * > Note: Avoid encrypting with passwords wherever possible. Nothing can protect against
     * poor-quality or compromised passwords.
     * @param string $data the data to encrypt
     * @param string $password the password to use for encryption
     * @return string the encrypted data
     * @see decryptByPassword()
     * @see encryptByKey()
     */
    public function encryptByPassword($data, $password)
    {
        return $this->encrypt($data, true, $password, null);
    }

    /**
     * Encrypts data using a cryptographic key.
     * Derives keys for encryption and authentication from the input key using HKDF and a random salt,
     * which is very fast relative to [[encryptByPassword()]]. The input key must be properly
     * random -- use [[generateRandomKey()]] to generate keys.
     * The encrypted data includes a keyed message authentication code (MAC) so there is no need
     * to hash input or output data.
     * @param string $data the data to encrypt
     * @param string $inputKey the input to use for encryption and authentication
     * @param string $info optional context and application specific information, see [[hkdf()]]
     * @return string the encrypted data
     * @see decryptByKey()
     * @see encryptByPassword()
     */
    public function encryptByKey($data, $inputKey, $info = null)
    {
        return $this->encrypt($data, false, $inputKey, $info);
    }

    /**
     * Verifies and decrypts data encrypted with [[encryptByPassword()]].
     * @param string $data the encrypted data to decrypt
     * @param string $password the password to use for decryption
     * @return bool|string the decrypted data or false on authentication failure
     * @see encryptByPassword()
     */
    public function decryptByPassword($data, $password)
    {
        return $this->decrypt($data, true, $password, null);
    }

    /**
     * Verifies and decrypts data encrypted with [[encryptByKey()]].
     * @param string $data the encrypted data to decrypt
     * @param string $inputKey the input to use for encryption and authentication
     * @param string $info optional context and application specific information, see [[hkdf()]]
     * @return bool|string the decrypted data or false on authentication failure
     * @see encryptByKey()
     */
    public function decryptByKey($data, $inputKey, $info = null)
    {
        return $this->decrypt($data, false, $inputKey, $info);
    }

    /**
     * Encrypts data.
     *
     * @param string $data data to be encrypted
     * @param bool $passwordBased set true to use password-based key derivation
     * @param string $secret the encryption password or key
     * @param string|null $info context/application specific information, e.g. a user ID
     * See [RFC 5869 Section 3.2](https://tools.ietf.org/html/rfc5869#section-3.2) for more details.
     *
     * @return string the encrypted data
     * @throws InvalidConfigException on OpenSSL not loaded
     * @throws Exception on OpenSSL error
     * @see decrypt()
     */
    protected function encrypt($data, $passwordBased, $secret, $info)
    {
        if (!extension_loaded('openssl')) {
            throw new InvalidConfigException('Encryption requires the OpenSSL PHP extension');
        }
        if (!isset($this->allowedCiphers[$this->cipher][0], $this->allowedCiphers[$this->cipher][1])) {
            throw new InvalidConfigException($this->cipher . ' is not an allowed cipher');
        }

        list($blockSize, $keySize) = $this->allowedCiphers[$this->cipher];

        $keySalt = $this->generateRandomKey($keySize);
        if ($passwordBased) {
            $key = $this->pbkdf2($this->kdfHash, $secret, $keySalt, $this->derivationIterations, $keySize);
        } else {
            $key = $this->hkdf($this->kdfHash, $secret, $keySalt, $info, $keySize);
        }

        $iv = $this->generateRandomKey($blockSize);

        $encrypted = openssl_encrypt($data, $this->cipher, $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new \yii\base\Exception('OpenSSL failure on encryption: ' . openssl_error_string());
        }

        $authKey = $this->hkdf($this->kdfHash, $key, null, $this->authKeyInfo, $keySize);
        $hashed = $this->hashData($iv . $encrypted, $authKey);

        /*
         * Output: [keySalt][MAC][IV][ciphertext]
         * - keySalt is KEY_SIZE bytes long
         * - MAC: message authentication code, length same as the output of MAC_HASH
         * - IV: initialization vector, length $blockSize
         */
        return $keySalt . $hashed;
    }

    /**
     * Decrypts data.
     *
     * @param string $data encrypted data to be decrypted.
     * @param bool $passwordBased set true to use password-based key derivation
     * @param string $secret the decryption password or key
     * @param string|null $info context/application specific information, @see encrypt()
     *
     * @return bool|string the decrypted data or false on authentication failure
     * @throws InvalidConfigException on OpenSSL not loaded
     * @throws Exception on OpenSSL error
     * @see encrypt()
     */
    protected function decrypt($data, $passwordBased, $secret, $info)
    {
        if (!extension_loaded('openssl')) {
            throw new InvalidConfigException('Encryption requires the OpenSSL PHP extension');
        }
        if (!isset($this->allowedCiphers[$this->cipher][0], $this->allowedCiphers[$this->cipher][1])) {
            throw new InvalidConfigException($this->cipher . ' is not an allowed cipher');
        }

        list($blockSize, $keySize) = $this->allowedCiphers[$this->cipher];

        $keySalt = StringHelper::byteSubstr($data, 0, $keySize);
        if ($passwordBased) {
            $key = $this->pbkdf2($this->kdfHash, $secret, $keySalt, $this->derivationIterations, $keySize);
        } else {
            $key = $this->hkdf($this->kdfHash, $secret, $keySalt, $info, $keySize);
        }

        $authKey = $this->hkdf($this->kdfHash, $key, null, $this->authKeyInfo, $keySize);
        $data = $this->validateData(StringHelper::byteSubstr($data, $keySize, null), $authKey);
        if ($data === false) {
            return false;
        }

        $iv = StringHelper::byteSubstr($data, 0, $blockSize);
        $encrypted = StringHelper::byteSubstr($data, $blockSize, null);

        $decrypted = openssl_decrypt($encrypted, $this->cipher, $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            throw new \yii\base\Exception('OpenSSL failure on decryption: ' . openssl_error_string());
        }

        return $decrypted;
    }

    /**
     * Derives a key from the given input key using the standard HKDF algorithm.
     * Implements HKDF specified in [RFC 5869](https://tools.ietf.org/html/rfc5869).
     * Recommend use one of the SHA-2 hash algorithms: sha224, sha256, sha384 or sha512.
     * @param string $algo a hash algorithm supported by `hash_hmac()`, e.g. 'SHA-256'
     * @param string $inputKey the source key
     * @param string $salt the random salt
     * @param string $info optional info to bind the derived key material to application-
     * and context-specific information, e.g. a user ID or API version, see
     * [RFC 5869](https://tools.ietf.org/html/rfc5869)
     * @param int $length length of the output key in bytes. If 0, the output key is
     * the length of the hash algorithm output.
     * @throws InvalidArgumentException when HMAC generation fails.
     * @return string the derived key
     */
    public function hkdf($algo, $inputKey, $salt = null, $info = null, $length = 0)
    {
        if (function_exists('hash_hkdf')) {
            $outputKey = hash_hkdf($algo, $inputKey, $length, $info, $salt);
            if ($outputKey === false) {
                throw new InvalidArgumentException('Invalid parameters to hash_hkdf()');
            }

            return $outputKey;
        }

        $test = @hash_hmac($algo, '', '', true);
        if (!$test) {
            throw new InvalidArgumentException('Failed to generate HMAC with hash algorithm: ' . $algo);
        }
        $hashLength = StringHelper::byteLength($test);
        if (is_string($length) && preg_match('{^\d{1,16}$}', $length)) {
            $length = (int) $length;
        }
        if (!is_int($length) || $length < 0 || $length > 255 * $hashLength) {
            throw new InvalidArgumentException('Invalid length');
        }
        $blocks = $length !== 0 ? ceil($length / $hashLength) : 1;

        if ($salt === null) {
            $salt = str_repeat("\0", $hashLength);
        }
        $prKey = hash_hmac($algo, $inputKey, $salt, true);

        $hmac = '';
        $outputKey = '';
        for ($i = 1; $i <= $blocks; $i++) {
            $hmac = hash_hmac($algo, $hmac . $info . chr($i), $prKey, true);
            $outputKey .= $hmac;
        }

        if ($length !== 0) {
            $outputKey = StringHelper::byteSubstr($outputKey, 0, $length);
        }

        return $outputKey;
    }

    /**
     * Derives a key from the given password using the standard PBKDF2 algorithm.
     * Implements HKDF2 specified in [RFC 2898](http://tools.ietf.org/html/rfc2898#section-5.2)
     * Recommend use one of the SHA-2 hash algorithms: sha224, sha256, sha384 or sha512.
     * @param string $algo a hash algorithm supported by `hash_hmac()`, e.g. 'SHA-256'
     * @param string $password the source password
     * @param string $salt the random salt
     * @param int $iterations the number of iterations of the hash algorithm. Set as high as
     * possible to hinder dictionary password attacks.
     * @param int $length length of the output key in bytes. If 0, the output key is
     * the length of the hash algorithm output.
     * @return string the derived key
     * @throws InvalidArgumentException when hash generation fails due to invalid params given.
     */
    public function pbkdf2($algo, $password, $salt, $iterations, $length = 0)
    {
        if (function_exists('hash_pbkdf2')) {
            $outputKey = hash_pbkdf2($algo, $password, $salt, $iterations, $length, true);
            if ($outputKey === false) {
                throw new InvalidArgumentException('Invalid parameters to hash_pbkdf2()');
            }

            return $outputKey;
        }

        // todo: is there a nice way to reduce the code repetition in hkdf() and pbkdf2()?
        $test = @hash_hmac($algo, '', '', true);
        if (!$test) {
            throw new InvalidArgumentException('Failed to generate HMAC with hash algorithm: ' . $algo);
        }
        if (is_string($iterations) && preg_match('{^\d{1,16}$}', $iterations)) {
            $iterations = (int) $iterations;
        }
        if (!is_int($iterations) || $iterations < 1) {
            throw new InvalidArgumentException('Invalid iterations');
        }
        if (is_string($length) && preg_match('{^\d{1,16}$}', $length)) {
            $length = (int) $length;
        }
        if (!is_int($length) || $length < 0) {
            throw new InvalidArgumentException('Invalid length');
        }
        $hashLength = StringHelper::byteLength($test);
        $blocks = $length !== 0 ? ceil($length / $hashLength) : 1;

        $outputKey = '';
        for ($j = 1; $j <= $blocks; $j++) {
            $hmac = hash_hmac($algo, $salt . pack('N', $j), $password, true);
            $xorsum = $hmac;
            for ($i = 1; $i < $iterations; $i++) {
                $hmac = hash_hmac($algo, $hmac, $password, true);
                $xorsum ^= $hmac;
            }
            $outputKey .= $xorsum;
        }

        if ($length !== 0) {
            $outputKey = StringHelper::byteSubstr($outputKey, 0, $length);
        }

        return $outputKey;
    }

    /**
     * Prefixes data with a keyed hash value so that it can later be detected if it is tampered.
     * There is no need to hash inputs or outputs of [[encryptByKey()]] or [[encryptByPassword()]]
     * as those methods perform the task.
     * @param string $data the data to be protected
     * @param string $key the secret key to be used for generating hash. Should be a secure
     * cryptographic key.
     * @param bool $rawHash whether the generated hash value is in raw binary format. If false, lowercase
     * hex digits will be generated.
     * @return string the data prefixed with the keyed hash
     * @throws InvalidConfigException when HMAC generation fails.
     * @see validateData()
     * @see generateRandomKey()
     * @see hkdf()
     * @see pbkdf2()
     */
    public function hashData($data, $key, $rawHash = false)
    {
        $hash = hash_hmac($this->macHash, $data, $key, $rawHash);
        if (!$hash) {
            throw new InvalidConfigException('Failed to generate HMAC with hash algorithm: ' . $this->macHash);
        }

        return $hash . $data;
    }

    /**
     * 验证给定数据是否被篡改
     * Validates if the given data is tampered.
     * 要验证的数据。数据必须是由[[hashData()]]生成的。
     * @param string $data the data to be validated. The data must be previously
     * generated by [[hashData()]].
     * 之前用于在[[hashData()]]中为数据生成散列的密钥。
     * 函数查看系统上支持的散列算法。
     * 这必须与在为数据生成散列时传递给[[hashData()]]的值相同。
     * @param string $key the secret key that was previously used to generate the hash for the data in [[hashData()]].
     * function to see the supported hashing algorithms on your system. This must be the same
     * as the value passed to [[hashData()]] when generating the hash for the data.
     * 这应该与使用[[hashData()]]生成数据时的值相同。
     * 它表示数据中的散列值是否为二进制格式。
     * 如果是false，则意味着哈希值只包含小写的十六进制数字。
     * 将生成十六进制数字。
     * @param bool $rawHash this should take the same value as when you generate the data using [[hashData()]].
     * It indicates whether the hash value in the data is in binary format. If false, it means the hash value consists
     * of lowercase hex digits only.
     * hex digits will be generated.
     * 返回被剥离了散列的实际数据。如果数据被篡改，则返回false
     * @return string|false the real data with the hash stripped off. False if the data is tampered.
     * @throws InvalidConfigException when HMAC generation fails.
     * @see hashData()
     */
    public function validateData($data, $key, $rawHash = false)
    {
        /**
         * 使用 HMAC 方法生成带有密钥的哈希值
         * @see http://php.net/manual/zh/function.hash-hmac.php
         *
         */
        $test = @hash_hmac($this->macHash, '', '', $rawHash);
        if (!$test) {
            throw new InvalidConfigException('Failed to generate HMAC with hash algorithm: ' . $this->macHash);
        }
        // 获取$test中的字节数
        $hashLength = StringHelper::byteLength($test);
        // 若$data字节数大于$test中的字节数
        if (StringHelper::byteLength($data) >= $hashLength) {
            // 截取$data的前n个字符为散列值
            $hash = StringHelper::byteSubstr($data, 0, $hashLength);
            // 截取之后的字符为有效数据
            $pureData = StringHelper::byteSubstr($data, $hashLength, null);

            // 重新使用 HMAC 方法生成带有密钥的哈希值
            $calculatedHash = hash_hmac($this->macHash, $pureData, $key, $rawHash);

            // 使用抗时序攻击方法比较两个散列值是否完全相同
            if ($this->compareString($hash, $calculatedHash)) {
                return $pureData;
            }
        }

        return false;
    }

    private $_useLibreSSL;
    private $_randomFile;

    /**
     * 生成指定数量的随机字节
     * 注意，输出的可能不是ASCII
     * Generates specified number of random bytes.
     * Note that output may not be ASCII.
     *
     * 如果你需要字符串
     * @see generateRandomString() if you need a string.
     *
     * 生成的字节数
     * @param int $length the number of bytes to generate
     * 生成的随机字节
     * @return string the generated random bytes
     * @throws InvalidArgumentException if wrong length is specified
     * @throws Exception on failure.
     */
    public function generateRandomKey($length = 32)
    {
        if (!is_int($length)) {
            throw new InvalidArgumentException('First parameter ($length) must be an integer');
        }

        if ($length < 1) {
            throw new InvalidArgumentException('First parameter ($length) must be greater than 0');
        }

        // always use random_bytes() if it is available
        /**
         * @link http://php.net/manual/zh/function.random-bytes.php
         * 判断是否存在 random_bytes() 函数，若存在，直接返回该函数生成的字节
         * 该函数是PHP7开始引入
         */
        if (function_exists('random_bytes')) {
            return random_bytes($length);
        }

        // The recent LibreSSL RNGs are faster and likely better than /dev/urandom.
        // Parse OPENSSL_VERSION_TEXT because OPENSSL_VERSION_NUMBER is no use for LibreSSL.
        // https://bugs.php.net/bug.php?id=71143
        // 最近的LibreSSL RNGs 比 /dev/urandom 更快，而且可能更好。
        // 解析 OPENSSL_VERSION_TEXT，因为 OPENSSL_VERSION_NUMBER 对 LibreSSL 没有任何用处.
        if ($this->_useLibreSSL === null) {
            // 判断是否定义了 OPENSSL_VERSION_TEXT 常量
            $this->_useLibreSSL = defined('OPENSSL_VERSION_TEXT')
                && preg_match('{^LibreSSL (\d\d?)\.(\d\d?)\.(\d\d?)$}', OPENSSL_VERSION_TEXT, $matches)
                && (10000 * $matches[1]) + (100 * $matches[2]) + $matches[3] >= 20105;
        }

        // Since 5.4.0, openssl_random_pseudo_bytes() reads from CryptGenRandom on Windows instead
        // of using OpenSSL library. LibreSSL is OK everywhere but don't use OpenSSL on non-Windows.
        // 自从 PHP 5.4.0版本起， openssl_random_pseudo_bytes() 在 Windows 环境下由原来的 从 OpenSSL library 读取改为了 从 CryptGenRandom读取。
        // LibreSSL 在任何环境都可以使用，但时不要在非windows上使用OpenSSL
        // openssl_random_pseudo_bytes() 函数存在
        if (function_exists('openssl_random_pseudo_bytes')
            && ($this->_useLibreSSL
            || (
                // 目录分隔符不为 '/'：'/'为Linux环境目录分隔符，Windows中为 '\'
                DIRECTORY_SEPARATOR !== '/'
                // 所在操作系统为 Windows
                && substr_compare(PHP_OS, 'win', 0, 3, true) === 0
            ))
        ) {
            /**
             * @link http://php.net/manual/zh/function.openssl-random-pseudo-bytes.php
             * openssl_random_pseudo_bytes() 生成一串伪随机字节，由长度参数确定字节数
             *
             * 关于第二个参数 $cryptoStrong
             * 如果传递到函数中，它将会被赋予一个布尔值，该值决定该算法的密码强度
             */
            $key = openssl_random_pseudo_bytes($length, $cryptoStrong);
            if ($cryptoStrong === false) {
                throw new Exception(
                    // PHP设置不安全
                    'openssl_random_pseudo_bytes() set $crypto_strong false. Your PHP setup is insecure.'
                );
            }
            // 若 $key 不为 false,且 $key 长度符合要求，则直接返回该 $key
            if ($key !== false && StringHelper::byteLength($key) === $length) {
                return $key;
            }
        }

        // mcrypt_create_iv() does not use libmcrypt. Since PHP 5.3.7 it directly reads
        // CryptGenRandom on Windows. Elsewhere it directly reads /dev/urandom.
        // mcrypt_create_iv() 不使用 libmcrypt。
        // 自从 PHP 5.3.7，它会在Windows中直接读取CryptGenRandom
        // 在其他地方，它直接读取/dev/urandom
        /**
         * @link http://php.net/manual/zh/function.mcrypt-create-iv.php
         * mcrypt_create_iv() 从随机源创建初始向量.
         * 初始向量只是为了给加密算法提供一个可用的种子， 所以它不需要安全保护，
         * 你甚至可以随同密文一起发布初始向量也不会对安全性带来影响。
         */
        if (function_exists('mcrypt_create_iv')) {
            $key = mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);
            if (StringHelper::byteLength($key) === $length) {
                return $key;
            }
        }

        // If not on Windows, try to open a random device.
        // 如果不是在Windows上，尝试打开一个随机的设备
        if ($this->_randomFile === null && DIRECTORY_SEPARATOR === '/') {
            // urandom is a symlink to random on FreeBSD.
            $device = PHP_OS === 'FreeBSD' ? '/dev/random' : '/dev/urandom';
            // Check random device for special character device protection mode. Use lstat()
            // instead of stat() in case an attacker arranges a symlink to a fake device.
            $lstat = @lstat($device);
            if ($lstat !== false && ($lstat['mode'] & 0170000) === 020000) {
                $this->_randomFile = fopen($device, 'rb') ?: null;

                if (is_resource($this->_randomFile)) {
                    // Reduce PHP stream buffer from default 8192 bytes to optimize data
                    // transfer from the random device for smaller values of $length.
                    // This also helps to keep future randoms out of user memory space.
                    $bufferSize = 8;

                    if (function_exists('stream_set_read_buffer')) {
                        stream_set_read_buffer($this->_randomFile, $bufferSize);
                    }
                    // stream_set_read_buffer() isn't implemented on HHVM
                    if (function_exists('stream_set_chunk_size')) {
                        stream_set_chunk_size($this->_randomFile, $bufferSize);
                    }
                }
            }
        }

        if (is_resource($this->_randomFile)) {
            $buffer = '';
            $stillNeed = $length;
            while ($stillNeed > 0) {
                $someBytes = fread($this->_randomFile, $stillNeed);
                if ($someBytes === false) {
                    break;
                }
                $buffer .= $someBytes;
                $stillNeed -= StringHelper::byteLength($someBytes);
                if ($stillNeed === 0) {
                    // Leaving file pointer open in order to make next generation faster by reusing it.
                    return $buffer;
                }
            }
            fclose($this->_randomFile);
            $this->_randomFile = null;
        }

        throw new Exception('Unable to generate a random key');
    }

    /**
     * 生成指定长度的随机字符串。
     * 会生成的匹配 [A-Za-z0-9_-]+ 的字符串，url编码对该字符串不会有任何改变。
     * Generates a random string of specified length.
     * The string generated matches [A-Za-z0-9_-]+ and is transparent to URL-encoding.
     *
     * @param int $length the length of the key in characters
     * @return string the generated random key
     * @throws Exception on failure.
     */
    public function generateRandomString($length = 32)
    {
        if (!is_int($length)) {
            // 必须是 int
            throw new InvalidArgumentException('First parameter ($length) must be an integer');
        }

        if ($length < 1) {
            // 长度必须大于0
            throw new InvalidArgumentException('First parameter ($length) must be greater than 0');
        }

        $bytes = $this->generateRandomKey($length);
        return substr(StringHelper::base64UrlEncode($bytes), 0, $length);
    }

    /**
     * Generates a secure hash from a password and a random salt.
     *
     * The generated hash can be stored in database.
     * Later when a password needs to be validated, the hash can be fetched and passed
     * to [[validatePassword()]]. For example,
     *
     * ```php
     * // generates the hash (usually done during user registration or when the password is changed)
     * $hash = Yii::$app->getSecurity()->generatePasswordHash($password);
     * // ...save $hash in database...
     *
     * // during login, validate if the password entered is correct using $hash fetched from database
     * if (Yii::$app->getSecurity()->validatePassword($password, $hash)) {
     *     // password is good
     * } else {
     *     // password is bad
     * }
     * ```
     *
     * @param string $password The password to be hashed.
     * @param int $cost Cost parameter used by the Blowfish hash algorithm.
     * The higher the value of cost,
     * the longer it takes to generate the hash and to verify a password against it. Higher cost
     * therefore slows down a brute-force attack. For best protection against brute-force attacks,
     * set it to the highest value that is tolerable on production servers. The time taken to
     * compute the hash doubles for every increment by one of $cost.
     * @return string The password hash string. When [[passwordHashStrategy]] is set to 'crypt',
     * the output is always 60 ASCII characters, when set to 'password_hash' the output length
     * might increase in future versions of PHP (http://php.net/manual/en/function.password-hash.php)
     * @throws Exception on bad password parameter or cost parameter.
     * @see validatePassword()
     */
    public function generatePasswordHash($password, $cost = null)
    {
        if ($cost === null) {
            $cost = $this->passwordHashCost;
        }

        if (function_exists('password_hash')) {
            /* @noinspection PhpUndefinedConstantInspection */
            return password_hash($password, PASSWORD_DEFAULT, ['cost' => $cost]);
        }

        $salt = $this->generateSalt($cost);
        $hash = crypt($password, $salt);
        // strlen() is safe since crypt() returns only ascii
        if (!is_string($hash) || strlen($hash) !== 60) {
            throw new Exception('Unknown error occurred while generating hash.');
        }

        return $hash;
    }

    /**
     * Verifies a password against a hash.
     * @param string $password The password to verify.
     * @param string $hash The hash to verify the password against.
     * @return bool whether the password is correct.
     * @throws InvalidArgumentException on bad password/hash parameters or if crypt() with Blowfish hash is not available.
     * @see generatePasswordHash()
     */
    public function validatePassword($password, $hash)
    {
        if (!is_string($password) || $password === '') {
            throw new InvalidArgumentException('Password must be a string and cannot be empty.');
        }

        if (!preg_match('/^\$2[axy]\$(\d\d)\$[\.\/0-9A-Za-z]{22}/', $hash, $matches)
            || $matches[1] < 4
            || $matches[1] > 30
        ) {
            throw new InvalidArgumentException('Hash is invalid.');
        }

        if (function_exists('password_verify')) {
            return password_verify($password, $hash);
        }

        $test = crypt($password, $hash);
        $n = strlen($test);
        if ($n !== 60) {
            return false;
        }

        return $this->compareString($test, $hash);
    }

    /**
     * Generates a salt that can be used to generate a password hash.
     *
     * The PHP [crypt()](http://php.net/manual/en/function.crypt.php) built-in function
     * requires, for the Blowfish hash algorithm, a salt string in a specific format:
     * "$2a$", "$2x$" or "$2y$", a two digit cost parameter, "$", and 22 characters
     * from the alphabet "./0-9A-Za-z".
     *
     * @param int $cost the cost parameter
     * @return string the random salt value.
     * @throws InvalidArgumentException if the cost parameter is out of the range of 4 to 31.
     */
    protected function generateSalt($cost = 13)
    {
        $cost = (int) $cost;
        if ($cost < 4 || $cost > 31) {
            throw new InvalidArgumentException('Cost must be between 4 and 31.');
        }

        // Get a 20-byte random string
        $rand = $this->generateRandomKey(20);
        // Form the prefix that specifies Blowfish (bcrypt) algorithm and cost parameter.
        $salt = sprintf('$2y$%02d$', $cost);
        // Append the random salt data in the required base64 format.
        $salt .= str_replace('+', '.', substr(base64_encode($rand), 0, 22));

        return $salt;
    }

    /**
     * 使用抗时序攻击方法执行字符串比较。
     * 关于时序攻击：@see https://www.zhihu.com/question/20156213/answer/45371668
     * Performs string comparison using timing attack resistant approach.
     * @see http://codereview.stackexchange.com/questions/13512
     * @param string $expected string to compare.
     * @param string $actual user-supplied string.
     * @return bool whether strings are equal.
     */
    public function compareString($expected, $actual)
    {
        if (!is_string($expected)) {
            throw new InvalidArgumentException('Expected expected value to be a string, ' . gettype($expected) . ' given.');
        }

        if (!is_string($actual)) {
            throw new InvalidArgumentException('Expected actual value to be a string, ' . gettype($actual) . ' given.');
        }

        if (function_exists('hash_equals')) {
            return hash_equals($expected, $actual);
        }

        $expected .= "\0";
        $actual .= "\0";
        // 字符串字节数
        $expectedLength = StringHelper::byteLength($expected);
        $actualLength = StringHelper::byteLength($actual);
        // 长度差值
        $diff = $expectedLength - $actualLength;
        for ($i = 0; $i < $actualLength; $i++) {
            // ord() 返回字符的ASCII值
            $diff |= (ord($actual[$i]) ^ ord($expected[$i % $expectedLength]));
        }

        return $diff === 0;
    }

    /**
     * Masks a token to make it uncompressible.
     * Applies a random mask to the token and prepends the mask used to the result making the string always unique.
     * Used to mitigate BREACH attack by randomizing how token is outputted on each request.
     * @param string $token An unmasked token.
     * @return string A masked token.
     * @since 2.0.12
     */
    public function maskToken($token)
    {
        // The number of bytes in a mask is always equal to the number of bytes in a token.
        $mask = $this->generateRandomKey(StringHelper::byteLength($token));
        return StringHelper::base64UrlEncode($mask . ($mask ^ $token));
    }

    /**
     * Unmasks a token previously masked by `maskToken`.
     * @param string $maskedToken A masked token.
     * @return string An unmasked token, or an empty string in case of token format is invalid.
     * @since 2.0.12
     */
    public function unmaskToken($maskedToken)
    {
        $decoded = StringHelper::base64UrlDecode($maskedToken);
        $length = StringHelper::byteLength($decoded) / 2;
        // Check if the masked token has an even length.
        if (!is_int($length)) {
            return '';
        }

        return StringHelper::byteSubstr($decoded, $length, $length) ^ StringHelper::byteSubstr($decoded, 0, $length);
    }
}
