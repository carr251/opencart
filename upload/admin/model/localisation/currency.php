<?php
//The first class gets info for BTC to usd
// 	Bitcoin Helper 0.11

/*
	Bitcoin Helper - Copyright 2012, Jordan Hall
	http://jordanhall.co.uk/projects/bitcoin-helper-php-bitcoin-class/

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/*	Information about error codes

	Certain functions within Bitcoin Helper (0.11 onwards) will return
	numeric error codes in the case of unexpected error or failure.
	
	You should make sure to check the returned value for error codes
	(intergers less than zero) in your code. The following reference 
	shows the error numbers and an explanation.
	
	 * -1 = Network error retrieving data from bitcoincharts.com
	 * -2 = Error decoding JSON data retrieved from bitcoincharts.com
	 * -3 = Currency code not supported
	 * -4 = Could not write to cache file - check permissions!
*/

// bitcoin_helper - A class consisting of various functions relating to Bitcoin
class bitcoin_helper {
	// convert_to_bitcoin - Converts a non-Bitcoin currency to Bitcoin and returns the Bitcoin value.
	// As of bitcoin_helper 0.11
	// currency_code = ISO 4217 formatted currency code, e.g. GBP, USD, etc.
	// amount = Amount of currency to convert to Bitcoin
	public function convert_to_btc($currency_code, $amount)
	{	
		// Get weighted prices. If an errors occurs, return the provided error code.
		$weighted_prices = $this->get_weighted_prices();
		if (is_numeric($weighted_prices) && $weighted_prices<0) return $weighted_prices;
		
		// Check if currency is supported
		if (!array_key_exists($currency_code, $weighted_prices)) return -3;
		if (!array_key_exists('24h', $weighted_prices[$currency_code])) return -3;
		
		// Perform necessary calculations and return Bitcoin amount
		$btc_amount = 1 / $weighted_prices[$currency_code]['24h'];
		return $btc_amount;
	}
	
	// get_weighted_prices - Retrieves Bitcoin weighted prices JSON data from bitcoincharts.com and returns it as an array.
	// Caching: This function will attempt to cache (to a file) the JSON data retrieved from bitcoincharts.com for up to one hour.
	private function get_weighted_prices()
	{
		$cache_filename = "bitcoin_weighted_prices.json";
		
			$url = "http://bitcoincharts.com/t/weighted_prices.json";
			$content = @file_get_contents($url);
			if (!$content) return -1;
	
		$json = json_decode($content, true);
		if (!$json) return -2;
		return $json;
	}

}

class ModelLocalisationCurrency extends Model {
	public function addCurrency($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "currency SET title = '" . $this->db->escape($data['title']) . "', code = '" . $this->db->escape($data['code']) . "', symbol_left = '" . $this->db->escape($data['symbol_left']) . "', symbol_right = '" . $this->db->escape($data['symbol_right']) . "', decimal_place = '" . $this->db->escape($data['decimal_place']) . "', value = '" . $this->db->escape($data['value']) . "', status = '" . (int)$data['status'] . "', date_modified = NOW()");

		if ($this->config->get('config_currency_auto')) {
			$this->updateCurrencies(true);
		}

		$this->cache->delete('currency');
	}
	
	public function editCurrency($currency_id, $data) {

			$this->db->query("UPDATE " . DB_PREFIX . "currency SET title = '" . $this->db->escape($data['title']) . "', code = '" . $this->db->escape($data['code']) . "', symbol_left = '" . $this->db->escape($data['symbol_left']) . "', symbol_right = '" . $this->db->escape($data['symbol_right']) . "', decimal_place = '" . $this->db->escape($data['decimal_place']) . "', value = '" . $this->db->escape($data['value']) . "', status = '" . (int)$data['status'] . "', date_modified = NOW() WHERE currency_id = '" . (int)$currency_id . "'");
			
		
		$this->cache->delete('currency');
	}
	
	public function deleteCurrency($currency_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "currency WHERE currency_id = '" . (int)$currency_id . "'");
	
		$this->cache->delete('currency');
	}

	public function getCurrency($currency_id) {
		
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "currency WHERE currency_id = '" . (int)$currency_id . "'");
	
		return $query->row;
	}
	
	public function getCurrencyByCode($currency) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "currency WHERE code = '" . $this->db->escape($currency) . "'");
	
		return $query->row;
	}
		
	public function getCurrencies($data = array()) {
	$this->updateCurrencies();
		if ($data) {
			$sql = "SELECT * FROM " . DB_PREFIX . "currency";

			$sort_data = array(
				'title',
				'code',
				'value',
				'date_modified'
			);	
			
			if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
				$sql .= " ORDER BY " . $data['sort'];	
			} else {
				$sql .= " ORDER BY title";	
			}
			
			if (isset($data['order']) && ($data['order'] == 'DESC')) {
				$sql .= " DESC";
			} else {
				$sql .= " ASC";
			}
			
			if (isset($data['start']) || isset($data['limit'])) {
				if ($data['start'] < 0) {
					$data['start'] = 0;
				}				

				if ($data['limit'] < 1) {
					$data['limit'] = 20;
				}	
			
				$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
			}
			
			$query = $this->db->query($sql);
	
			return $query->rows;
		} else {
			$currency_data = $this->cache->get('currency');

			if (!$currency_data) {
				$currency_data = array();
				
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "currency ORDER BY title ASC");
	
				foreach ($query->rows as $result) {
      				$currency_data[$result['code']] = array(
        				'currency_id'   => $result['currency_id'],
        				'title'         => $result['title'],
        				'code'          => $result['code'],
						'symbol_left'   => $result['symbol_left'],
						'symbol_right'  => $result['symbol_right'],
						'decimal_place' => $result['decimal_place'],
						'value'         => $result['value'],
						'status'        => $result['status'],
						'date_modified' => $result['date_modified']
      				);
    			}	
			
				$this->cache->set('currency', $currency_data);
			}
			
			return $currency_data;			
		}
	}	

	public function updateCurrencies($force = true) {
		if (extension_loaded('curl')) {
			$data = array();
			
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "currency WHERE code != '" . $this->db->escape($this->config->get('config_currency')) . "'");
			
			foreach ($query->rows as $result) {
				$data[] = $this->config->get('config_currency') . $result['code'] . '=X';
			}	
			
			$curl = curl_init();
			
			curl_setopt($curl, CURLOPT_URL, 'http://download.finance.yahoo.com/d/quotes.csv?s=' . implode(',', $data) . '&f=sl1&e=.csv');
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			
			$content = curl_exec($curl);
			
			curl_close($curl);
			
			$lines = explode("\n", trim($content));
				
			foreach ($lines as $line) {
				$currency = utf8_substr($line, 4, 3);
				$value = utf8_substr($line, 11, 6);
				
				if ((float)$value) {
					$this->db->query("UPDATE " . DB_PREFIX . "currency SET value = '" . (float)$value . "', date_modified = '" .  $this->db->escape(date('Y-m-d H:i:s')) . "' WHERE code = '" . $this->db->escape($currency) . "'");
				}
				
			}
			
			$this->db->query("UPDATE " . DB_PREFIX . "currency SET value = '1.00000', date_modified = '" .  $this->db->escape(date('Y-m-d H:i:s')) . "' WHERE code = '" . $this->db->escape($this->config->get('config_currency')) . "'");
			if  (($this->db->escape($currency)) == "BTC") {
			$bh = new bitcoin_helper;
			$this->db->query("UPDATE " . DB_PREFIX . "currency SET value = '" . $bh->convert_to_btc($this->db->escape($this->config->get('config_currency')), 1) . "', date_modified = '" .  $this->db->escape(date('Y-m-d H:i:s')) . "' WHERE code = '" . $this->db->escape($currency) . "'");
		} 
			$this->cache->delete('currency');
		}
	}
	
	public function getTotalCurrencies() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "currency");
		
		return $query->row['total'];
	}
}
?>
