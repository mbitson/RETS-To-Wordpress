<?php
/**
 * Created by PhpStorm.
 * User: mbitson
 * Date: 10/1/2014
 * Time: 9:03 AM
 */
class RETS
{
	/**
	 * Data to pass to template/views.
	 *
	 * @var array
	 */
	private $_data = array();
	private $ch = array();
	private $loggedIn = false;

	/**
	 * Function to login to the RETS server.
	 *
	 * @return bool|SimpleXMLElement
	 */
	public function login()
	{
		// Validate cookie file
		if (!file_exists(realpath($this->__get('cookie_file')))) touch($this->__get('cookie_file'));

		// Init curl
		$this->ch = curl_init();

		// Configure curl
		curl_setopt($this->ch, CURLOPT_URL, $this->__get('host').$this->__get('Login'));
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->ch, CURLOPT_COOKIESESSION, true);
		curl_setopt($this->ch, CURLOPT_COOKIEJAR, realpath($this->__get('cookie_file')));
		curl_setopt($this->ch, CURLOPT_TIMEOUT, 0);
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);

		if ($this->__get('force_basic_authentication') == true) {
			curl_setopt($this->ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		}
		else {
			curl_setopt($this->ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST|CURLAUTH_BASIC);
		}

		if ($this->__get('disable_follow_location') != true) {
			curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, 1);
		}

		// Set curl Login
		curl_setopt($this->ch, CURLOPT_USERPWD, $this->__get('username').":".$this->__get('password'));

		// Get result
		$response = curl_exec($this->ch);

		// If there is a result...
		if($response)
		{
			// Convert return to xml element
			$xml = new SimpleXMLElement($response);

			// Get Login Response
			if (isset($xml->{'RETS-RESPONSE'}))
			{
				// Get login response and break apart at new lines.
				$login_response = explode("\r\n", $xml->{'RETS-RESPONSE'});
				if (empty($login_response[3]))
				{
					$login_response = explode("\n", $xml->{'RETS-RESPONSE'});
				}

				// Parse each line to get the key and values into an array
				$result = array();
				foreach($login_response as &$entry){
					$entry = explode('=', $entry);
					if(isset($entry[0]) && $entry[0] && isset($entry[1]) && $entry[1])
					{
						$this->__set($entry[0], $entry[1]);
						$result[$entry[0]] = $entry[1];
					}
					unset($entry);
				}

				// Tell us we're logged in!
				$this->loggedIn = true;

				// Set response to newly parsed result array
				$login_response = $result;
			}

			// Else, failed login...
			else
			{
				$login_response = $xml;
			}

			return $login_response;
		}

		// Else there is no result...
		else
		{
			return FALSE;
		}

	}

	public function search($type = NULL, $class = NULL, $query = NULL, $select = NULL, $restrictionDelimiting = false, $limit = NULL)
	{
		// Check for logged in status!
		if(!$this->loggedIn){
			return 'You must login before searching.';
		}

		// Check for Search URL & Host
		if(!$this->__get('host') || !$this->__get('Search')){
			return 'We do not yet know the SEARCH url from the server. Try logging in with login().';
		}

		// Else.. both host and search url are set.
		else{
			$request_url = $this->__get('Search').'?';
		}

		// Check for type
		if(!is_null($type)){
			$request_url .= 'SearchType='.$type.'&';
		}else{
			return "You must provide Search Type to search.";
		}

		// Check for Class
		if(!is_null($class)){
			$request_url .= 'Class='.$class.'&';
		}else{
			return "You must provide a class to search.";
		}

		// Check for Query
		if(!is_null($query)){
			$request_url .= 'QueryType=DMQL2&Query='.$query.'&';
		}else{
			return "You must provide a query to search.";
		}

		// Check for select statement
		if(!is_null($select)){
			$request_url .= 'Select='.$select.'&';
		}

		// Check for select statement
		if($restrictionDelimiting){
			$request_url .= 'RestrictedIndicator=****&';
		}

		// Check for Query
		if(!is_null($limit)){
			$request_url .= 'Limit='.$limit.'&';
		}

		// Remove the last character of string if it is a &
		$request_url = trim($request_url, "&");

		// Run the request!
		$results = $this->simpleRequest($request_url);

		// Return the results.
		return $results;
	}

	public function getObject($type = NULL, $resource = NULL, $id = NULL, $location = NULL)
	{
		// Check for logged in status!
		if(!$this->loggedIn){
			return 'You must login before searching.';
		}

		// Check for Search URL & Host
		if(!$this->__get('host') || !$this->__get('Search')){
			return 'We do not yet know the SEARCH url from the server. Try logging in with login().';
		}

		// Else.. both host and search url are set.
		else{
			$request_url = $this->__get('GetObject').'?';
		}

		// Check for type
		if(!is_null($type)){
			$request_url .= 'Type='.$type.'&';
		}else{
			return "You must provide an Object Type to search.";
		}

		// Check for Class
		if(!is_null($resource)){
			$request_url .= 'Resource='.$resource.'&';
		}else{
			return "You must provide a resource to search.";
		}

		// Check for Query
		if(!is_null($id)){
			$request_url .= 'ID='.$id.'&';
		}else{
			return "You must provide an id to search.";
		}

		// Check for select statement
		if(!is_null($location)){
			$request_url .= 'Location='.$location.'&';
		}

		// Remove the last character of string if it is a &
		$request_url = trim($request_url, "&");

		// Run the request!
		$results = $this->simpleRequest($request_url);

		// Return the results.
		return $results;
	}

	public function simpleRequest($url)
	{
		// Set full request url
		$request_url = $this->__get('host').$url;

		// Set new url of request
		curl_setopt($this->ch, CURLOPT_URL, $request_url);

		// Save this as the most recently accessed URL
		$this->__set('last_request_url', $request_url);

		// Tell curl to keep old session (logged in already)
		curl_setopt($this->ch, CURLOPT_COOKIESESSION, false);

		// Tell curl what cookie file to use
		curl_setopt($this->ch, CURLOPT_COOKIEFILE, realpath($this->__get('cookie_file')));

		// Get result
		$response = curl_exec($this->ch);

		// If there is a result...
		if($response && strstr($response, '<?xml version'))
		{
			// Convert return to xml element
			$xml = new SimpleXMLElement( $response );

			// Save last result
			$this->__set('last_request_result', $xml);

			// Return result
			return $xml;
		}

		// Else no results...
		else
		{
			// Save last result
			$this->__set('last_request_result', $response);

			// Return result.
			return $response;
		}
	}

	public function parseImageHeaders($imageHeaders)
	{
		// Init final parsed array
		$finalArray = array();

		// Explode headers on new line.
		$headerArray = explode("\r\n", $imageHeaders);

		// Init first key
		$arrayKey = 0;

		// Loop through each line.
		foreach($headerArray as $heading)
		{
			// Separate heading by value and key
			$value = substr( $heading, ( $pos = strpos( $heading, ':' ) ) === false ? 0 : $pos + 1 );
			$key = substr( $heading, 0, ( $pos = strpos( $heading, ':' ) ) === false ? 0 : $pos );

			// If this key is the same as the first key, it's one loop!
			if(isset($firstKey) && $firstKey === $key){
				$arrayKey++;
			}

			// If first key isn't set....
			if(!isset($firstKey))
			{
				// Find the first key in array.
				$firstKey = $key;
			}

			// If we have a value and a key...
			if(isset($value) && $value && isset($key) && $key)
			{
				// Add trimmed info from this heading to final array.
				$finalArray[$arrayKey][trim($key)] = trim($value);
			}
		}

		// Return formatted results!
		return $finalArray;
	}

	public function formatForNexes($object, $agentId = NULL)
	{
		// Init results
		$results = array();

		// Generic Post Data
		$results['title'] = $object->Listing->StreetAddress->StreetNumber.' '.$object->Listing->StreetAddress->StreetName.' '.$object->Listing->StreetAddress->StreetSuffix.', '.$object->Listing->StreetAddress->City.', '.$object->Listing->StreetAddress->StateOrProvince;
		$results['content'] = (string) $object->Listing->ListingData->PublicRemarks;
		$results['postType'] = 'property';

		// Add meta data
		$results['meta']['pyre_status']         = 'For Sale';
		$results['meta']['pyre_full_address']   = (string) $results['title'];
		$results['meta']['pyre_address']        = (string) $object->Listing->StreetAddress->StreetNumber.' '.$object->Listing->StreetAddress->StreetName.' '.$object->Listing->StreetAddress->StreetSuffix;
		$results['meta']['pyre_city']           = (string) $object->Listing->StreetAddress->City;
		$results['meta']['pyre_state']          = (string) $object->Listing->StreetAddress->StateOrProvince;
		$results['meta']['pyre_zip']            = (string) $object->Listing->StreetAddress->PostalCode;
		$results['meta']['pyre_price']          = (string) $object->Listing->ListingData->ListPrice;
		$results['meta']['pyre_built']          = (string) $object->YearBuilt;
		$results['meta']['pyre_bathrooms']      = (string) $object->Baths->BathsTotal;
		$results['meta']['pyre_bedrooms']       = (string) $object->Bedrooms;
		$results['meta']['mls_id']              = (string) $object->Listing->ListingID;

		// Add agents if specified
		if(!is_null($agentId))
		{
			// Agent passed, add to meta
			$results['meta']['pyre_agent']          = $agentId;
		}

		// Tell system no agent was passed if not passed
		else
		{
			// Set no agent meta.
			$results['meta']['pyre_no_agent']       = 'TRUE';
		}

		// Return results.
		return $results;
	}

	/**
	 * Set overload method for template class
	 *
	 * @param string $name  Name of property to set
	 * @param string $value Value to set property to
	 *
	 * @return void
	 */
	public function __set($name, $value)
	{
		// Set property
		$this->_data[$name] = $value;
	}

	/**
	 * Get overload method for template class
	 *
	 * @param string $name Name of property to get
	 *
	 * @return mixed
	 */
	public function __get($name)
	{
		// If a name is passed...
		if(isset($name) && $name)
		{
			// Check for property
			if (array_key_exists($name, $this->_data))
			{
				return $this->_data[$name];
			}
		}

		// No name was passed...
		else
		{
			// return all data
			return $this->_data;
		}

		// Return no results
		return NULL;
	}
}