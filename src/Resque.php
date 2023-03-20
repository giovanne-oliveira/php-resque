<?php

/*
 * This file is part of the php-resque package.
 *
 * (c) Michael Haynes <mike@mjphaynes.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Resque\Redis;
use Resque\Queue;
use Resque\Helpers\Util;
use Symfony\Component\Yaml;

/**
 * Main Resque class
 *
 * @author Michael Haynes <mike@mjphaynes.com>
 */
class Resque
{
    /**
     * php-resque version
     */
    public const VERSION = '4.0.0';

    /**
     * How long the job and worker data will remain in Redis for
     * after completion/shutdown in seconds. Default is one week.
     */
    public const DEFAULT_EXPIRY_TIME = 604800;

    /**
     * Default config file name
     */
    public const DEFAULT_CONFIG_FILE = 'resque.yml';

    /**
     * @var array Configuration settings array.
     */
    protected static array $config = [];

    /**
     * @var Queue The queue instance.
     */
    protected static ?Queue $queue = null;

    /**
     * Create a queue instance.
     *
     * @return Queue
     */
    public static function queue(): Queue
    {
        if (!static::$queue) {
            static::$queue = new Queue();
        }

        return static::$queue;
    }

    /**
     * Dynamically pass calls to the default connection.
     *
     * @param string $method     The method to call
     * @param array  $parameters The parameters to pass
     *
     * @return mixed
     */
    public static function __callStatic(string $method, array $parameters)
    {
        $callable = [static::queue(), $method];

        return call_user_func_array($callable, $parameters);
    }

    /**
     * Reads and loads data from a config file
     *
     * @param string $file The config file path
     *
     * @return bool
     */
    public static function loadConfig(string $file = self::DEFAULT_CONFIG_FILE): bool
    {
        self::readConfigFile($file);

        Redis::setConfig([
            'scheme'     => static::getConfig('redis.scheme', Redis::DEFAULT_SCHEME),
            'host'       => static::getConfig('redis.host', Redis::DEFAULT_HOST),
            'port'       => static::getConfig('redis.port', Redis::DEFAULT_PORT),
            'namespace'  => static::getConfig('redis.namespace', Redis::DEFAULT_NS),
            'password'   => static::getConfig('redis.password', Redis::DEFAULT_PASSWORD),
            'rw_timeout' => static::getConfig('redis.rw_timeout', Redis::DEFAULT_RW_TIMEOUT),
            'phpiredis'  => static::getConfig('redis.phpiredis', Redis::DEFAULT_PHPIREDIS),
            'predis'     => static::getConfig('predis'),
        ]);

        return true;
    }

    /**
     * Reads data from a config file
     *
     * @param string $file The config file path
     *
     * @return array
     */
    public static function readConfigFile(string $file = self::DEFAULT_CONFIG_FILE): array
    {
        if (!is_string($file)) {
            throw new InvalidArgumentException('The config file path must be a string, type passed "'.gettype($file).'".');
        }

        $baseDir = realpath(dirname($file));
        $searchDirs = [
            $baseDir.'/',
            $baseDir.'/../',
            $baseDir.'/../../',
            $baseDir.'/config/',
            $baseDir.'/../config/',
            $baseDir.'/../../config/',
        ];

        $filename = basename($file);

        $configFile = null;
        foreach ($searchDirs as $dir) {
            if (realpath($dir.$filename) and is_readable($dir.$filename)) {
                $configFile = realpath($dir.$filename);
                break;
            }
        }

        if (is_null($configFile) and $file !== self::DEFAULT_CONFIG_FILE) {
            throw new InvalidArgumentException('The config file "'.$file.'" cannot be found or read.');
        }

        if (!$configFile) {
            return static::$config;
        }

        // Try to parse the contents
        try {
            $yaml = Yaml\Yaml::parse(file_get_contents($configFile));
        } catch (Yaml\Exception\ParseException $e) {
            throw new Exception('Unable to parse the config file: '.$e->getMessage());
        }

        return static::$config = $yaml;
    }

    /**
     * Gets Resque config variable
     *
     * @param string $key     The key to search for (optional)
     * @param mixed  $default If key not found returns this (optional)
     *
     * @return mixed
     */
    public static function getConfig(?string $key = null, $default = null)
    {
        if (!is_null($key)) {
            if (false !== Util::path(static::$config, $key, $found)) {
                return $found;
            } else {
                return $default;
            }
        }

        return static::$config;
    }

    /**
     * Gets Resque stats
     *
     * @return array
     */
    public static function stats(): array
    {
        return Redis::instance()->hgetall('stats');
    }
}
