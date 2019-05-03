<?php
/**
 * Copyright (C) 2017-2019 thirty bees
 * Copyright (C) 2007-2016 PrestaShop SA
 *
 * thirty bees is an extension to the PrestaShop software by PrestaShop SA.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017-2019 thirty bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   Academic Free License (AFL 3.0)
 * PrestaShop is an internationally registered trademark of PrestaShop SA.
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

    /**
     * Test if directory is writable
     *
     * @param string $dir        Directory path, absolute or relative
     * @param bool   $recursive
     * @param null   $fullReport
     * @param bool   $absolute   Is absolute path to directory
     *
     * @return bool
     *
     * @since   1.0.0 Added $absolute parameter
     * @version 1.0.0 Initial version
     */
    public static function testDir($dir, $recursive = false, &$fullReport = null, $absolute = false)
    {
        if ($absolute) {
            $absoluteDir = $dir;
        } else {
            $absoluteDir = rtrim(_PS_ROOT_DIR_, '\\/').DIRECTORY_SEPARATOR.trim($dir, '\\/');
        }

        if (!file_exists($absoluteDir)) {
            $fullReport = sprintf('Directory %s does not exist or is not writable', $absoluteDir);

            return false;
        }

        if (!is_writable($absoluteDir)) {
            $fullReport = sprintf('Directory %s is not writable', $absoluteDir);

            return false;
        }

        if ($recursive) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($absoluteDir)) as $file) {
                /** @var \SplFileInfo $file */
                if (in_array($file->getFilename(), ['.', '..']) || $file->isLink()) {
                    continue;
                }

                if (!is_writable($file)) {
                    $fullReport = sprintf('File %s is not writable', $file);

                    return false;
                }
            }
        }

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
