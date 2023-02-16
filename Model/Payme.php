<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Sunflowerbiz\Payme\Model;

class Payme 
{
	public $values;
	
	public function SetKV($key,$value)
    {
        $this->values[$key] = $value;
    }
	public function GetKV($key)
    {
       return isset($this->values[$key])?$this->values[$key]:false ;
    }
	
	public function getDomain()
    {
        if(isset($this->values['sandbox_mode']) && $this->values['sandbox_mode'])
		return 'https://sandbox.api.payme.hsbc.com.hk';
		else
		return 'https://api.payme.hsbc.com.hk';
    }
	
	public function getAuthUrl()
    {
		return $this->getDomain().'/oauth2/token';
    }
	
	public function getPaymentRequestUrl()
    {
		return $this->getDomain().'/payments/paymentrequests';
    }
	
	public function getRefundUrl()
    {
		return $this->getDomain().'/payments/transactions/'. $this->values['transactionId'].'/refunds';
    }
	
	public function goAuthData()
    {
		$requestData=array('client_id'=> $this->values['client_id'],'client_secret'=> $this->values['client_secret']);
		$postdata=$this->ToUrlParams($requestData);
		$headers = array(
			// Request headers
			'Accept:application/json',
			'Content-Type:application/x-www-form-urlencoded',
			'api-version:0.12',
		);
		$this->logData(" oauth2 requrest >>>> \r\n header: ".json_encode($headers)." \r\n data: ".json_encode($requestData));
		$res=$this->postCurl($postdata,$this->getAuthUrl(),$headers); 
		$this->logData(" oauth2 response >>>> \r\n : ".($res));
		$returnArray=json_decode($res,true);
		$Authorization="";
		if(isset($returnArray['accessToken'])) {
			$Authorization=$returnArray['accessToken'];
			$this->values['Authorization']=$Authorization;
			return true;
		}else{
			$this->values['error']=true;
			$this->values['errorCode']=$this->values['errorDescription']="Errors.";
			if(isset($returnArray['errors'][0]['errorCode'])) {
				$this->values['errorCode']=$returnArray['errors'][0]['errorCode'];
				$this->values['errorDescription']=$returnArray['errors'][0]['errorDescription'];
			}
			return false;
		}
    }
	
	public function goPaymentData()
    {
		if(!$this->goAuthData())
		return false;
		
		$orderdata["totalAmount"]=$this->values['totalAmount'];
		$orderdata["currencyCode"]=$this->values['currencyCode'];
		$orderdata["notificationUri"]=$this->values['notificationUri'];
		$orderdata["appSuccessCallback"]=$this->values['appSuccessCallback'];
		$orderdata["appFailCallback"]=$this->values['appFailCallback'];
		$orderdata["effectiveDuration"]=$this->values['effectiveDuration'];
		$orderdata["merchantData"]=array(
									'orderId'=>$this->values['orderId']
								);
		 
		$postdata=json_encode($orderdata);
		
		
		$Digest='SHA-256='.$this->base64hash256($postdata,$this->values['signing_key']);
		$traceid=$this->create_uuid();
		$Authorization='Bearer '.$this->values['Authorization'];
		$requesttime=date('Y-m-d\TH:i:s.u\Z');
		$headerHash=array(
			'(request-target)'=>'post /payments/paymentrequests',
			'Api-Version'=>'0.12',
			'Request-Date-Time'=>$requesttime,
			'Content-Type'=>'application/json',
			'Digest'=>$Digest,
			'Accept'=>'application/json',
			'Trace-Id'=>$traceid,
			'Authorization'=>$Authorization
		);
		$signingBase="";
		foreach($headerHash as $h=>$hh){
			if ($signingBase !== '')$signingBase .= "\n";
			$signingBase .= strtolower($h).': '.$headerHash[$h];
		}
		
		$signaturehash=$this->base64hash256($signingBase,$this->values['signing_key'],true);
		
		$Signature='keyId="'.$this->values['signing_keyid'].'",algorithm="hmac-sha256",headers="'.implode(' ',array_keys($headerHash)).'",signature="'.($signaturehash).'"';
		$headers = array(
			// Request headers
			'Api-Version:0.12',
			'Content-Type:application/json',
			'Accept:application/json',
			'Accept-Language:en-US',
			'Trace-Id:'.$traceid,
			'Request-Date-Time:'.$requesttime,
			'Authorization:'.$Authorization,
			'Digest:'.$Digest,
			'Signature:'.$Signature,
		);
		$this->logData(" paymentrequests requrest >>>> \r\n header: ".json_encode($headers)." \r\n data: ".($postdata));
		$res=$this->postCurl($postdata,$this->getPaymentRequestUrl(),$headers); 
		$this->logData(" paymentrequests response >>>> \r\n : ".($res));
		
		$returnArray=json_decode($res,true);
		$Authorization="";
		if(isset($returnArray['paymentRequestId'])) {
			$this->values['ReturnPaymentRequest']=$returnArray;
			$this->values['webLink']=$returnArray['webLink'];
			$this->values['appLink']=$returnArray['appLink'];
			return true;
		}else{
			$this->values['error']=true;
			if(isset($returnArray['errors'][0]['errorCode'])) {
				$this->values['errorCode']=$returnArray['errors'][0]['errorCode'];
				$this->values['errorDescription']=$returnArray['errors'][0]['errorDescription'];
			}
			return false;
		}
    }
	
	public function goRefundData()
    {
		
		if(!$this->goAuthData())
		return false;
		
		$orderdata["amount"]=$this->values['totalAmount'];
		$orderdata["currencyCode"]=$this->values['currencyCode'];
		$orderdata["reasonCode"]=$this->values['reasonCode'];//'00';
		$orderdata["reasonMessage"]=$this->values['reasonMessage'];//'System Refund';
								
		$postdata=json_encode($orderdata);
		
		
		$Digest='SHA-256='.$this->base64hash256($postdata,$this->values['signing_key']);
		$traceid=$this->create_uuid();
		$Authorization='Bearer '.$this->values['Authorization'];
		$requesttime=date('Y-m-d\TH:i:s.u\Z');
		$headerHash=array(
			'(request-target)'=>'post /payments/transactions/'. $this->values['transactionId'].'/refunds',
			'Api-Version'=>'0.12',
			'Request-Date-Time'=>$requesttime,
			'Digest'=>$Digest,
			'Trace-Id'=>$traceid,
			'Authorization'=>$Authorization
		);
		$signingBase="";
		foreach($headerHash as $h=>$hh){
			if ($signingBase !== '')$signingBase .= "\n";
			$signingBase .= strtolower($h).': '.$headerHash[$h];
		}
		
		$signaturehash=$this->base64hash256($signingBase,$this->values['signing_key'],true);
		
		$Signature='keyId="'.$this->values['signing_keyid'].'",algorithm="hmac-sha256",headers="'.implode(' ',array_keys($headerHash)).'",signature="'.($signaturehash).'"';
		$headers = array(
			// Request headers
			'Api-Version:0.12',
			'Content-Type:application/json',
			'Accept:application/json',
			'Accept-Language:en-US',
			'Trace-Id:'.$traceid,
			'Request-Date-Time:'.$requesttime,
			'Authorization:'.$Authorization,
			'Digest:'.$Digest,
			'Signature:'.$Signature,
		);
		$this->logData(" refund requrest >>>> \r\n header: ".json_encode($headers)." \r\n data: ".($postdata));
		$res=$this->postCurl($postdata,$this->getRefundUrl(),$headers); 
		$this->logData(" refund response >>>> \r\n : ".($res));
		
		$returnArray=json_decode($res,true);
		$Authorization="";
		if(isset($returnArray['refundId'])) {
			$this->values['refundId']=$returnArray['refundId'];
			$this->values['ReturnRefundRequest']=$returnArray;
			return true;
		}else{
			$this->values['error']=true;
			if(isset($returnArray['errors'][0]['errorCode'])) {
				$this->values['errorCode']=$returnArray['errors'][0]['errorCode'];
				$this->values['errorDescription']=$returnArray['errors'][0]['errorDescription'];
			}
			return false;
		}
    }
	
	public function logData($str)
    {
       if(isset($this->values['enable_log']) && $this->values['enable_log']){
			if( $dumpFile = fopen($this->values['basepath'] .'/var/log/Payme.log', 'a+')){
					fwrite($dumpFile, "\r\n".date("Y-m-d H:i:s").' : '.$str."\r\n");
			}
		}
        return ;
    }
	
	
	public function ToUrlParams($values)
    {
        $buff = "";
        foreach ($values as $k => $v) {
            if ($k != "sign" && $v != "" && !is_array($v)) {
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }
	
	public function base64hash256($text,$signingkey,$hmac=false){
		if($hmac)
		$hash = hash_hmac("sha256", (utf8_encode($text)),base64_decode($signingkey),true);
		else
		$hash = hash("sha256", (utf8_encode($text)),base64_decode($signingkey));
		
		$output=base64_encode($hash);
		return $output;
	}
	
	public function create_uuid($prefix = ""){    //可以指定前缀
		//$str = md5(uniqid(random_int(), true));  
		$str =hash('md5',uniqid(random_int(1000,9999), true));
		$uuid  = substr($str,0,8) . '-';  
		$uuid .= substr($str,8,4) . '-';  
		$uuid .= substr($str,12,4) . '-';  
		$uuid .= substr($str,16,4) . '-';  
		$uuid .= substr($str,20,12);  
		return $prefix . $uuid;
	}
	
 	public function postCurl($xml, $url,  $headers=false, $useCert = false, $second = 30,$javascript_loop=0)
    {
		//echo 'POST: '.$url.'<br>Data: '.json_encode($xml).'<br>Header: '.json_encode($headers).'<br>';
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);

        curl_setopt($ch, CURLOPT_URL, $url);
		
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);//严格校验
		
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		if($headers)
   		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行curl
        $content = curl_exec( $ch );
	
		$response = curl_getinfo( $ch );
		
	    //echo 'RETURN: '.($content).'<br>-----<br>';	
		curl_close ( $ch );
		if ($response['http_code'] == 301 || $response['http_code'] == 302) {
			ini_set("user_agent", "Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1");
	
			if ( $tmheaders = get_headers($response['url']) ) {
				foreach( $tmheaders as $value ) {
					if ( substr( strtolower($value), 0, 9 ) == "location:" )
						return $this->postCurl( $xml ,trim( substr( $value, 9, strlen($value) ) ),$headers , $useCert , $second );
				}
			}
		}
	
		if (    ( preg_match("/>[[:space:]]+window\.location\.replace\('(.*)'\)/i", $content, $value) || preg_match("/>[[:space:]]+window\.location\=\"(.*)\"/i", $content, $value) ) && $javascript_loop < 5) {
			return $this->postCurl($xml,$value[1],$headers , $useCert , $second , $javascript_loop+1 );
		} else {
			return $content;
		}
		
    }


}