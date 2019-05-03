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

use TbUpdaterModule\Tools;
use TbUpdaterModule\AjaxProcessor;

if (function_exists('opcache_reset')) {
    opcache_reset();
}
umask(0000);

if (function_exists('date_default_timezone_set')) {
    // date_default_timezone_get calls date_default_timezone_set, which can provide warning
    $timezone = @date_default_timezone_get();
    date_default_timezone_set($timezone);
}

require_once __DIR__.'/../../config/defines.inc.php';
require_once __DIR__.'/../../config/settings.inc.php';
require_once __DIR__.'/../../modules/tbupdater/classes/autoload.php';

// Note that this script is always located in the admin dir + /autoupgrade/
if (!defined('_PS_ROOT_DIR_')) {
    // 2 directories up
    define('_PS_ROOT_DIR_', realpath(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR));
}
if (!defined('_PS_MODULE_DIR_')) {
    define('_PS_MODULE_DIR_', _PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR);
}
if (!defined('_PS_TOOL_DIR_')) {
    define('_PS_TOOL_DIR_', _PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR);
}
define('AUTOUPGRADE_MODULE_DIR', _PS_MODULE_DIR_.'tbupdater'.DIRECTORY_SEPARATOR);
define('_PS_ADMIN_DIR_', realpath(__DIR__.DIRECTORY_SEPARATOR.'..'));
if (!defined('_MYSQL_ENGINE_')) {
    define('_MYSQL_ENGINE_', 'InnoDB');
}

require_once(AUTOUPGRADE_MODULE_DIR.'functions.php');

$request = json_decode(file_get_contents('php://input'));

// the following test confirm the directory exists
if (!isset($request->dir)) {
    die('no directory');
}

require_once(AUTOUPGRADE_MODULE_DIR.'alias.php');

$dir = Tools::safeOutput($request->dir);

if (_PS_ADMIN_DIR_ !== _PS_ROOT_DIR_.DIRECTORY_SEPARATOR.$dir) {
    die("wrong directory: $dir");
}

include(AUTOUPGRADE_MODULE_DIR.'init.php');

$ajaxUpgrader = AjaxProcessor::getInstance();

if (is_object($ajaxUpgrader) && $ajaxUpgrader->verifyToken()) {
    $ajaxUpgrader->optionDisplayErrors();

    // the differences with index.php is here
    $ajaxUpgrader->ajaxPreProcess();
    $action = $request->action;

    // no need to use displayConf() here

    if (!empty($action) && method_exists($ajaxUpgrader, 'ajaxProcess'.$action)) {
        $ajaxUpgrader->{'ajaxProcess'.$action}();
    } else {
        die(json_encode([
            'error' => true,
            'status'  => 'Method not found',
        ], JSON_PRETTY_PRINT));
    }

    if (!empty($action) && method_exists($ajaxUpgrader, 'displayAjax'.$action)) {
        $ajaxUpgrader->{'displayAjax'.$action}();
    } else {
        $ajaxUpgrader->displayAjax();
    }
}

die(json_encode([
    'error'  => true,
    'status' => 'Wrong token or request',
], JSON_PRETTY_PRINT));
