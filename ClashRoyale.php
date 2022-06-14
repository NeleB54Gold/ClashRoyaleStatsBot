<?php

class ClashRoyale {
	# Official API Endpoint [https://developer.clashroyale.com/]
	public $endpoint = 'https://api.clashroyale.com/v1';
	# API Token
	private $token = '';
	# Cache time (Redis only)
	public $cache_time = 60 * 60 * 2;
	# Request timeout
	public $r_timeout = 5;
	# Database class
	private $db = [];
	
	# Set configs
	public function __construct ($db = []) {
		if (is_a($db, 'Database') and $db->configs['redis']['status']) $this->db = $db;
	}
	
	# Get player info by tag
	public function getPlayer ($tag) {
		return $this->request('players/%23' . str_replace('#', '', $tag));
	}
	
	# Get player chests by tag
	public function getPlayerChests ($tag) {
		return $this->request('players/%23' . str_replace('#', '', $tag) . '/upcomingchests');
	}
	
	# Get clan info by tag
	public function getClan ($tag) {
		return $this->request('clans/%23' . str_replace('#', '', $tag));
	}
	
	# Custom API requests
	public function request ($src) {
		if (!isset($this->curl))	$this->curl = curl_init();
		$url = $this->endpoint . '/' . $src;
		if (is_a($db, 'Database')) {
			$cache = $this->db->rget($url);
			if ($r = json_decode($cache, 1)) return $r;
		}
		curl_setopt_array($this->curl, [
			CURLOPT_URL				=> $url,
			CURLOPT_TIMEOUT			=> $this->r_timeout,
			CURLOPT_RETURNTRANSFER	=> 1,
			CURLOPT_HTTPHEADER			=> [
				'Accept: application/json',
				'Authorization: Bearer ' . $this->token
			]
		]);
		$output = curl_exec($this->curl);
		if ($json_output = json_decode($output, 1)) {
			if (is_a($db, 'Database')) $this->db->rset($url, json_encode($json_output), $this->cache_time);
			return $json_output;
		}
		if ($output) return $output;
		if ($error = curl_error($this->curl)) return ['ok' => 0, 'error_code' => 500, 'description' => 'CURL Error: ' . $error];
	}
}

?>
