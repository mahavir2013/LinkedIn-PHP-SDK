<?php
class LinkedInException extends Exception {}

class Linkedin{
  const _API_OAUTH_REALM             = 'http://api.linkedin.com';
	const _API_OAUTH_VERSION           = '1.0';
	
	const _URL_AUTH = 'https://www.linkedin.com/uas/oauth2/authorization?';
	const _URL_ACCESS = 'https://www.linkedin.com/uas/oauth2/accessToken?';
	const _URL_API = 'https://api.linkedin.com';
	
	const _DEFAULT_RESPONSE_FORMAT     = 'json';
	const _RESPONSE_JSON               = 'json';
	const _RESPONSE_JSONP              = 'jsonp';
	const _RESPONSE_XML                = 'xml';
	
	//APP properties
	protected $application_key, 
			$application_secret;
	
	// oauth properties
	protected $callback;
	protected $access_token = NULL;
	protected $expires_at = NULL;
	protected $scope;
	protected $code;
	
	protected $response_format         = self::_DEFAULT_RESPONSE_FORMAT;
	
	public $last_request_headers, 
		$last_request_url;
	
	function __construct($config) {
		if(!is_array($config)) {
			// bad data passed
			throw new LinkedInException('LinkedIn->__construct(): bad data passed, $config must be of type array.');
		}
		$this->setApplicationKey($config['appKey']);
		$this->setApplicationSecret($config['appSecret']);
		
		if(isset($_SESSION['access_token']) && $_SESSION['access_token']) {
			$this->setAccessToken($_SESSION['access_token']);
		}
	}
	
	public function connect($params){
		if(!is_array($params)) {
			// bad data passed
			throw new LinkedInException('LinkedIn->connect(): bad data passed, $params must be of type array.');
		}
		$this->setCallbackUrl($params['callbackUrl']);
		$this->setScope($params['scope']);
		
		$response = $this->getAuthorizationCode();
		
		if($response['status']=='success'){
			$_SESSION['access_token'] = $this->access_token;
			$response['access_token'] = $this->access_token;
		}
		return $response;
	}
	
	public function getApplicationKey() {
		return $this->application_key;
	}
	
	public function getApplicationSecret() {
		return $this->application_secret;
	}
	
	public function getCallbackUrl() {
		return $this->callback;
	}
	
	public function getScope() {
	  return $this->scope;
	}
	
	public function getAccessToken() {
	  return $this->access_token;
	}
	
	public function getCode() {
	  return $this->code;
	}
	
	public function getExpiresAt() {
		return $this->expires_at;
	}
	
	public function getResponseFormat() {
	  return $this->response_format;
	}
	
	public function setApplicationKey($key) {
	  $this->application_key = $key;
	}
	
	public function setApplicationSecret($secret) {
	  $this->application_secret = $secret;
	}
	
	public function setCallbackUrl($url) {
	  $this->callback = $url;
	}
	
	public function setScope($scope) {
	  $this->scope = $scope;
	}
	
	public function setCode($code) {
	  $this->code = $code;
	}
	
	public function setAccessToken($access_token) {
		// set token
		$this->access_token = $access_token;
	}
	
	public function setExpiresAt($expires_at) {
		// set token
		$this->expires_at = $expires_at;
	}
	
	public function setResponseFormat($format = self::_DEFAULT_RESPONSE_FORMAT) {
	  $this->response_format = $format;
	}
	
	public function getAuthorizeToken() {
		$params = array('grant_type' => 'authorization_code',
						'client_id' => $this->application_key,
						'client_secret' => $this->application_secret,
						'code' => $this->code,
						'redirect_uri' => $this->callback,
				  );
		 
		// Access Token request
		$url = self::_URL_ACCESS  . http_build_query($params, '', '&');
		 
		// Tell streams to make a POST request
		$context = stream_context_create(
						array('http' => 
							array('method' => 'POST',
							)
						)
					);
		
		try {
			// Retrieve access token information
			$response = file_get_contents($url, false, $context);
		} catch(Exception $e) {
			$result = array(
				'status' => 'fail',
				'error' => 'access_token',
				'message' => $e->getMessage(),
			);
			return $result;
		}
	 
		// Native PHP object, please
		$token = json_decode($response);
	 
		// Store access token and expiration time
		$this->access_token = $token->access_token;
		$this->expires_at   = time() + $token->expires_in;
		
		$result = array(
			'status' => 'success'
		);
		return $result;
	}
	public function getAuthorizationCode(){
		// OAuth 2 Control Flow
		if (isset($_GET['error'])) {
			// LinkedIn returned an error
			//print $_GET['error'] . ': ' . $_GET['error_description'];
			$result = array(
				'status' => 'fail',
				'error' => $_GET['error'],
				'message' => $_GET['error_description'],
			);
			return $result;
		} elseif (isset($_GET['code'])) {
			// User authorized your application
			$this->setCode($_GET['code']);
			if ($_SESSION['state'] == $_GET['state']) {
				// Get token so you can make API calls
				return $this->getAuthorizeToken();
			} else {
				// CSRF attack? Or did you mix up your states?
				$result = array(
					'status' => 'fail',
					'error' => 'CSRF',
					'message' => 'State does not match. You may be a victim of CSRF!',
				);
				return $result;
			}
		} else { 
			if ((empty($this->expires_at)) || (time() > $this->expires_at)) {
				// Token has expired, clear the state
				$this->setAccessToken(NULL);
				$this->setExpiresAt(NULL);
			}
			if (empty($this->access_token)) {
				// Start authorization process
				$this->getAuthCode();
			}
		}
	}
	
	function getAuthCode() {
		$params = array('response_type' => 'code',
						'client_id' => $this->application_key,
						'scope' => $this->scope,
						'state' => uniqid('', true), // unique long string
						'redirect_uri' => $this->callback,
				  );
	 
		// Authentication request
		$url = self::_URL_AUTH . http_build_query($params, '', '&');
		
		// Needed to identify request when it returns to us
		$_SESSION['state'] = $params['state'];
		
		// Redirect user to authenticate
		header("Location: $url");
		exit;
	}
	
	/*protected function fetch($method, $url, $data = '') {
		$params = array('oauth2_access_token' => $this->getAccessToken(),
						'format' => $this->getResponseFormat(),
				  );
		 
		// Need to use HTTPS
		$url = $url . '?' . http_build_query($params, '', '&');
		// Tell streams to make a (GET, POST, PUT, or DELETE) request
		$context = stream_context_create(
						array('http' => 
							array('method' => $method,
							)
						)
					);
		
		
		// Hocus Pocus
		return $response = file_get_contents($url, false, $context);
	}*/
	
	protected function fetchCURL($method, $url, $data = NULL) {
		$url = $url . '?oauth2_access_token=' . $this->getAccessToken();
		
		$data1 = '<?xml version="1.0" encoding="UTF-8"?>
		<share>
  <comment>test Check out the LinkedIn Share API!</comment>
  <content>
    <title>test LinkedIn Developers Documentation On Using the Share API</title>
    <description>Leverage test the Share API to maximize engagement on user-generated content on LinkedIn</description>
    <submitted-url>https://developer.linkedin.com/documents/share-api</submitted-url>
    <submitted-image-url>http://m3.licdn.com/media/p/3/000/124/1a6/089a29a.png</submitted-image-url> 
  </content>
  <visibility> 
    <code>anyone</code> 
  </visibility>
</share>';

		try {
			if(!$handle = curl_init()) {
				// cURL failed to start
				throw new LinkedInException('LinkedIn->fetch(): cURL did not initialize properly.');
			}
			
			// set cURL options, based on parameters passed
			curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
			curl_setopt($handle, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($handle, CURLOPT_URL, $url);
			curl_setopt($handle, CURLOPT_VERBOSE, FALSE);
			
			// configure the header we are sending to LinkedIn - http://developer.linkedin.com/docs/DOC-1203
			//$header = array($oauth_req->to_header(self::_API_OAUTH_REALM));
			if(is_null($data)) {
				// not sending data, identify the content type
				$header[] = 'Content-Type: text/plain; charset=UTF-8';
				switch($this->getResponseFormat()) {
					case self::_RESPONSE_JSON:
						$header[] = 'x-li-format: json';
						break;
					case self::_RESPONSE_JSONP:
						$header[] = 'x-li-format: jsonp';
						break;
				}
			} else {
				$header[] = 'Content-Type: text/xml; charset=UTF-8';
				curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
			}
			curl_setopt($handle, CURLOPT_HTTPHEADER, $header);
			
			// set the last url, headers
			$this->last_request_url = $url;
			$this->last_request_headers = $header;
			
			// gather the response
			$return_data['linkedin']        = curl_exec($handle);
			$return_data['info']            = curl_getinfo($handle);
			
			// check for throttling
			if(self::isThrottled($return_data['linkedin'])) {
				throw new LinkedInException('LinkedIn->fetch(): throttling limit for this user/application has been reached for LinkedIn resource - ' . $url);
			}
			
			//TODO - add check for NO response (http_code = 0) from cURL
			
			// close cURL connection
			curl_close($handle);
			
			// no exceptions thrown, return the data
			return $return_data;
		} catch(OAuthException $e) {
			// oauth exception raised
			throw new LinkedInException('OAuth exception caught: ' . $e->getMessage());
		}
	}
	
	public static function isThrottled($response) {
		$return_data = FALSE;
		
		// check the variable
		if(!empty($response) && is_string($response)) {
			// we have an array and have a properly formatted LinkedIn response
			
			// store the response in a temp variable
			$temp_response = json_decode($response,true);//self::xmlToArray($response);
			if($temp_response !== FALSE) {
				// check to see if we have an error
				if(array_key_exists('error', $temp_response) && ($temp_response['error']['children']['status']['content'] == 403) && preg_match('/throttle/i', $temp_response['error']['children']['message']['content'])) {
					// we have an error, it is 403 and we have hit a throttle limit
					$return_data = TRUE;
				}
			}
		}
		return $return_data;
	}
	
	public function getUser(){	
		$status = false;
		try{
			$response = $this->fetchCURL('GET', self::_URL_API.'/v1/people/~:(id)');
			if($response['info']['http_code']==200){
				$status = json_decode($response['linkedin'])->id;
			}
		} catch(Exception $e) {
			//echo $e->getMessage();
			$status = false;
		}
		
		return $status;
	}
	
	public function getProfile(){	
		return $this->fetchCURL('GET', self::_URL_API.'/v1/people/~:(id,firstName,lastName,headline,siteStandardProfileRequest,public-profile-url,picture-url)');
	}
	
	public function share($data){
		return $this->fetchCURL('POST', self::_URL_API.'/v1/people/~/shares', $data);
	}
}
