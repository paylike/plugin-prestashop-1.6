{**
 *
 * @author    DerikonDevelopment <ionut@derikon.com>
 * @copyright Copyright (c) permanent, DerikonDevelopment
 * @license   Addons PrestaShop license limitation
 * @version   1.0.0
 * @link      http://www.derikon.com/
 *
 *}
<p class="alert alert-danger">
    {l s='Note: Due to prestashop standards we need to abide to, currencies decimals must match the paylike supported decimals. In order to use the Paylike module for the following currencies, you\'ll need to set "Number of decimals" option to the number shown bellow from tab. Since this is a global setting that affects all currencies you cannot use at the same time currencies with different decimals.' mod='paylikepayment'}
    <a href="{$preferences_url}">{l s='Preferences -> General' mod='paylikepayment'}</a>
    <br>
    {foreach from=$warning_currencies_decimal key=decimals item=currency}
        {foreach from=$currency item=iso_code}
            <b>{$iso_code} {l s='supports only' mod='paylikepayment'} {$decimals} {l s='decimals' mod='paylikepayment'}</b>
            <br/>
        {/foreach}
    {/foreach}
</p>