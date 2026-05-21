{*
* 2007-2025 PrestaShop
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
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2025 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*
* Don't forget to prefix your containers with your own identifier
* to avoid any conflicts with others containers.
*}


{if $order_state_paid}{/if}

<div class="card mb-3" id="sanalpospro-payment-details">
    <div class="card-header">
        <h3 class="card-header-title">SanalPOS PRO &mdash; {l s='Payment Details' mod='sanalpospro'}</h3>
    </div>
    <div class="card-body">
        {if $payment_logo}
            <div class="text-center mb-3">
                <img src="{$payment_logo|escape:'html':'UTF-8'}" alt="SanalPOS PRO" style="max-width: 280px; height: auto;">
            </div>
        {/if}

        {if $transaction}
            <div class="alert alert-warning">
                <strong>{l s='WARNING! Do not ship the order before checking the payment from the SanalPOS PRO panel.' mod='sanalpospro'}</strong>
            </div>

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>{l s='Process Detail' mod='sanalpospro'}</th>
                        <th>{l s='Value' mod='sanalpospro'}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{l s='Transaction ID' mod='sanalpospro'}</td>
                        <td>{$transaction.id|escape:'html':'UTF-8'}</td>
                    </tr>
                    <tr>
                        <td>{l s='Amount' mod='sanalpospro'}</td>
                        <td>{displayPrice price=$order_total currency=$order_currency->id}</td>
                    </tr>
                    {if $captured_amount}
                        <tr>
                            <td>{l s='Captured Amount' mod='sanalpospro'}</td>
                            <td>{displayPrice price=$captured_amount currency=$order_currency->id}</td>
                        </tr>
                    {/if}
                    {if $process_fee}
                        <tr>
                            <td>{l s='Process Fee' mod='sanalpospro'}</td>
                            <td>{displayPrice price=$process_fee currency=$order_currency->id}</td>
                        </tr>
                    {/if}
                    {if $gateway}
                        <tr>
                            <td>{l s='Gateway' mod='sanalpospro'}</td>
                            <td>{$gateway|escape:'html':'UTF-8'}</td>
                        </tr>
                    {/if}
                    {if $installment}
                        <tr>
                            <td>{l s='Installment' mod='sanalpospro'}</td>
                            <td>{$installment|escape:'html':'UTF-8'}</td>
                        </tr>
                    {/if}
                    {if isset($transaction.currency)}
                        <tr>
                            <td>{l s='Currency' mod='sanalpospro'}</td>
                            <td>{$transaction.currency|escape:'html':'UTF-8'}</td>
                        </tr>
                    {/if}
                    <tr>
                        <td>{l s='Status' mod='sanalpospro'}</td>
                        <td>
                            <span class="badge {if $transaction.status == 'completed'}badge-success bg-success text-white{else}badge-danger bg-danger text-white{/if}">
                                {$payment_status_label|escape:'html':'UTF-8'}
                            </span>
                        </td>
                    </tr>
                    {if isset($transaction.created_at)}
                        <tr>
                            <td>{l s='Created At' mod='sanalpospro'}</td>
                            <td>{$transaction.created_at|escape:'html':'UTF-8'}</td>
                        </tr>
                    {/if}
                    {if $raw_response}
                        <tr>
                            <td>{l s='Raw Response (debug)' mod='sanalpospro'}</td>
                            <td><pre style="white-space:pre-wrap;word-break:break-all;max-height:400px;overflow:auto;margin:0;">{$raw_response|escape:'html':'UTF-8'}</pre></td>
                        </tr>
                    {/if}
                </tbody>
            </table>
        {else}
            {if $payment_error}
                <div class="alert alert-danger">{$payment_error|escape:'html':'UTF-8'}</div>
            {else}
                <div class="alert alert-info">{l s='Transaction details could not be found.' mod='sanalpospro'}</div>
            {/if}
            {if $raw_response}
                <div class="mt-3">
                    <strong>{l s='Raw Response (debug)' mod='sanalpospro'}</strong>
                    <pre style="white-space:pre-wrap;word-break:break-all;max-height:400px;overflow:auto;">{$raw_response|escape:'html':'UTF-8'}</pre>
                </div>
            {/if}
        {/if}
    </div>
</div>