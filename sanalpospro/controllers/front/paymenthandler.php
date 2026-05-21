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

        if (Tools::getValue('action') === 'callback') {
            return $this->callback();
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

    private function callback()
    {
        $requestData = json_decode(file_get_contents('php://input'), true);

        if (!is_array($requestData)) {
            header('HTTP/1.0 400 Bad Request');
            exit;
        }

        $cartId = (int) ($requestData['oid'] ?? 0);

        if (!$cartId) {
            header('HTTP/1.0 400 Bad Request');
            exit;
        }

        $orderId = (int) \Order::getIdByCartId($cartId);
        if ($orderId > 0) {
            $order = new \Order($orderId);
            $orderState = new \OrderState((int) $order->current_state);
            if ($orderState->paid) {
                http_response_code(200);
                header('Content-Type: application/json');
                die(json_encode(['status' => 'success']));
            }
        }

        $hash = $requestData['hash'] ?? '';
        if (!$hash) {
            header('HTTP/1.0 400 Bad Request');
            exit;
        }

        $cart = new \Cart($cartId);
        if (!\Validate::isLoadedObject($cart)) {
            header('HTTP/1.0 404 Not Found');
            exit;
        }

        \Context::getContext()->cart = $cart;

        $api = \Eticsoft\Paythor\Sanalpospro\InternalApi::getInstance()->setModule($this->module);
        $api->action = 'confirmOrder';
        $api->params = ['process_token' => $hash];
        $api->xfvv = \Eticsoft\Paythor\Sanalpospro\EticConfig::get('SANALPOSPRO_XFVV');
        $api->call();

        http_response_code(200);
        header('Content-Type: application/json');
        die(json_encode(['status' => 'success']));
    }
}
