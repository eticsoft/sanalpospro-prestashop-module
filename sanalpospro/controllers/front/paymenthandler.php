<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class SanalPosProPaymentHandlerModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();


        if (Tools::getValue('action') === 'confirmOrder') {
            return $this->confirmOrder();
        }

        // Deny direct browser access
        if (
            !isset($_SERVER['HTTP_REFERER'])
            || strpos($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']) === false
        ) {
            header('HTTP/1.0 404 Not Found');
            exit;
        }

        $api = \Eticsoft\Paythor\Sanalpospro\InternalApi::getInstance()->setModule($this->module)->run();
        header('Content-Type: application/json');
        die(json_encode($api->response));
    }

    public function confirmOrder()
    {
        $process_token = Tools::getValue('p_id');
        $nonce = Tools::getValue('nonce');
        if (!isset($process_token)) {
            header('HTTP/1.0 404 Not Found');
            exit;
        }

        if (!isset($nonce)) {
            header('HTTP/1.0 404 Not Found');
            exit;
        }

        $api = \Eticsoft\Paythor\Sanalpospro\InternalApi::getInstance()->setModule($this->module);
        $api->action = 'confirmOrder';
        $api->params = ['process_token' => $process_token];
        $api->xfvv = $nonce;
        $api->call();

        if (isset($api->response["status"]) && isset($api->response["data"]["redirect_url"])) {
            header('Location: ' . $api->response["data"]["redirect_url"]);
            exit;
        }

        header('Content-Type: application/json');
        die(json_encode($api->response));
    }
}
