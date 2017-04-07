<?php

if (!defined('_TB_VERSION_')) {
    exit;
}

function upgrade_module_1_0_1($module)
{
    Configuration::deleteByName('TBUPDATER_AUTO_UPDATE');

    return true;
}
