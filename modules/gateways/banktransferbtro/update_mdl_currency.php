<?php

//
// add crontab line:
// 
// 40 9 * * *    /opt/cpanel/ea-php74/root/usr/bin/php -q .../banktransfer/update_mdl_currency.php >/dev/null
//

ini_set('display_errors', 'On');
ini_set('output_buffering', 'On');
error_reporting(E_ALL & ~E_DEPRECATED);


require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/victoriabank.class.php';
$whmcs->load_function('gateway');
$whmcs->load_function('invoice');

use WHMCS\Database\Capsule;


// Detect module name from filename.
$gatewayModuleName = 'banktransfer';

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
	die("Module Not Activated");
}

$bank = new victoriabank_wiretransfer();

$bank->login($gatewayParams['login'],$gatewayParams['password']);
$rates = $bank->getCommercialRates();
$bank->close();

$eur_exchange_rate=0;

foreach($rates->I as $r) {
	foreach($r->attributes() as $att=>$val) {
		if($att=="Currency" && $val!=="EUR") {
			continue;
		}
		if($att=="SellRate") {
			$eur_exchange_rate = round((string)$val, 2);
		}
	}
}

if ($eur_exchange_rate !== 0) {
	$t2 = Capsule::table('tblcurrencies')->where('code','=','MDL')->update([
		'rate' => $eur_exchange_rate
	]);

	echo "Updated exchange rate of EUR to MDL to ".$eur_exchange_rate."\n";
} else {
	$result = localAPI('TriggerNotificationEvent', array(
		'notification_identifier' => 'verificareplati',
		'title' => 'Actualizare curs EUR-MDL',
		'message' => "Eroare actualizare curs valutar!",
		'statusStyle' => 'info',
	));
	echo "Error during currency exchange rate for EUR\n";
}
?>
