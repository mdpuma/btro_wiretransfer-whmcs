<?php

class bancatransilvania_wiretransfer {
	private $token = null;
	private $handle = null;
	private $certificate_body = null;
	private $client_id = '';
	public function __construct($client_id) {
		$this->handle = curl_init();
		$this->client_id = $client_id;
		curl_setopt($this->handle, CURLOPT_URL, 'https://api.apistorebt.ro/bt/partners/oauth/oauth2/token');
		curl_setopt($this->handle, CURLOPT_HEADER, 0);
		curl_setopt($this->handle, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($this->handle, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($this->handle, CURLOPT_RETURNTRANSFER, 1);
		#curl_setopt($this->handle, CURLOPT_VERBOSE, true);
		
		curl_setopt($this->handle, CURLOPT_SSLCERT, dirname(__FILE__).'/client_cert.crt'); // Client certificate
		curl_setopt($this->handle, CURLOPT_SSLKEY, dirname(__FILE__).'/client_cert.key');   // Private key
		
		curl_setopt($this->handle, CURLOPT_POST, 1);
		
		$this->certificate_body = trim(file_get_contents(dirname(__FILE__).'/client_cert.txt'));
		curl_setopt($this->handle, CURLOPT_HTTPHEADER, array(
			'X-Client-Certificate: '.$this->certificate_body,
			'Content-Type: application/x-www-form-urlencoded',
			'Accept: */*',
		));
		
		$post_data = http_build_query(array(
			'client_id' => $this->client_id,
			'grant_type' => 'client_credentials',
			'scope' => 'test'
		));
		curl_setopt($this->handle, CURLOPT_POSTFIELDS, $post_data);
		
		$output = curl_exec($this->handle);
		$output2 = json_decode($output);
		
		if(!isset($output2->access_token) || empty($output2->access_token)) {
			return false;
		}
		$this->token = $output2->access_token;
		return true;
	}
	public function getTransactions($day, $ibanNumber) {
		curl_setopt($this->handle, CURLOPT_HTTPHEADER, array(
			'Authorization: Bearer '.$this->token,
			'X-IBM-Client-Id: '.$this->client_id,
			'X-Client-Certificate: '.$this->certificate_body,
			'Content-Type: application/x-www-form-urlencoded',
			'Accept: */*',
		));
		
		$post_data = array(
			'IbanAccountNumber' => $ibanNumber,
			'FromDate' => $day,  // Luna/data/anul
			'ToDate' => $day,
			'crtPage' => 1,
			'sizePage' => 50
		);
		#var_dump($post_data);
		curl_setopt($this->handle, CURLOPT_POSTFIELDS, http_build_query($post_data));
		
		curl_setopt($this->handle, CURLOPT_URL, 'https://api.apistorebt.ro/bt/partners/bt-partners-api/v1/transactions');
		$output = curl_exec($this->handle);
		$output2 = json_decode($output);
		return $output2->bTPartnersTransactions;
	}

	public function getCommercialRates($day=null) {
		return false;
	}

	public function close() {
		curl_close($this->handle);
	}
}

?>
