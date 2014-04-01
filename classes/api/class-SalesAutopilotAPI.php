<?php
/**
 * SalesAutopilot simple API
 * 
 * Uses curl if available, falls back to file_get_contents and HTTP stream.
 *
 * @author Gyorgy Khauth <gykhauth@salesautopilot.com>
 * @version 1.0.0
 */
class SalesAutopilotAPI
{
    private $api_username;
    private $api_password;
    private $api_endpoint = 'restapi.emesz.com';
	
	public $errorMessage;
	public $errorCode;

    /**
     * Create a new instance
     * @param string $api_key Your MailChimp API key
     */
    function __construct($api_username,$api_password)
    {
        $this->api_username = $api_username;
        $this->api_password = $api_password;
    }

    /**
     * Call an API method. Every request needs the API username and password, so that is added automatically -- you don't need to pass it in.
     * @param  string $method The API method to call, e.g. 'lists/list'
     * @param  array  $args   An array of arguments to pass to the method. Will be json-encoded for you.
     * @return array          Associative array of json decoded API response.
     */
    public function call($method, $args=array())
    {
        return $this->makeRequest($method, $args);
    }

    /**
     * Performs the underlying HTTP request. Not very exciting
     * @param  string $method The API method to be called
     * @param  array  $args   Assoc array of parameters to be passed
     * @return array          Assoc array of decoded result
     */
    private function makeRequest($method, $args=array())
    {      
        $url = 'http://'.$this->api_username.':'.$this->api_password.'@'.$this->api_endpoint.'/'.$method;

        if (function_exists('curl_init') && function_exists('curl_setopt')){
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($ch, CURLOPT_USERAGENT, 'PHP-SalesAutopilotAPI');       
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			if (sizeof($args) > 0) {
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($args));
			}
            $result = curl_exec($ch);
			$info = curl_getinfo($ch);
			if ($info['http_code'] != 200) {
				$this->errorCode = $info['http_code'];
			}
            curl_close($ch);
        } else {
            $json_data = json_encode($args);
            $result    = file_get_contents($url, null, stream_context_create(array(
                'http' => array(
                    'protocol_version' => 1.1,
                    'user_agent'       => 'PHP-SalesAutopilotAPI',
                    'method'           => 'POST',
                    'header'           => "Content-type: application/json\r\n".
                                          "Connection: close\r\n" .
                                          "Content-length: " . strlen($json_data) . "\r\n",
                    'content'          => $json_data,
                ),
            )));
        }

        return $result ? json_decode($result, true) : false;
    }
}