<?php

namespace Eticsoft\Paythor\Sanalpospro;

use Eticsoft\Paythor\Sanalpospro\EticContext;
use Eticsoft\Paythor\Sanalpospro\EticConfig;
use Eticsoft\Paythor\Sanalpospro\EticTools;
use Eticsoft\Paythor\Sanalpospro\Payment;
use Eticsoft\Paythor\Sanalpospro\ApiClient;

use Eticsoft\Sanalpospro\Common\Models\Cart;
use Eticsoft\Sanalpospro\Common\Models\Payer;
use Eticsoft\Sanalpospro\Common\Models\Order;
use Eticsoft\Sanalpospro\Common\Models\Invoice;
use Eticsoft\Sanalpospro\Common\Models\Address;
use Eticsoft\Sanalpospro\Common\Models\Shipping;
use Eticsoft\Sanalpospro\Common\Models\PaymentRequest;
use Eticsoft\Sanalpospro\Common\Models\PaymentModel;
use Eticsoft\Sanalpospro\Common\Models\CartItem;



class InternalApi
{

    public ?string $action = '';
    public ?string $payload = '';
    public ?array $params = [];
    public ?array $response = [
        'status' => 'error',
        'message' => 'Internal error',
        'data' => [],
        'xfvv' => '',
    ];
    public ?string $xfvv = '';

    public $module;

    public function run(): self
    {
        $this->setAction()->setParams()->setXfvv()->call();
        return $this;
    }

    public static function getInstance(): self
    {
        return new self();
    }

    public function setAction(): self
    {
        $this->action = EticTools::postVal('iapi_action', false);
        return $this;
    }

    public function setXfvv(): self
    {
        $this->xfvv = EticTools::postVal('iapi_xfvv', false);
        return $this;
    }

    public function setParams(): self
    {
        $params = EticTools::postVal('iapi_params', '');
        $this->params = json_decode($params, true);
        return $this;
    }

    public function setModule($module): self
    {
        $this->module = $module;
        return $this;
    }

    public function call(): self
    {
        if (!$this->action) {
            return $this->setResponse('error', 'Action not found. #' . $this->action);
        }
        //make action first letter uppercase
        $this->action = ucfirst($this->action);
        if (!method_exists($this, 'action' . $this->action)) {
            return $this->setResponse('error', 'Action func not found. #' . 'action' . $this->action);
        }
        if ($this->xfvv != EticConfig::get('SANALPOSPRO_XFVV')) {
            return $this->setResponse('error', 'XFVV not matched');
        }
        $f_name = 'action' . $this->action;
        return $this->$f_name();
    }

    public function setResponse(string $status = 'success', string $message = '', array $data = [], array $details = [], array $meta = []): self
    {
        $this->response = [
            'status' => $status,
            'message' => $message,
            'data' => $data,
            'details' => $details,
            'meta' => $meta
        ];

        if ($status != 'success' && $status != 'warning') {
            unset($this->response['data']);
        }

        return $this;
    }

    private function actionSaveApiKeys(): self
    {
        try {
            $publicKey = $this->params['iapi_publicKey'];
            if ($publicKey) {
                EticConfig::set('SANALPOSPRO_PUBLIC_KEY', $publicKey);
            }
            $secretKey = $this->params['iapi_secretKey'];
            if ($secretKey) {
                EticConfig::set('SANALPOSPRO_SECRET_KEY', $secretKey);
            }
            $this->setResponse('success', 'Api keys saved');
            return $this;
        } catch (\Exception $e) {
            $this->setResponse('error', $e->getMessage());
            return $this;
        }
    }

    private function actionCheckApiKeys(): self
    {
        try {
            if (!EticConfig::get('SANALPOSPRO_PUBLIC_KEY') || !EticConfig::get('SANALPOSPRO_SECRET_KEY')) {
                $this->setResponse('error', 'Api keys not found');
                return $this;
            }
            $apiClient = ApiClient::getInstanse();
            $this->response = $apiClient->post('/check/accesstoken', [
                'accesstoken' => $this->params['iapi_accessToken']
            ]);

            return $this;
        } catch (\Exception $e) {
            $this->setResponse('error', $e->getMessage());
            return $this;
        }
    }

    private function actionSetInstallmentOptions(): self
    {
        try {
            $installmentOptions = $this->params['iapi_installmentOptions'];
            if (empty($installmentOptions)) {
                $this->setResponse('error', 'Invalid installment options');
                return $this;
            }
            EticConfig::set('SANALPOSPRO_INSTALLMENTS', json_encode($installmentOptions));
            $this->setResponse('success', 'Installment options updated');
            return $this;
        } catch (\Exception $e) {
            $this->setResponse('error', $e->getMessage());
            return $this;
        }
    }

    private function actionCreatePaymentLink(): self
    {
        try {
            // contexts
            $currency_obj = EticContext::get('currency');
            $currency = $currency_obj->iso_code;
            $cart = EticContext::get('cart');
            $customer = EticContext::get('customer');
            $cart_items = $cart->getProducts();
            $cart_total = $cart->getOrderTotal();
            // Get order id
            $order_id = $cart->id;

            // Get shipping costs
            $shipping_cost = $cart->getTotalShippingCost();
            // Add shipping as cart item if cost exists
            // Get cart discounts/cart rules
            $cartRules = $cart->getCartRules();
            $discounts = [];

            if (!empty($cartRules)) {
                foreach ($cartRules as $rule) {
                    $discounts[] = [
                        'id' => $rule['id_cart_rule'],
                        'name' => $rule['name'],
                        'value' => $rule['value_real'],
                        'value_tax_exc' => $rule['value_tax_exc']
                    ];
                }
            }

            // addresses 
            $shippingAddress = new \Address($cart->id_address_delivery);
            //$invoiceAddress = new \Address($cart->id_address_invoice);
            $adress = new \Address($cart->id_address_delivery);

            // Create Cart instance
            $cartModel = new Cart();

            // Add products to cart
            // discount vs de kontrol edilip eklenilecek
            foreach ($cart_items as $product) {
                $cartItem = new CartItem(
                    'PRD-' . $product['id_product'],
                    $product['name'],
                    'product',
                    number_format($product['price_wt'], 2, '.', ''),
                    $product['quantity']
                );
                $cartModel->addItem($cartItem);
            }

            // Add discounts to cart
            foreach ($discounts as $discount) {
                $discountItem = new CartItem(
                    'DSC-' . $discount['id'],
                    $discount['name'],
                    'discount',
                    number_format($discount['value'], 2, '.', ''),
                    1
                );
                $cartModel->addItem($discountItem);
            }

            if ($shipping_cost > 0) {
                $shippingItem = new CartItem(
                    'SHP-1',
                    'Kargo Ücreti',
                    'shipping',
                    number_format($shipping_cost, 2, '.', ''),
                    1
                );
                $cartModel->addItem($shippingItem);
            }


            $payment = new PaymentModel();
            $payment->setAmount($cart_total);
            $payment->setCurrency($currency);
            $payment->setBuyerFee(0);
            $payment->setMethod('creditcard');
            $payment->setMerchantReference($order_id);


            //$order = new \Order($this->module->currentOrder);
            $link = EticContext::get('link');
            $redirect_url = $link->getModuleLink($this->module->name, 'paymenthandler', [
                'action' => 'confirmOrder',
                'nonce' => EticConfig::get('SANALPOSPRO_XFVV')
            ], true);
            $payment->setReturnUrl($redirect_url);

            $payerAddress = new Address();
            $payerAddress->setLine1($adress->address1);
            $payerAddress->setCity($adress->city);
            $payerAddress->setState($adress->country);
            $payerAddress->setPostalCode($adress->postcode);
            $payerAddress->setCountry($adress->country);


            $shippingPhone = $shippingAddress->phone ?: ($shippingAddress->phone_mobile ?: '5000000000');
            $phone = !empty($customer->phone) ? $customer->phone : $shippingPhone;


            $payer = new Payer();
            $payer->setFirstName($customer->firstname);
            $payer->setLastName($customer->lastname);
            $payer->setEmail($customer->email);
            $payer->setPhone($phone);
            $payer->setAddress($payerAddress);
            $payer->setIp($_SERVER['REMOTE_ADDR']);


            $invoice = new Invoice();
            $invoice->setId($order_id);
            $invoice->setFirstName($customer->firstname);
            $invoice->setLastName($customer->lastname);
            $invoice->setPrice($cart_total);
            $invoice->setQuantity(1);


            $shipping = new Shipping();
            $shipping->setFirstName($customer->firstname);
            $shipping->setLastName($customer->lastname);
            $shipping->setPhone($shippingPhone);
            $shipping->setEmail($customer->email);
            $shipping->setAddress($payerAddress);

            $order = new Order();
            $order->setCart($cartModel->toArray()['items']);
            $order->setShipping($shipping);
            $order->setInvoice($invoice);


            $paymentRequest = new PaymentRequest();
            $paymentRequest->setPayment($payment);
            $paymentRequest->setPayer($payer);
            $paymentRequest->setOrder($order);


            $result = Payment::createPayment($paymentRequest->toArray());

            $this->response = $result;
            return $this;
        } catch (\Exception $e) {
            $this->setResponse('error', $e->getMessage());
            return $this;
        }
    }

    private function actionConfirmOrder(): self
    {
        $cart = EticContext::get('cart');
        if (!isset($cart->id)) {
            $link = EticContext::get('link');
            $redirect_url = $link->getPageLink('index.php?controller=order&step=1', true, null, []);
            $this->setResponse('warning', 'Order confirmation failed', [
                'redirect_url' => $redirect_url
            ]);
            return $this;
        }
        $customer = new \Customer($cart->id_customer);
        $link = EticContext::get('link');

        try {
            $process_token = $this->params['process_token'];
            $res = Payment::validatePayment($process_token);

            if ($res['status'] != 'success') {
                $redirect_url = $link->getPageLink('index.php?controller=order&step=1', true, null, []);
                $this->setResponse('warning', 'Order confirmation failed', [
                    'redirect_url' => $redirect_url
                ]);
                return $this;
            }

            $processData = $res['data']['process'];
            $data = $res['data']['transaction'];


            if ($data['status'] == 'completed' && $processData['process_status'] == 'completed') {
                $transaction_amount = $processData['amount'];
                $this->module->validateOrder(
                    $cart->id,
                    empty(EticConfig::get('SANALPOSPRO_ORDER_STATUS')) ? EticConfig::get('PS_OS_PAYMENT') : EticConfig::get('SANALPOSPRO_ORDER_STATUS'),
                    $cart->getOrderTotal(true, \Cart::BOTH),
                    $this->module->displayName,
                    null,
                    [],
                    null,
                    false,
                    $customer->secure_key
                );

                $order = new \Order($this->module->currentOrder);
                $order->total_paid_tax_incl = $transaction_amount;
                $order->total_paid = $transaction_amount;
                $order->update();

                $redirect_url = $link->getPageLink('order-confirmation', true, null, [
                    'id_cart' => $cart->id,
                    'id_module' => $this->module->id,
                    'id_order' => $this->module->currentOrder,
                    'key' => $order->secure_key
                ]);
                $this->setResponse('success', 'Order confirmed', [
                    'redirect_url' => $redirect_url
                ]);
                return $this;
            } else {
                $this->module->validateOrder(
                    $cart->id,
                    EticConfig::get('PS_OS_ERROR'),
                    $cart->getOrderTotal(true, \Cart::BOTH),
                    $this->module->displayName,
                    null,
                    [],
                    null,
                    false,
                    $customer->secure_key
                );
            }
        } catch (\Exception $e) {
            $redirect_url = $link->getPageLink('index.php?controller=order&step=1', true, null, []);
            $this->setResponse('warning', 'Order confirmation failed', [
                'redirect_url' => $redirect_url
            ]);
        }

        $this->setResponse('success', 'Order confirmation');
        return $this;
    }

    private function actionSetModuleSettings(): self
    {
        try {
            $settings = $this->params['iapi_moduleSettings'];
            if (empty($settings)) {
                $this->setResponse('error', 'Invalid settings');
                return $this;
            }
            foreach ($settings as $key => $value) {
                EticConfig::set('SANALPOSPRO_' . strtoupper($key), $value);
            }
            $this->setResponse('success', 'Settings updated');
            return $this;
        } catch (\Exception $e) {
            $this->setResponse('error', $e->getMessage());
            return $this;
        }
    }

    private function actionGetMerchantInfo(): self
    {
        try {
            $apiClient = ApiClient::getInstanse();
            $this->response = $apiClient->post('/merchant/info', []);

            return $this;
        } catch (\Exception $e) {
            $this->setResponse('error', $e->getMessage());
            return $this;
        }
    }

    public function getResponse()
    {
        return $this->response;
    }
}
