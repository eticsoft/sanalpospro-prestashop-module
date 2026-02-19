<?php

/**
 * @author    Eticsoft
 * @copyright 2024 Eticsoft
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */


if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminSanalPosProIapiController extends ModuleAdminController
{
    public function init()
    {
        parent::init();
    }

    public function display()
    {
        $api = \Eticsoft\Paythor\Sanalpospro\InternalApi::getInstance()->run();
        header('Content-Type: application/json');
        die(json_encode($api->response));
    }

    public function initContent()
    {
        parent::initContent();
        $api = \Eticsoft\Paythor\Sanalpospro\InternalApi::getInstance()->run();
        header('Content-Type: application/json');
        die(json_encode($api->response));
    }
}