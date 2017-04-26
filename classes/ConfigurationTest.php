<?php
/**
 * 2007-2016 PrestaShop
 *
 * Thirty Bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017 Thirty Bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    Thirty Bees <modules@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017 Thirty Bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

namespace TbUpdaterModule;

/**
 * Class ConfigurationTestCore
 *
 * @since 1.0.0
 */
class ConfigurationTest
{
    /**
     * @param array $tests
     *
     * @return array
     *
     * @since 1.0.0
     */
    public static function check($tests)
    {
        $res = [];
        foreach ($tests as $key => $test) {
            $res[$key] = self::run($key, $test);
        }

        return $res;
    }

    /**
     * @param     $ptr
     * @param int $arg
     *
     * @return string
     *
     * @since 1.0.0
     */
    public static function run($ptr, $arg = 0)
    {
        if (call_user_func(['ConfigurationTest', 'test_'.$ptr], $arg)) {
            return 'ok';
        }

        return 'fail';
    }

    /**
     * @return mixed
     *
     * @since 1.0.0
     */
    public static function testPhpVersion()
    {
        return version_compare(substr(phpversion(), 0, 3), '5.4', '>=');
    }

    public static function testMysqlSupport()
    {
        return function_exists('mysql_connect');
    }

    public static function testUpload()
    {
        return ini_get('file_uploads');
    }

    public static function testFopen()
    {
        return ini_get('allow_url_fopen');
    }

    public static function testCurl()
    {
        return function_exists('curl_init');
    }

    public static function testSystem($funcs)
    {
        foreach ($funcs as $func) {
            if (!function_exists($func)) {
                return false;
            }
        }

        return true;
    }

    public static function testGd()
    {
        return function_exists('imagecreatetruecolor');
    }

    public static function testRegisterGlobals()
    {
        return !ini_get('register_globals');
    }

    static function testGz()
    {
        if (function_exists('gzencode')) {
            return !(@gzencode('dd') === false);
        }

        return false;
    }

    static function testConfigDir($dir)
    {
        return self::testDir($dir);
    }

    public static function testDir($relativeDir, $recursive = false, &$fullReport = null)
    {
        $dir = rtrim(_PS_ROOT_DIR_, '\\/').DIRECTORY_SEPARATOR.trim($relativeDir, '\\/');
        if (!file_exists($dir) || !$dh = opendir($dir)) {
            $fullReport = sprintf('Directory %s does not exist or is not writable', $dir); // sprintf for future translation

            return false;
        }
        $dummy = rtrim($dir, '\\/').DIRECTORY_SEPARATOR.uniqid();
        if (@file_put_contents($dummy, 'test')) {
            @unlink($dummy);
            if (!$recursive) {
                closedir($dh);

                return true;
            }
        } elseif (!is_writable($dir)) {
            $fullReport = sprintf('Directory %s is not writable', $dir); // sprintf for future translation

            return false;
        }

        if ($recursive) {
            while (($file = readdir($dh)) !== false) {
                if (is_dir($dir.DIRECTORY_SEPARATOR.$file) && $file != '.' && $file != '..' && $file != '.svn') {
                    if (!self::testDir($relativeDir.DIRECTORY_SEPARATOR.$file, $recursive, $fullReport)) {
                        return false;
                    }
                }
            }
        }

        closedir($dh);

        return true;
    }

    /**
     * @param string $dir
     *
     * @return bool
     *
     * @since 1.0.0
     */
    static function testSitemap($dir)
    {
        return self::testFile($dir);
    }

    /**
     * @param string $file
     *
     * @return bool
     *
     * @since 1.0.0
     */
    static function testFile($file)
    {
        return file_exists($file) && is_writable($file);
    }

    static function testRootDir($dir)
    {
        return self::testDir($dir);
    }

    static function testLogDir($dir)
    {
        return self::testDir($dir);
    }

    static function testAdminDir($dir)
    {
        return self::testDir($dir);
    }

    static function testImgDir($dir)
    {
        return self::testDir($dir, true);
    }

    static function testModuleDir($dir)
    {
        return self::testDir($dir, true);
    }

    static function testToolsDir($dir)
    {
        return self::testDir($dir);
    }

    static function testCacheDir($dir)
    {
        return self::testDir($dir);
    }

    static function testToolsV2Dir($dir)
    {
        return self::testDir($dir);
    }

    static function testCacheV2Dir($dir)
    {
        return self::testDir($dir);
    }

    static function testDownloadDir($dir)
    {
        return self::testDir($dir);
    }

    static function testMailsDir($dir)
    {
        return self::testDir($dir, true);
    }

    static function testTranslationsDir($dir)
    {
        return self::testDir($dir, true);
    }

    static function testThemeLangDir($dir)
    {
        if (!file_exists($dir)) {
            return true;
        }

        return self::testDir($dir, true);
    }

    static function testThemeCacheDir($dir)
    {
        if (!file_exists($dir)) {
            return true;
        }

        return self::testDir($dir, true);
    }

    static function testCustomizableProductsDir($dir)
    {
        return self::testDir($dir);
    }

    static function testVirtualProductsDir($dir)
    {
        return self::testDir($dir);
    }

    static function testMcrypt()
    {
        return function_exists('mcrypt_encrypt');
    }

    static function testDom()
    {
        return extension_loaded('Dom');
    }

    static function testMobile()
    {
        return true;
    }
}
