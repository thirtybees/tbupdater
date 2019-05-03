<?php
/**
 * Copyright (C) 2017-2019 thirty bees
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
 * @copyright 2017-2019 thirty bees
 * @license   Academic Free License (AFL 3.0)
 */

if (!function_exists('TbUpdaterModule\GuzzleHttp\uri_template')) {
    require __DIR__.'/GuzzleHttp/functions.php';
}
if (!function_exists('TbUpdaterModule\GuzzleHttp\Psr7\str')) {
    require __DIR__.'/GuzzleHttp/Psr7/functions.php';
}
if (!function_exists('TbUpdaterModule\GuzzleHttp\Promise\promise_for')) {
    require __DIR__.'/GuzzleHttp/Promise/functions.php';
}


spl_autoload_register(
    function ($class) {
        if (in_array($class, [
            'TbUpdaterModule\\AbstractLogger',
            'TbUpdaterModule\\AddConfToFile',
            'TbUpdaterModule\\AjaxProcessor',
            'TbUpdaterModule\\Archive_Tar',
            'TbUpdaterModule\\Backup',
            'TbUpdaterModule\\Blowfish',
            'TbUpdaterModule\\Cache',
            'TbUpdaterModule\\Configuration',
            'TbUpdaterModule\\ConfigurationTest',
            'TbUpdaterModule\\Context',
            'TbUpdaterModule\\CryptBlowfish',
            'TbUpdaterModule\\Db',
            'TbUpdaterModule\\DbPDO',
            'TbUpdaterModule\\DbQuery',
            'TbUpdaterModule\\Dispatcher',
            'TbUpdaterModule\\Employee',
            'TbUpdaterModule\\FileActions',
            'TbUpdaterModule\\FileLogger',
            'TbUpdaterModule\\Group',
            'TbUpdaterModule\\Hook',
            'TbUpdaterModule\\Language',
            'TbUpdaterModule\\ObjectModel',
            'TbUpdaterModule\\PEAR',
            'TbUpdaterModule\\PrestaShopCollection',
            'TbUpdaterModule\\Shop',
            'TbUpdaterModule\\ShopUrl',
            'TbUpdaterModule\\Tab',
            'TbUpdaterModule\\Tools',
            'TbUpdaterModule\\Translate',
            'TbUpdaterModule\\Upgrader',
            'TbUpdaterModule\\UpgraderTools',
            'TbUpdaterModule\\Validate',
            'TbUpdaterModule\\GuzzleHttp\\Client',
            'TbUpdaterModule\\GuzzleHttp\\ClientInterface',
            'TbUpdaterModule\\GuzzleHttp\\Cookie\\CookieJar',
            'TbUpdaterModule\\GuzzleHttp\\Cookie\\CookieJarInterface',
            'TbUpdaterModule\\GuzzleHttp\\Cookie\\FileCookieJar',
            'TbUpdaterModule\\GuzzleHttp\\Cookie\\SessionCookieJar',
            'TbUpdaterModule\\GuzzleHttp\\Cookie\\SetCookie',
            'TbUpdaterModule\\GuzzleHttp\\Exception\\BadResponseException',
            'TbUpdaterModule\\GuzzleHttp\\Exception\\ClientException',
            'TbUpdaterModule\\GuzzleHttp\\Exception\\ConnectException',
            'TbUpdaterModule\\GuzzleHttp\\Exception\\GuzzleException',
            'TbUpdaterModule\\GuzzleHttp\\Exception\\RequestException',
            'TbUpdaterModule\\GuzzleHttp\\Exception\\SeekException',
            'TbUpdaterModule\\GuzzleHttp\\Exception\\ServerException',
            'TbUpdaterModule\\GuzzleHttp\\Exception\\TooManyRedirectsException',
            'TbUpdaterModule\\GuzzleHttp\\Exception\\TransferException',
            'TbUpdaterModule\\GuzzleHttp\\Handler\\CurlFactory',
            'TbUpdaterModule\\GuzzleHttp\\Handler\\CurlFactoryInterface',
            'TbUpdaterModule\\GuzzleHttp\\Handler\\CurlHandler',
            'TbUpdaterModule\\GuzzleHttp\\Handler\\CurlMultiHandler',
            'TbUpdaterModule\\GuzzleHttp\\Handler\\EasyHandle',
            'TbUpdaterModule\\GuzzleHttp\\Handler\\MockHandler',
            'TbUpdaterModule\\GuzzleHttp\\Handler\\Proxy',
            'TbUpdaterModule\\GuzzleHttp\\Handler\\StreamHandler',
            'TbUpdaterModule\\GuzzleHttp\\HandlerStack',
            'TbUpdaterModule\\GuzzleHttp\\MessageFormatter',
            'TbUpdaterModule\\GuzzleHttp\\Middleware',
            'TbUpdaterModule\\GuzzleHttp\\Pool',
            'TbUpdaterModule\\GuzzleHttp\\PrepareBodyMiddleware',
            'TbUpdaterModule\\GuzzleHttp\\RedirectMiddleware',
            'TbUpdaterModule\\GuzzleHttp\\RequestOptions',
            'TbUpdaterModule\\GuzzleHttp\\RetryMiddleware',
            'TbUpdaterModule\\GuzzleHttp\\TransferStats',
            'TbUpdaterModule\\GuzzleHttp\\UriTemplate',
            'TbUpdaterModule\\GuzzleHttp\\Promise\\AggregateException',
            'TbUpdaterModule\\GuzzleHttp\\Promise\\CancellationException',
            'TbUpdaterModule\\GuzzleHttp\\Promise\\Coroutine',
            'TbUpdaterModule\\GuzzleHttp\\Promise\\EachPromise',
            'TbUpdaterModule\\GuzzleHttp\\Promise\\FulfilledPromise',
            'TbUpdaterModule\\GuzzleHttp\\Promise\\Promise',
            'TbUpdaterModule\\GuzzleHttp\\Promise\\PromiseInterface',
            'TbUpdaterModule\\GuzzleHttp\\Promise\\PromisorInterface',
            'TbUpdaterModule\\GuzzleHttp\\Promise\\RejectedPromise',
            'TbUpdaterModule\\GuzzleHttp\\Promise\\RejectionException',
            'TbUpdaterModule\\GuzzleHttp\\Promise\\TaskQueue',
            'TbUpdaterModule\\GuzzleHttp\\Promise\\TaskQueueInterface',
            'TbUpdaterModule\\GuzzleHttp\\Promise\\functions',
            'TbUpdaterModule\\GuzzleHttp\\Promise\\functions_include',
            'TbUpdaterModule\\GuzzleHttp\\Psr7\\AppendStream',
            'TbUpdaterModule\\GuzzleHttp\\Psr7\\BufferStream',
            'TbUpdaterModule\\GuzzleHttp\\Psr7\\CachingStream',
            'TbUpdaterModule\\GuzzleHttp\\Psr7\\DroppingStream',
            'TbUpdaterModule\\GuzzleHttp\\Psr7\\FnStream',
            'TbUpdaterModule\\GuzzleHttp\\Psr7\\InflateStream',
            'TbUpdaterModule\\GuzzleHttp\\Psr7\\LazyOpenStream',
            'TbUpdaterModule\\GuzzleHttp\\Psr7\\LimitStream',
            'TbUpdaterModule\\GuzzleHttp\\Psr7\\MessageTrait',
            'TbUpdaterModule\\GuzzleHttp\\Psr7\\MultipartStream',
            'TbUpdaterModule\\GuzzleHttp\\Psr7\\NoSeekStream',
            'TbUpdaterModule\\GuzzleHttp\\Psr7\\PumpStream',
            'TbUpdaterModule\\GuzzleHttp\\Psr7\\Request',
            'TbUpdaterModule\\GuzzleHttp\\Psr7\\Response',
            'TbUpdaterModule\\GuzzleHttp\\Psr7\\ServerRequest',
            'TbUpdaterModule\\GuzzleHttp\\Psr7\\Stream',
            'TbUpdaterModule\\GuzzleHttp\\Psr7\\StreamDecoratorTrait',
            'TbUpdaterModule\\GuzzleHttp\\Psr7\\StreamWrapper',
            'TbUpdaterModule\\GuzzleHttp\\Psr7\\UploadedFile',
            'TbUpdaterModule\\GuzzleHttp\\Psr7\\Uri',
            'TbUpdaterModule\\GuzzleHttp\\Psr7\\UriNormalizer',
            'TbUpdaterModule\\GuzzleHttp\\Psr7\\UriResolver',
            'TbUpdaterModule\\Psr\\Http\\Message\\MessageInterface',
            'TbUpdaterModule\\Psr\\Http\\Message\\RequestInterface',
            'TbUpdaterModule\\Psr\\Http\\Message\\ResponseInterface',
            'TbUpdaterModule\\Psr\\Http\\Message\\ServerRequestInterface',
            'TbUpdaterModule\\Psr\\Http\\Message\\StreamInterface',
            'TbUpdaterModule\\Psr\\Http\\Message\\UploadedFileInterface',
            'TbUpdaterModule\\Psr\\Http\\Message\\UriInterface',
            'TbUpdaterModule\\SemVer\\Expression',
            'TbUpdaterModule\\SemVer\\SemVerException',
            'TbUpdaterModule\\SemVer\\Version',
        ])) {
            // project-specific namespace prefix
            $prefix = 'TbUpdaterModule\\';

            // base directory for the namespace prefix
            $baseDir = __DIR__.'/';

            // does the class use the namespace prefix?
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                // no, move to the next registered autoloader
                return;
            }

            // get the relative class name
            $relativeClass = substr($class, $len);

            // replace the namespace prefix with the base directory, replace namespace
            // separators with directory separators in the relative class name, append
            // with .php
            $file = $baseDir.str_replace('\\', '/', $relativeClass).'.php';

            // if the file exists, require it
            require $file;
        }
    }
);
