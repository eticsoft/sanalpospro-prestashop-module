<?php

/**
 * 2007-2024 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2024 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */


use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use Eticsoft\Paythor\Sanalpospro\EticConfig;

include _PS_MODULE_DIR_ . DIRECTORY_SEPARATOR . 'sanalpospro' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'include.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

class sanalpospro extends PaymentModule
{
    protected $config_form = false;


    public function __construct()
    {
        $this->name = 'sanalpospro';
        $this->tab = 'payments_gateways';
        $this->version = '10.0.4';
        $this->author = 'EticSoft R&D Lab';
        $this->need_instance = 1;
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => '9.0.3'];

        /*
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('SanalPOSPRO');
        $this->description = $this->l(
            'SanalPOSPRO allows you to accept payments via credit/debit cards by using SanalPOSPRO Payment Services.'
        );

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall SanalPOSPRO?');
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        // Temel ayarlar
        Configuration::updateValue('SANALPOSPRO_PUBLIC_KEY', '');
        Configuration::updateValue('SANALPOSPRO_SECRET_KEY', '');

        // Ek ayarlar için default değerler
        Configuration::updateValue('SANALPOSPRO_ORDER_STATUS', '2'); // 2 = Payment accepted
        Configuration::updateValue('SANALPOSPRO_CURRENCY_CONVERT', 'no');
        Configuration::updateValue('SANALPOSPRO_SHOWINSTALLMENTSTABS', 'no');
        Configuration::updateValue('SANALPOSPRO_PAYMENTPAGETHEME', 'classic');
        Configuration::updateValue('SANALPOSPRO_INSTALLMENTS', '[]');
        $xfvv = hash('sha256', time() . rand(1000000, 9999999));
        EticConfig::set('SANALPOSPRO_XFVV', $xfvv);

        include dirname(__FILE__) . '/sql/install.php';

        return parent::install()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('displayAdminOrderTop')
            && $this->registerHook('actionFrontControllerSetMedia')
            && $this->registerHook('displayProductExtraContent')
            && $this->installAdminTab();
    }

    private function installAdminTab()
    {
        $tabId = (int) Tab::getIdFromClassName('AdminSanalPosProIapi');
        if (!$tabId) {
            $tabId = null;
        }
        $tab = new Tab($tabId);
        $tab->active = 1;
        $tab->class_name = 'AdminSanalPosProIapi';
        $tab->name = array();
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'SanalPosPro Iapi';
        }
        $tab->id_parent = -1;  // -1 means hidden tab
        $tab->module = $this->name;

        return $tab->save();
    }

    private function uninstallAdminTab()
    {
        $tabId = (int) Tab::getIdFromClassName('AdminSanalPosProIapi');
        if ($tabId) {
            $tab = new Tab($tabId);
            return $tab->delete();
        }
        return true;
    }


    public function uninstall()
    {
        // Uninstall module-specific configuration values
        Configuration::deleteByName('SANALPOSPRO_ORDER_STATUS');
        Configuration::deleteByName('SANALPOSPRO_CURRENCY_CONVERT');
        Configuration::deleteByName('SANALPOSPRO_SHOWINSTALLMENTSTABS');
        Configuration::deleteByName('SANALPOSPRO_PAYMENTPAGETHEME');
        Configuration::deleteByName('SANALPOSPRO_INSTALLMENTS');
        Configuration::deleteByName('SANALPOSPRO_PUBLIC_KEY');
        Configuration::deleteByName('SANALPOSPRO_SECRET_KEY');

        // Additional configuration values to remove
        Configuration::deleteByName('CONF_SANALPOSPRO_FIXED');
        Configuration::deleteByName('CONF_SANALPOSPRO_VAR');
        Configuration::deleteByName('CONF_SANALPOSPRO_FIXED_FOREIGN');
        Configuration::deleteByName('CONF_SANALPOSPRO_VAR_FOREIGN');

        // Drop module-specific database tables
        $sql = [];
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'sanalpospro_transaction`';
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'sanalpospro`';

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        // Unregister specific hooks
        $this->unregisterHook('paymentOptions');
        $this->unregisterHook('displayAdminOrderTop');
        $this->unregisterHook('actionFrontControllerSetMedia');
        $this->unregisterHook('displayProductExtraContent');
        $this->uninstallAdminTab();


        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        $getOrderStatus = OrderState::getOrderStates($this->context->language->id);

        $this->context->smarty->assign('orderStatus', $getOrderStatus);
        $this->context->smarty->assign('iapi_base_url', $this->context->link->getAdminLink('AdminSanalPosProIapi'));
        $this->context->smarty->assign('iapi_xfvv', EticConfig::get('SANALPOSPRO_XFVV'));
        $this->context->smarty->assign('SANALPOSPRO_PAYMENTPAGE_THEME', EticConfig::get('SANALPOSPRO_PAYMENTPAGE_THEME'));
        $this->context->smarty->assign('SANALPOSPRO_ORDER_STATUS', EticConfig::get('SANALPOSPRO_ORDER_STATUS'));
        $this->context->smarty->assign('SANALPOSPRO_CURRENCY_CONVERT', EticConfig::get('SANALPOSPRO_CURRENCY_CONVERT'));
        $this->context->smarty->assign('SANALPOSPRO_SHOWINSTALLMENTSTABS', EticConfig::get('SANALPOSPRO_SHOWINSTALLMENTSTABS'));
        $this->context->smarty->assign('store_url', $this->context->link->getBaseLink());

        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');
    }

    public function hookDisplayAdminOrderTop($param)
    {
        $order = new Order((int) Tools::getValue('id_order'));
        $orderState = new OrderState((int) $order->current_state);

        if ($orderState->paid && $order->module == $this->name) {
            $this->context->smarty->assign([
                'order_id' => $order->id,
                'order_reference' => $order->reference,
                'order_total' => $order->total_paid,
                'order_currency' => new Currency($order->id_currency),
                'order_state' => $orderState->name,
            ]);

            return $this->context->smarty->fetch($this->getTemplatePath('admin/order.tpl'));
        }
        return '';
    }


    public function hookActionFrontControllerSetMedia()
    {
        // add front.js
        $this->context->controller->registerJavascript(
            'module-SanalPosPro-front',
            'modules/' . $this->name . '/views/js/front.js',
            ['position' => 'bottom', 'priority' => 150]
        );
        $this->context->controller->addCSS(
            'modules/' . $this->name . '/views/css/sanalpospro-payment.css',
            ['media' => 'all', 'priority' => 150]
        );

        //önemli
        Media::addJsDef(
            [
                'sanalpospro_front_handler_url' => $this->context->link->getModuleLink($this->name, 'paymenthandler', [], true),
                'sanalpospro_front_xfvv' => EticConfig::get('SANALPOSPRO_XFVV'),
            ]
        );
    }

    public function hookPaymentOptions()
    {
        if (!$this->active) {
            return [];
        }

        // add https://code.jquery.com/jquery-3.7.1.js
        $this->context->controller->registerJavascript(
            'module-SanalPosPro-jquery',
            'https://code.jquery.com/jquery-3.7.1.js',
            ['server' => 'remote', 'position' => 'head', 'priority' => 150]
        );

        $this->context->smarty->assign([
            'ids' => [
                'id_cart' => Context::getContext()->cart->id,
                'id_customer' => Context::getContext()->customer->id,
                'id_lang' => Context::getContext()->language->id,
                'id_currency' => Context::getContext()->currency->id,
                'id_shop' => Context::getContext()->shop->id,
            ],
        ]);

        $newOption = new PaymentOption();
        $newOption->setModuleName($this->name)
            ->setCallToActionText($this->l('Pay with Credit Card'))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
            ->setAdditionalInformation($this->context->smarty->fetch('module:' . $this->name . '/views/templates/front/payment_form.tpl'));

        return [$newOption];
    }

    public function hookDisplayProductExtraContent($params)
    {
        if (Configuration::get('SANALPOSPRO_SHOWINSTALLMENTSTABS') == 'no') {
            return '';
        }

        $this->context->controller->registerStylesheet(
            'module-sanalpospro-front',
            'modules/' . $this->name . '/views/css/front.css',
            ['media' => 'all', 'priority' => 150]
        );

        $product = new Product($params['product']->id);
        $price = $product->getPrice(true, null, 2);

        // GarantiBBVA taksit verilerini alalım
        $installments = json_decode(Configuration::get('SANALPOSPRO_INSTALLMENTS') ?? '[]', true) ?? [];
        if (!empty($installments['default'])) {
            unset($installments['default']);
        }

        foreach ($installments as $key => $installment) {
            foreach ($installment as $key2 => $value) {
                if ($value['gateway'] == 'off') {
                    unset($installments[$key][$key2]);
                }
            }
        }

        $currencySymbol = Context::getContext()->currency->sign;

        $this->context->smarty->assign([
            'price' => $price,
            'installments' => $installments,
            'currencySymbol' => $currencySymbol
        ]);
        if (Configuration::get('SANALPOSPRO_PAYMENTPAGETHEME') == 'classic') {
            $content = $this->context->smarty->fetch($this->getTemplatePath('/front/installments/classic.tpl'));
        } elseif (Configuration::get('SANALPOSPRO_PAYMENTPAGETHEME') == 'modern') {
            $content = $this->context->smarty->fetch($this->getTemplatePath('/front/installments/modern.tpl'));
        } else {
            $content = $this->context->smarty->fetch($this->getTemplatePath('/front/installments/classic.tpl'));
        }

        $array = [];
        $array[] = (new PrestaShop\PrestaShop\Core\Product\ProductExtraContent())
            ->setTitle($this->l('Installments', 'SanalPosPro'))
            ->setContent($content);
        return $array;
    }


    public function getTemplatePath($template)
    {
        return _PS_MODULE_DIR_ . $this->name . '/views/templates/' . $template;
    }
}