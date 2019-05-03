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

<div id="upgradeButtonBlock" class="panel col-lg-12">
    <div class="panel-heading">
        <i class="icon icon-wrench"></i>
        {l s='Start your Upgrade' mod='tbupdater'}
    </div>

    <div class="blockOneClickUpgrade">
        <strong>{l s='Your current thirty bees version:' mod='tbupdater'}</strong>
        <code>{$currentVersion}</code>
    </div>
    {if $configOk}
        {include file='./channelselect.tpl'}

        <p class="clearfix configOk">
            <a href="" id="upgradeNow" class="upgradestep btn btn-primary btn-lg">
                <i class="icon icon-wrench"></i>
                {l s='Update thirty bees' mod='tbupdater'}
            </a>
        </p>
    {else}
        <strong>{l s='Make sure every item on the checklist is OK before continuing.' mod='tbupdater'}</strong>
    {/if}
</div>
