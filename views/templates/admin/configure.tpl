{**
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
 *}

<div class="panel col-lg-12">
	<h3><i class="icon icon-refresh"></i> {l s='thirty bees updater' mod='tbupdater'}</h3>
	<div class="alert alert-info">{l s='This module keeps your modules updated. Click the button below to force an update check.' mod='tbupdater'}</div>
	<a href="{$baseUrl|escape:'htmlall':'UTF-8'}&checkForUpdates" class="btn btn-default"><i class="icon icon-refresh"></i> {l s='Check for module updates' mod='tbupdater'}</a>
</div>
