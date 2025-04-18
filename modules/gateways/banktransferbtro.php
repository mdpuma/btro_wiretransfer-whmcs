<?php

# Bank Transfer Payment Gateway Module

if (!defined("WHMCS")) die("This file cannot be accessed directly");

function banktransferbtro_config() {

    $configarray = array(
     "FriendlyName" => array(
        "Type" => "System",
        "Value" => "Bank Transfer (Banca Transilvania Romania)"
        ),
     "instructions" => array(
        "FriendlyName" => "Bank Transfer Instructions",
        "Type" => "textarea",
        "Rows" => "5",
        "Value" => "Bank Name:\nPayee Name:\nSort Code:\nAccount Number:",
        "Description" => "The instructions you want displaying to customers who choose this payment method - the invoice number will be shown underneath the text entered above",
        ),
	  "account" => array(
        "FriendlyName" => "Bank account number",
        "Type" => "text",
        "Size" => "30",
        "Value" => "",
        "Description" => "Bank account number",
        ),
       "client_id" => array(
        "FriendlyName" => "Client ID",
        "Type" => "text",
        "Size" => "50",
        "Value" => "",
        "Description" => "Client ID received from BT Support",
        ),
       "localapi_user" => array(
        "FriendlyName" => "API User",
        "Type" => "text",
        "Size" => "16",
        "Value" => "bank_api",
        "Description" => "",
        ),
    );

    return $configarray;

}

function banktransferbtro_link($params) {
    $code = '<p>'
        . nl2br($params['instructions'])
        . '<br />'
        . Lang::trans('invoicerefnum')
        . ': '
        . $params['invoicenum']
        . '</p>';

    return $code;
}
