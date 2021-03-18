<?php 
	# Multi_curl с поддержкой прокси, проверкой прокси
	function multi_curl_parser($urls, $array) {
		
		# Конфигурация из массива
		if (!$array['TIMEOUT']) {$array['TIMEOUT'] = 3;}
		if (!$array['CONNECTTIMEOUT']) {$array['CONNECTTIMEOUT'] = 3;}
		if ($array['PROXY'] and !$array['USE_PROXY']) {include "service/addition.proxy.php";}
		if ($array['COOKIE']) {$array['COOKIE'] = $_SERVER['DOCUMENT_ROOT'].'/service/'.$array['COOKIE'];}
		if ($array['SSL']) {$array['SSL'] = $_SERVER['DOCUMENT_ROOT'].'/service/'.$array['SSL'];}
	
		
		$curl = [];
		$result = [];
		$ch = curl_multi_init();

		foreach ($urls as $id => $url) {
			
			// print $url; print "<br>"; 
			$curl[$id] = curl_init();

			curl_setopt($curl[$id], CURLOPT_URL, "$url");
			curl_setopt($curl[$id], CURLOPT_HEADER, $array['HEADER']);
			curl_setopt($curl[$id], CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl[$id], CURLOPT_TIMEOUT, $array['TIMEOUT']);  
			curl_setopt($curl[$id], CURLOPT_CONNECTTIMEOUT, $array['CONNECTTIMEOUT']);  
			curl_setopt($curl[$id], CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($curl[$id], CURLOPT_REFERER, $array['REFERER']);
		
			if (!$array['SSL']) {
				curl_setopt($curl[$id], CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($curl[$id], CURLOPT_SSL_VERIFYHOST, false);
			}
			if ($array['SSL'] == "cacert.pem") {
				curl_setopt($curl[$id], CURLOPT_SSL_VERIFYPEER, TRUE);
				curl_setopt($curl[$id], CURLOPT_CAINFO, $array['SSL']);
			}
			if ($array['POST']) {
				curl_setopt($curl[$id], CURLOPT_POST, true);
				curl_setopt($curl[$id], CURLOPT_POSTFIELDS, $array['POST']);
			}
			if ($array['ENCODING']) {
				curl_setopt($curl[$id], CURLOPT_ENCODING, $array['ENCODING']);
			}
			
			
			
			
			//Включаем прокси в работы
			if ($array['PROXY']) {
				
				# ----------------------- ### ---------------------- #
				if (!$array['USE_PROXY']) {
					$rand_number = mt_rand(0,count($proxy_ip - 1));
					curl_setopt($curl[$id], CURLOPT_PROXY, $proxy_ip[$rand_number]); 
					curl_setopt($curl[$id], CURLOPT_PROXYPORT, $proxy_port[$rand_number]); 
					curl_setopt($curl[$id], CURLOPT_PROXYUSERPWD, $proxy_login[$rand_number].":".$proxy_password[$rand_number].""); 
					
					//Включаем проверку работы прокси
					if ($array['PROXY_LOGGING']) {
						$ipuse[$id] = str_replace(".","", $proxy_ip[$rand_number]);
						$iporig[$id] = $proxy_ip[$rand_number];
					
					}
				}
				if ($array['USE_PROXY']) {
					$use_proxy = explode(":", $array['USE_PROXY']);
					curl_setopt($curl[$id], CURLOPT_PROXY, $use_proxy[0]); 
					curl_setopt($curl[$id], CURLOPT_PROXYPORT, $use_proxy[1]); 
					curl_setopt($curl[$id], CURLOPT_PROXYUSERPWD, $use_proxy[2].":".$use_proxy[3].""); 
					
					//Включаем проверку работы прокси
					if ($array['PROXY_LOGGING']) {
						$ipuse[$id] = str_replace(".","", $use_proxy[0]);
						$iporig[$id] = $use_proxy[0];
					
					}
				}
				# ---------------------- ### ----------------------- #
				
			}
			
			
			
			
			
			
			if (!$array['USERAGENT']) {
				curl_setopt($curl[$id], CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:67.0) Gecko/20100101 Firefox/67.0');
			} else {
				curl_setopt($curl[$id], CURLOPT_USERAGENT, $array['USERAGENT']);
			}
			

			
			if ($array['COOKIE']) {
				curl_setopt($curl[$id], CURLOPT_COOKIEJAR, $array['COOKIE']);
				curl_setopt($curl[$id], CURLOPT_COOKIEFILE, $array['COOKIE']);
			}
			curl_multi_add_handle($ch, $curl[$id]);
		}

		$running = null;
		do {
			usleep(25000); //sleep 0.025 seconds
			curl_multi_exec($ch, $running);
		} while($running > 0);

		foreach($curl as $id => $c) {
			$result[$id] = curl_multi_getcontent($c);  
			curl_multi_remove_handle($ch, $c);
		
		//Включаем проверку работы прокси
		if ($array['PROXY_LOGGING']) {
			mysqli_query("INSERT INTO `problem_proxy` (`ip`, `count`, `iporig`) VALUES ('".$ipuse[$id]."', '0', '".$iporig[$id]."')");
			if ($result[$id] == "") {
				mysqli_query("UPDATE `problem_proxy` SET count = count+'1' WHERE `ip`='".$ipuse[$id]."' ");
			}
			
		}
		
		}
		curl_multi_close($ch);
		return $result;
	}