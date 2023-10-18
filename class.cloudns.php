<?php
/**
 * ClouDNS API class
 * @author Greg Whitehead
 */

class CloudNS {

    private $api_url = 'https://api.cloudns.net/';
    private $auth_id;
    private $auth_pass;
    private $domains = array();

    public function __construct( $auth_id = '', $auth_pass = '' ) {
    	$this->auth_id = $auth_id;
    	$this->auth_pass = $auth_pass;
    }

    private function apiCall($url, $data) {
        $url = "{$this->api_url}{$url}";
        $data = "auth-id=" . $this->auth_id . "&auth-password=" . $this->auth_pass . "&{$data}";
        $init = curl_init();
        curl_setopt($init, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($init, CURLOPT_URL, $url);
        curl_setopt($init, CURLOPT_POST, true);
        curl_setopt($init, CURLOPT_POSTFIELDS, $data);
        $content = curl_exec($init);
        curl_close($init);
        return json_decode($content, true);
    }

    public function check_api_connections() {
        $login = $this->apiCall('dns/login.json', "");
        return isset($login['status']) && $login['status'] !== 'Failed';
    }

    public function get_page_count($rows_per_page = 100) {
        return $this->apiCall('dns/get-pages-count.json', "rows-per-page={$rows_per_page}");
    }

    public function get_dns_zones($page = 1, $rows_per_page = 100) {
        return $this->apiCall('dns/list-zones.json', "page={$page}&rows-per-page={$rows_per_page}");
    }

	/**
	 * This function returns the domains and if it hasn't gotten the complete list 
	 * then it does so at that time. 
	 */
    public function get_all_zones() {
    	if (count($this->domains) == 0) {
    		$this->update_domains();
    	}
    	return $this->domains;
    }
    private function update_domains() {
    	$pages = $this->get_page_count();
    	for ($i=1; $i<=$pages; $i++) {
			$page_data = $this->get_dns_zones($i);
			// if ($debug) echo "Page Data: <pre>" . print_r($page_data, true) . "</pre>";
			foreach ($page_data as $page => $zone) {
				if (!in_array($zone['name'], $this->domains)) {
					$this->domains[] = $zone['name'];
				}
			}
		}
    }

    public function get_zone_records($zone_name = '', $type = '') {
        if ($zone_name === '') 
            return false;
        
        return $this->apiCall('dns/records.json', "domain-name={$zone_name}" . ($type !== '' ? "&type={$type}" : ''));
    }

    public function get_soa_details($zone_name = '') {
        if ($zone_name === '') 
            return false;
        
        return $this->apiCall('dns/soa-details.json', "domain-name={$zone_name}");
    }

    public function update_soa_details($zone_name = '', $primary_ns = '', $admin_mail = '', $refresh = '', $retry = '', $expire = '', $default_ttl = '') {
        if ($zone_name && $primary_ns && $admin_mail && $refresh && $retry && $expire && $default_ttl) {
            return $this->apiCall('dns/modify-soa.json', "domain-name={$zone_name}&primary-ns={$primary_ns}&admin-mail={$admin_mail}&refresh={$refresh}&retry={$retry}&expire={$expire}&default-ttl={$default_ttl}");
        } else {
            return ['status' => 'Failed'];
        }
    }

    public function get_ssl_details($zone_name = '') {
        if ($zone_name === '') 
            return false;
        
        return $this->apiCall('dns/freessl-get.json', "domain-name={$zone_name}");
    }

    public function issue_ssl_cert($zone_name = '', $issuer = 1) {
        if ($zone_name === '') 
            return false;
        
        return $this->apiCall('dns/freessl-activate.json', "domain-name={$zone_name}&issuer={$issuer}");
    }

    public function get_public_records($zone_name = '') {
    	if ($zone_name == '') 
    		return false;

    	$public_records = dns_get_record($zone_name);
    	$dns_records = array();
    	if ( $public_records !== false) {
	    	foreach ($public_records as $public_record) {
	    		$dns_records[$public_record['type']][] = array(
	    			'host' => $public_record['host'],
	    			'destination' => (isset($public_record['target']) ? $public_record['target'] : $public_record['ip']),
	    		);
	    	}
	    	return $dns_records;
	    }

	    return false;
    }
}
