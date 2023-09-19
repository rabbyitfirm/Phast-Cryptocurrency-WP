<?php
//////////////////////////
/// We suggest that you read the Zanjir Document before using this library
/// https://github.com/Zanjir/API-Documentation/blob/main/Documentation.md
/// Author : Zanjir Network
/// Website : https://zanjir.network
/////////////////////////
namespace Zanjir;

define("ZANJIR_API_URL", "https://gate.zanjir.network/api/");
class Zanjir {

    public function create(Array $params) {
        $result = Zanjir::_curl("create",$params,"POST");
        return json_decode($result);
    }

    public function verify(String $id) {
        $result = Zanjir::_curl("verify",$id,'GET');
        return json_decode($result);
    }

    public function qrCodeBase64($in_wallet,$amount,$size=250) {
        $params = "$in_wallet/$amount/$size";
        return Zanjir::_curl("qr/base64",$params,'GET');
    }

    public function coin_list() {
        $result = Zanjir::_curl("tickers",NULL,'GET');
        return json_decode($result);
    }

    public function ticker_info($ticker) {
        $coinlists =   Zanjir::coin_list();
        foreach($coinlists as $coin){
            if($coin->name == strtolower($ticker)){
                return $coin;
            }
        }
    }
    public function error_dictionary($error_code){
        $code[1001] = "Your invoice amount is less than the allowed amount of this cryptocurrency. Please change your cryptocurrency.";
        $code[1002] = "Your wallet address structure is invalid or does not support Zanjir.";
        $code[1003] = "Callback url is invalid.";
        $code[1004] = "Ticker is invalid.";
        $code[1005] = "currency is invalid.";
        $code[1006] = "The secret has not been verified.";
        $code[1007] = "Ticker is Disable.";
        $code[1008] = "The ticker is temporarily unavailable.";
        return $code[$error_code];
    }
    private static function _curl($endpoint, $params,$method = "POST") {
        $curl = curl_init();
        $CURLOPT_URL = ZANJIR_API_URL . $endpoint .'/';
        if($method == "GET"){
            $CURLOPT_URL = $CURLOPT_URL .  $params;
            $http_build_query = NULL;
        }else{
            $http_build_query =   http_build_query($params);
        }
        curl_setopt_array($curl, array(
                CURLOPT_URL => $CURLOPT_URL,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_POSTFIELDS => $http_build_query,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/x-www-form-urlencoded'
                ),
            ));
        $response = curl_exec($curl);
        curl_close($curl);
        return ($response);
    }


}