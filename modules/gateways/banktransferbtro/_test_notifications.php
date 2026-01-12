<?php

ini_set('display_errors', 'On');
ini_set('output_buffering', 'On');
error_reporting(E_ALL & ~E_DEPRECATED);


require_once __DIR__ . '/../../../init.php';
$whmcs->load_function('gateway');
$whmcs->load_function('invoice');

use WHMCS\Database\Capsule;


//$result = localAPI('TriggerNotificationEvent', array(
//		'notification_identifier' => 'verificareplati',
//		'title' => 'Achitari neprocesate',
//		'message' => 'te rog verifica aceste achitari',
//		'statusStyle' => 'info',
//	));

$result = run_hook('TriggerCustomNotificationEvent', array(
	    'notification_identifier' => 'verificareplati',
	    'title' => 'Achitari neprocesate',
	    'custom_message' => $message,
	    'statusStyle' => 'info',
	  ));

var_dump($result);
