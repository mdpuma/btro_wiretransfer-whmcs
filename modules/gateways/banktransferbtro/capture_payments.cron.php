<?php

//
// add crontab line:
// 
// 3 8-22 * * *    /opt/cpanel/ea-php74/root/usr/bin/php -q .../banktransferbtro/capture_payments.cron.php >/dev/null
//

ini_set('display_errors', 'On');
ini_set('output_buffering', 'On');
error_reporting(E_ALL & ~E_DEPRECATED);


require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/bancatransilvania.class.php';
$whmcs->load_function('gateway');
$whmcs->load_function('invoice');

use WHMCS\Database\Capsule;


// Detect module name from filename.
$gatewayModuleName = 'banktransferbtro';

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
	die("Module Not Activated");
}

$excepted_DebtorAccounts = array(
	'RO97CITI0000000120373021', // STRIPE TECHNOLOGY EUROPE LIMITED
	'RO38BTRLRONCRT0623080801', // AMPLICA HOSTING S R L
);

$bank = new bancatransilvania_wiretransfer($gatewayParams['client_id']);

$day = date('m/d/Y');
#$day = '04/04/2025';

$transactions = $bank->getTransactions($day, $gatewayParams['account']);
$bank->close();

$unknown_transfers=array();

var_dump($transactions);


// get currencies
$currencies = localAPI('GetCurrencies', array());
$currency_id = false;
foreach($currencies['currencies']['currency'] as $c) {
	if($c['code'] == 'RON') {
		$currency_id= $c['id'];	
	}
}
echo "currency id for RON is ".$currency_id."\n";

foreach($transactions as $t) {
	// skip outgoing bank transfers
	if ($t->Tip_tranzactie !== 'Ingoing Payment') {
		echo "outgoing payment\n";
		continue;
	}
	if (in_array($t->DebtorAccount, $excepted_DebtorAccounts)) {
		print "skipping transaction from ".$t->DebtorAccount."\n";
		continue;
	}
	if(empty($t->DebtorAccount)) {
		print "skipping transaction without DebtorAccount, with Narrative: ".$t->Narrative."\n";
		continue;
	}
	print "Processing transfer from ".$t->DebtorName." with bank account ".$t->DebtorAccount." with amount ".$t->Amount." ".$t->Currency."\n";

	// find clientid by DebtorAccount // cont bancar

	// get fieldid by name iban
	$customfield = Capsule::table('tblcustomfields')->select('*')->where('type','=', 'client')->where('fieldname','=','IBAN')->limit(1)->get();
	if(count($customfield) == 0) {
		echo "Cant find custom field id by name IBAN\n";
		exit;
	}
	$fieldid = $customfield[0]->id;
	
	$clients = Capsule::table('tblcustomfieldsvalues')->select('*')->where('fieldid','=', $fieldid)->where('value', '=', $t->DebtorAccount)->limit(10)->get();
	if(count($clients) == 0) {
		echo "Cant find client company ".$t->DebtorName."\n";
		$unknown_transfers[] = array(
			'company' => $t->DebtorName,
			'iban' => $t->DebtorAccount,
			'amount' => $t->Amount,
			'currency' => $t->Currency,
			'status' => $t->TransactionStatus,
			'error' => 'Nu gasesc persoana juridica dupa numar IBAN'
		);
		continue;
	} elseif(count($clients) > 1) {
		$active=0;
		foreach($clients as $c) {
			$t2 = Capsule::table('tblclients')->select('id')->where('id','=', $c->relid)->where('status', '=', 'Active')->limit(1)->get();
			if(count($t2)) {
				$active++;
				$clientid = $c->relid;
			}
		}
		if($active>1) {
			echo "There is multiple company with same IBAN ".$t->DebtorAccount."\n";
			$unknown_transfers[] = array(
				'company' => $t->DebtorName,
				'iban' => $t->DebtorAccount,
				'amount' => $t->Amount,
				'error' => 'Mai multe companii identificate cu acelasi IBAN'
			);
			continue;
		}
	} else {
		$clientid = $clients[0]->relid;
	}
	
	$invoices = localAPI('GetInvoices', array(
		'userid' => $clientid,
		'orderby' => 'id',
		'order' => 'desc',
	));
	$found_invoice=0;
	
	$transactionId = (string)$t->TransactionRef;
	
	// check invoice total (including TVA) and paid by customer amount
	$amount = $t->Amount;
	$currency = $t->Currency;
	
	if($invoices['result'] == 'success') {
		foreach($invoices['invoices']['invoice'] as $i) {
			if($i['status'] !== 'Unpaid' && $i['status'] !== 'Payment Pending') {
				continue;
			}
			echo "checking invoice ".$i['id']."\n";

			if(round($i['total'], 2) === round($amount, 2) && $i['status']=='Unpaid') {
				// check currency of invoice if it is RON and it is unpaid!
				if($i['currencycode'] !== 'RON') {
					echo "There are invoice but, currency is not RON ".$i['id']."\n";
					$unknown_transfers[] = array(
						'company' => $t->DebtorName,
						'iban' => $t->DebtorAccount,
						'amount' => $t->Amount,
						'error' => 'Nu corespunde valuta la invoice identificat'
					);
					continue;	
				}
				
				
				echo "Check transaction id ".$transactionId."\n";
				$t3 = localAPI('GetTransactions', array(
					'clientid' => $clientid,
					'transid' => $transactionId,
				));
				$t3['totalresults'] = intval($t3['totalresults']);
				if($t3['result']=='success' && $t3['totalresults']!== 0 ) {
					echo "There is already existing transaction ".$transactionId."\n";
					$found_invoice=1;
					continue;
				}
				
				localAPI('addInvoicePayment', array(
					'invoiceid' => (int) $i['id'],
					'transid' => $transactionId,
					'payed' => $amount,
					'fees' => 0,
					'gateway' => $gatewayModuleName,
				), $gatewayParams['localapi_user']);
				$found_invoice=1;
			}
		}
	}
	if($found_invoice==0) {
		echo "Check transaction id ".$transactionId."\n";
		$t3 = localAPI('GetTransactions', array(
			'clientid' => $clientid,
			'transid' => $transactionId,
		));
		$t3['totalresults'] = intval($t3['totalresults']);
		if($t3['result']=='success' && $t3['totalresults']==0) {
			echo "Cant find invoice where to apply transaction with amount '".$amount."'\n";
			$unknown_transfers[] = array(
				'company' => $t->DebtorName,
				'iban' => $t->DebtorAccount,
				'amount' => $amount,
				'currency' => $currency,
				'error' => 'Nu gasesc cont spre plata pentru transfer efectuat'
			);
		}
	}
	echo "Processed company '".$t->DebtorName."' finished\n\n";
}

if(count($unknown_transfers)>0) {
	$message = "Te rog verifica aceste achitari:\n\n";
	
	$c=0;
	foreach($unknown_transfers as $l) {
		// check if we have sent already notification about unknown transaction
		$db_trans = Capsule::table('mod_wiretransferbt_transactions')->select('*')->where('iban','=', $l['iban'])->whereRaw('amount = ?', [ $l['amount'] ])->count();
		if($db_trans == 1) {
			echo "skipping ".$l['company']."\n";
			continue;
		}
		
		$message .= sprintf("%s IBAN %s TRANSFERAT %s %s - EROARE: %s\n\n", $l['company'], $l['iban'], $l['amount'], $l['currency'], $l['error']);
		
		Capsule::table('mod_wiretransferbt_transactions')->insert(
			[
			'iban'=> $l['iban'],
			'debitname'	=> $l['company'],
			'amount'	=> $l['amount'],
			'currency'	=> $l['currency'],
			'error'		=> $l['error'],
			]
		);
		$c++;
	}
	if($c>0) {
		$result = localAPI('TriggerNotificationEvent', array(
			'notification_identifier' => 'verificareplati',
			'title' => 'Achitari neprocesate',
			'message' => $message,
			'statusStyle' => 'info',
		));
	}
}

// if time os over 22:00
if(date('H') >= 22) {
	Capsule::table('mod_wiretransferbt_transactions')->truncate();
	echo "Truncate table mod_wiretransferbt_transactions\n";
}

?>
