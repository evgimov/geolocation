<?php
include_once('lib/mobile_detect/Mobile_Detect.php');
include_once('geo.inc.php');

	$outputData = array();
	$detect = new Mobile_Detect;
	// create an object to work with Geo API
	$geo = Geo::getInstance(); 
	// get the ip address
	$ip = $geo->get_ip(); 
	// determine the refferer
	$reff = $geo->define_refferer();


	// if the user came from mobile device or tablet
	if( $detect->isMobile() || $detect->isTablet() ){
	  $country_name = $geo->find_country_providers($ip); // search country by ip of gsm provider
	  // if we didn't find the country in the list of providers 
	  if (!isset($country_name)){
	    // using the ipaddresslabs service
	    $city_name_eng = $geo->find_with_service("SAKEQAN36YHLWT359X2Z",$ip); 
	    $city_name_ru = $geo->get_transliteration($city_name_eng);
	    //get all variants of word declination
	    $outputData = $geo->get_morphy($city_name_ru); 
	    $country = mb_strtolower($city_name_eng);
	    $image = $geo->get_img($country);

	    $outputData["img"] = $image; 
	  }
	  else $outputData["msg"] = "Бесплатная доставка";
	}

	else {
	  if(isset($reff)){
	    // if user came from yandex search engine
	    if ($reff == 'yandex'){
	      $phrase = urldecode($reff);
	      // get id from url
	      preg_match('"lr=(.*?)[^&]*"', $phrase, $arr);
	      $yandex_id = substr($arr[0], 3);
	      // find the id in yandex database
	      $sql = "select * from yandex_ru where yandex_id='$yandex_id'";
	      $res = mysql_query($sql);
	      if ($row = mysql_fetch_array($res)) {
	          $city_name = $row['name'];
	          $outputData["city"] = $city_name;
	      }
	      else{
	        $outputData["msg"] = "Бесплатная доставка";
	      }
	    }
	    // if user came from google
	    if ($reff == 'google'){
	      $city_name = $geo->find_location_by_ip($ip);
	      $outputData["city"] = $city_name;
	      $city = mb_strtolower($city_name);
	      $image = $geo->get_img($city);
	      $outputData["img"] = $image; 
	      
	    // if user came directly or from other website
	    if ($reff == 'other'){
	      $city_name = $geo->find_location_by_ip($ip);
	      $outputData["city"] = $city_name;
	      $city = mb_strtolower($city_name);
	      $image = $geo->get_img($city);
	      $outputData["img"] = $image; 
	    }          
	  }
	}
?>