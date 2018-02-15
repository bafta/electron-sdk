<?php

require_once('config.php');

class s2sClient{

    private $accessToken;
    private $accessTokenExpiry;
    private $userId;
    private $username;
    private $userType;
    private $accessKeyId;
    private $secretAccessKey;
    private $sessionToken;
    private $bucket;
    private $region = 'us-east-1';

    function __construct($accessToken=false){
        $this->accessToken = $accessToken;
    }

    private function get($endpoint){
        $ch = curl_init(S2S_API_ADDRESS . $endpoint);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Bearer " . $this->accessToken));

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$code, $response];
    }

    private function post($endpoint, $data=null, $headers=[]){
        $ch = curl_init(S2S_API_ADDRESS . $endpoint);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, count($data));
        if($data) curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

		if($this->accessToken != '') array_push($headers, "Authorization: Bearer " . $this->accessToken);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$code, $response];
    }

	private function processStandardResponse($code, $response){
		$decoded = json_decode($response);
		if($code < 200 || $code >= 300) {
            return [
                "successful"    => false,
                "error"         => $decoded->error->message
            ];
        }
        else {
			return [
				"successful"	=> true,
				"result"		=> $decoded->result
			];
        }
	}

    private function parseAccessToken($token){
        $payload = explode('.', $token)[1];
        $payload = str_replace('-', '+', $payload);
        $payload = str_replace('_', '/', $payload);
        $payload = base64_decode($payload);
        $payload = json_decode($payload);

        $this->accessTokenExpiry = $payload->exp;
        $this->userId = $payload->sub;
        $this->username = $payload->username;
        $this->userType = $payload->userType;
        $this->accessKeyId = $payload->accessKeyId;
        $this->secretAccessKey = $payload->secretAccessKey;
        $this->sessionToken = $payload->sessionToken;
        $this->bucket = substr($payload->scope, strpos($payload->scope, 'bucket:') + strlen('bucket:'));
    }

    public function login($username=USERNAME, $password=PASSWORD){
        list($code, $response) = $this->post(
            "/oauth/tokens",
            [
                "client_id"     => CLIENT_ID,
                "grant_type"    => "password",
                "scope"         => "bucket:* path:* read write admin"
            ],
            [
                'Authorization: Basic '.base64_encode($username.':'.$password)
            ]
        );

        $decoded = json_decode($response);

        if($code != 200) {
            return [
                "successful"    => false,
                "error"         => $decoded->error->message
            ];
        }
        else {
            $this->accessToken = $decoded->result->access_token;
            $this->parseAccessToken($this->accessToken);
            return [
                "successful"    => true,
                "token"         => $this->accessToken
            ];
        }
    }

    public function listFiles($parent='~', $sortBy='key', $sortOrder='ASC', $page=0, $perPage=DEFAULT_RESULTS_PER_PAGE){
        list($code, $response) = $this->get(
            '/files?'.
            'parent='.$parent.
			'&sortBy='.$sortBy.
			'&sortOrder='.$sortOrder.
            '&page='.$page.
            '&perPage='.$perPage
        );
        return $this->processStandardResponse($code, $response);
    }

    public function getFile($fileId='~'){
        list($code, $response) = $this->get('/files/'.$fileId);
        return $this->processStandardResponse($code, $response);
    }

	public function searchFiles($search, $sortBy='key', $sortOrder='ASC', $page=0, $perPage=DEFAULT_RESULTS_PER_PAGE){
		list($code, $response) = $this->get(
            '/files?'.
            'search='.$search.
			'&sortBy='.$sortBy.
			'&sortOrder='.$sortOrder.
            '&page='.$page.
            '&perPage='.$perPage
        );
        return $this->processStandardResponse($code, $response);
	}

    public function getFileUploader($fileId, $uploaderId, $hash){
        list($code, $response) = $this->get('/files/'.$fileId.'/uploaders/'.$uploaderId.'?hash='.$hash);
		return $this->processStandardResponse($code, $response);
    }

	public function createFileUploader($fileId, $expiry='', $instructions=''){
		//if no expiry provided - default to 1 day
		if($expiry == '') $expiry = strtotime('+1 day');
		list($code, $response) = $this->post(
            "/files/".$fileId.'/uploaders',
            [
                "expiry"    	=> $expiry,
                "instructions"  => $instructions
            ]
        );
		return $this->processStandardResponse($code, $response);
	}

	public function createFolder($path){
		list($code, $response) = $this->post(
            "/files",
            [
                "key"    	=> $path,
                "fileType"  => 'FOLDER'
            ]
        );
		return $this->processStandardResponse($code, $response);
	}

    public function getAccessToken(){
        return $this->accessToken;
    }

    public function getAccessTokenExpiry(){
        return $this->accessTokenExpiry;
    }

    public function getUserId(){
        return $this->userId;
    }

    public function getUsername(){
        return $this->username;
    }

    public function getUserType(){
        return $this->userType;
    }

    public function getAccessKeyId(){
        return $this->accessKeyId;
    }

    public function getSecretAccessKey(){
        return $this->secretAccessKey;
    }

    public function getSessionToken(){
        return $this->sessionToken;
    }

    public function getBucket(){
        return $this->bucket;
    }

    public function getRegion(){
        return $this->region;
    }

}

?>
