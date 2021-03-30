{**
 *
 * @author    DerikonDevelopment <ionut@derikon.com>
 * @copyright Copyright (c) permanent, DerikonDevelopment
 * @license   Addons PrestaShop license limitation
 * @version   1.0.0
 * @link      http://www.derikon.com/
 *
 *}
{if $warning_currencies_decimal|@count}
    <p class="alert alert-danger">
        {l s='Note: Due to prestashop standards we need to abide to, currencies decimals must match the paylike supported decimals. In order to use the Paylike module for the following currencies, you\'ll need to set "Number of decimals" option to the number shown bellow from tab. Since this is a global setting that affects all currencies you cannot use at the same time currencies with different decimals.' mod='paylikepayment'}
        <br/>
        <a href="{$preferences_url}">{l s='Preferences -> General' mod='paylikepayment'}</a>
        <br>
        {foreach from=$warning_currencies_decimal key=decimals item=currency}
            {foreach from=$currency item=iso_code}
                <b>{$iso_code} {l s='supports only' mod='paylikepayment'} {$decimals} {l s='decimals' mod='paylikepayment'}</b>
                <br/>
            {/foreach}
        {/foreach}
    </p>
 {/if}

 {if $warning_currencies_display_decimals|@count}
    <p class="alert alert-warning">
        {l s='Warning: Paylike doesn`t provide support for customizable decimals display. In order to display the correct values durring the payment process you`ll need to set "Decimals" option to value "True" for the edit menu of the currencies listed below.' mod='paylikepayment'}
        <br/>
        <a href="{$currencies_url}">{l s='Localization -> Currencies' mod='paylikepayment'}</a>
        <br>
        {foreach from=$warning_currencies_decimal key=decimals item=currency}
            {foreach from=$currency item=iso_code}
                <b>{$iso_code}</b>
                <br/>
            {/foreach}
        {/foreach}
    </p>
{/if}