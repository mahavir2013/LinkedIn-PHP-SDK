LinkedIn-PHP-SDK
================
<pre>
PHP SDK for LinkedIn API

You can add you code to implement this code.


Example CODE:
[code]
session_start();

require_once('linkedInAPI.php');

// Change these
define('API_KEY',      'Your Api Key');
define('API_SECRET',   'Your Secret Key');
define('CALLBACK_URL', 'application redirect URI');
define('SCOPE',        'r_basicprofile r_fullprofile r_emailaddress rw_nus');
 

$config = array(
	'appKey' => API_KEY,
	'appSecret' => API_SECRET
);


$linkedin = new Linkedin($config);

if(!$linkedin->getUser()){
	$params = array(
		'callbackUrl' => CALLBACK_URL,
		'scope' => SCOPE,
	);
	$response = $linkedin->connect($params);
	if($response['status']=='success') {
		$access_token = $response['access_token'];
	} else {
		echo $response['status'].': '.$response['message'];die;
	}
}

//$linkedin->setAccessToken($access_token); //use this if you have a accesstoken

if($access_token){
	$user = $linkedin->getProfile();
	if($user['info']['http_code']==200){
		$user = json_decode($user['linkedin']);
		print "Hello $user->firstName $user->lastName.";
	}
}
[/code]
</pre>
