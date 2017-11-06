{*
 * 2007-2016 PrestaShop
 *
 * thirty bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017 thirty bees
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
 * @author    thirty bees <modules@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017 thirty bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * PrestaShop is an internationally registered trademark & property of PrestaShop SA
 *}

<div class="form-group">
    <label for="channelSelect">{l s='Channel:' mod='tbupdater'}</label>
    <select id="channelSelect" name="channel" class="form-control fixed-width-xxl">
        {foreach ['stable', 'rc', 'beta', 'alpha'] as $channel}
            <option id="use{$channel|ucfirst}" value="{$channel}">
                {$channel}
            </option>
        {/foreach}
    </select>
    <div id="channelSelectErrors" class="alert alert-danger" style="display:none"></div>
</div>
<strong>{l s='Going to update to thirty bees version:' mod='tbupdater'}</strong>
<em id="selectedVersion">...</em>