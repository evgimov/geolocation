<?php
include_once('lib/phpmorphy/src/common.php');

/**
 * Geolocation service
 * =====================
 *
 * Geolocation service is a lightweight PHP class to get ip address, validate ip address, find country and city by ip address, 
 * get the image with coat of arms of the city
 *
 * @author      Evghenii Ghimazitdinov <e.ghimazitdinov@gmail.com> *
 *
 * @license     Code and contributions have 'MIT License'
 *              More details: https://github.com/evgimov/geolocation/blob/master/LICENSE
 *
 * @link        GitHub Repo:  https://github.com/evgimov/geolocation
 *
 * @version     1.0
 */


class Geo {
  /**
   * Geolocation instance
   */
  private static $instance;
  /**
   * Country name
   * @var string
   */
  static $country; 
    /**
   * City name
   * @var string
   */
  static $city;
  /**
   * Path to the image with coat of arms of the city
   * @var string
   */  
  static $image; 

  /**
   * Get GeoLocation instance. Singleton pattern
   *
   * @return object
   */
  public static function getInstance()
  {
    if (!(self::$instance instanceof self)) {
        self::$instance = new self();
    }
    return self::$instance;
  }
  /**
   * Constructor of the Geo class
   */
  private function __construct(){
    $db_host = "localhost";
    $db_user = "root";
    $db_password = "123";
    $db_database = "geo";
    $link = mysql_connect ($db_host, $db_user, $db_password);
    if ($link && mysql_select_db ($db_database)) {
        mysql_query ("set_client='utf8'");
        mysql_query ("set character_set_results='utf8'");
        mysql_query ("set collation_connection='utf8_general_ci'");
        mysql_query("SET NAMES 'utf8'");
    } else {
        die ("db error");
    }
  }

  /**
    * Checks if provided ip address is valid
    * @param string $ip - provided ip address
    * @return bool 
  */
  public function is_valid_ip($ip=null){
    // if ip matches regular expression
    if(preg_match("#^([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})$#", $ip))
        return true;
    // if doesn't matches 
    return false; 
  }

  /**
    * Get ip address using PHP SuperGlobal variable Server
    * @return string|false
  */
  public function get_ip(){
    $ipa=array();
    if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))$ipa[] = trim(strtok($_SERVER['HTTP_X_FORWARDED_FOR'], ','));
    if(isset($_SERVER['HTTP_CLIENT_IP']))$ipa[] = $_SERVER['HTTP_CLIENT_IP'];
    if(isset($_SERVER['REMOTE_ADDR']))$ipa[] = $_SERVER['REMOTE_ADDR'];
    if(isset($_SERVER['HTTP_X_REAL_IP']))$ipa[] = $_SERVER['HTTP_X_REAL_IP'];
    // check ip addresses starting from the most foreground
    foreach($ipa as $ips) // if ip is valid exit the loop, return this ip
      if(self::is_valid_ip($ips)) return $ips;
    return false;
  }

  /**
    * Searches country and city based on ip address
    * @param $ip - ip address of location
    * @return array 
  */
  public function find_location_by_ip($ip)
  {
    // transform ip address into number
    $int = sprintf("%u", ip2long($ip));

    $country_name = "";
    $country_id = 0;

    $city_name = "";
    $city_id = 0;

    // looking among russian and ukranian cities
    $sql = "select * from (select * from net_ru where begin_ip<=$int order by begin_ip desc limit 1) as t where end_ip>=$int";
    $result = mysql_query($sql);
    if ($row = mysql_fetch_array($result)) {
        $city_id = $row['city_id'];
        $sql = "select * from net_city where id='$city_id'";
        $result = mysql_query($sql);
        if ($row = mysql_fetch_array($result)) {
            $city_name = $row['name_ru'];
            $country_id = $row['country_id'];
        } else {
            $city_id = 0;
        }
    }

    // looking among cities and countries of the world
    if (!$city_id) {
        // looking among european countries
        $sql = "select * from (select * from net_euro where begin_ip<=$int order by begin_ip desc limit 1) as t where end_ip>=$int";
        $result = mysql_query($sql);
        if (mysql_num_rows($result) == 0) {
            // looking among the countries in the world
            $sql = "select * from (select * from net_country_ip where begin_ip<=$int order by begin_ip desc limit 1) as t where end_ip>=$int";
            $result = mysql_query($sql);
        }
        if ($row = mysql_fetch_array($result)) {
            $country_id = $row['country_id'];
        }

        // looking among the cities
        $city_name = "";
        $city_id = 0;
        // looking the city in the global database
        $sql = "select * from (select * from net_city_ip where begin_ip<=$int order by begin_ip desc limit 1) as t where end_ip>=$int";
        $result = mysql_query($sql);
        if ($row = mysql_fetch_array($result)) {
            $city_id = $row['city_id'];
            $sql = "select * from net_city where id='$city_id'";
            $result = mysql_query($sql);
            if ($row = mysql_fetch_array($result)) {
                $city_name = $row['name_ru'];
                $country_id = $row['country_id']; 
            }
        }
    }

    // get the results of our search
    if ($country_id == 0) {
        $country_name = "";
    }
    else {
    // get the name of the country by id
      $sql = "select * from net_country where id='$country_id'";
      $result = mysql_query($sql);
      if ($row = mysql_fetch_array($result)) {
          $country_name = $row['name_ru'];
      }
      $arr[] = $country_name;
    }

    if ($city_id == 0) {
      $city_name = "";
    } else {
        $arr[] = $city_name;
    }
    return $arr;
  }

  /**
    * Find the country by ip of gsm provider
    * @param string $ip - id address
    * @return string 
  */
  public function find_country_providers($ip)
  {

    // transform from ip address to number
    $int = sprintf("%u", ip2long($ip));

    $country_name = "";
    $country_id = 0;

    $city_name = "";
    $city_id = 0;

    // looking among russian and ukranian cities
    $sql = "select * from (select * from ips_ip where begin_ip<=$int order by begin_ip desc limit 1) as t where end_ip>=$int";
    $result = mysql_query($sql);
    if ($row = mysql_fetch_array($result)) {
        $operator_id = $row['operator_id'];
        $sql = "select * from ips_country where operator_id='$operator_id'";
        $result = mysql_query($sql);
        if ($row = mysql_fetch_array($result)) {
            $country_name = $row['country_name'];
        } else {
            $country_name = "";
        }
    }
    return $country_name;
  }

  /**
    * Determine the refferer
    * @return string
  */
  public function define_refferer(){
    // determine the referer
    $reff = $_SERVER['HTTP_REFERER'];
    // determine from which search engine the transition occured
    $search = 'none';
    if(strpos($reff,"yandex")) $search = 'yandex';
    if(strpos($reff,"google")) $search = 'google';

    $server_name = $_SERVER["SERVER_NAME"];
    if(substr($_SERVER["SERVER_NAME"],0,4) == "www."){
      $server_name = substr($_SERVER["SERVER_NAME"], 4);
    }
    // transition occured from the other website
    if(strpos($reff,$server_name)) $search = 'other'; 
    return $search;
  }

  /**
    * Get all variants of word declination
    * @param string $words - words needed to decline
    * @return string 
  */
  public function get_morphy($words){
    if (isset($words)) {
      $words=iconv("UTF-8", "WINDOWS-1251", $words);
      $words=preg_split('//u', $words, -1, PREG_SPLIT_NO_EMPTY);

      // using the morphy library
      $opts = array(
          // PHPMORPHY_STORAGE_FILE - use the file
          // PHPMORPHY_STORAGE_SHM - upload the dictionary in shared memory(need the shmop extension)
          // PHPMORPHY_STORAGE_MEM - upload the dictionary in shared memory at each initialization of phpmorphy
          'storage' => PHPMORPHY_STORAGE_FILE,
          'predict_by_suffix' => true,
          'predict_by_db' => true,
          'graminfo_as_text' => true,
      );
      $morphy = new phpMorphy($morphy.'dicts', 'ru_RU', $opts);

      foreach($words as $i=>$word)
      $words[$i]=iconv('windows-1251', $morphy->getEncoding(), mb_strtoupper($word)); // convert from windows-1251 to utf-8

      $awords = $morphy->getAllForms($words);
      foreach($awords as $base_key => $base_value) {
        foreach($base_value as $key => $value) {
          $morfiedArr[] = $value;
        }
      }
      return $morfiedArr;
    }
  }

  /**
    * Get image by name
    * @param string $name - image name
    * @return string 
  */
  public function get_img($name){
    $images = scandir('./img/');
    foreach($images as $img) {
      if(stripos($img,'.gif')){
        $pos = strpos($img,'.gif');
        $str = substr($img, 0, $pos);
        if (strcmp($str,$name) == 0){
          $img = '<img src="img/'.$img.'" width="50" height="50" />';
          break;
       }
      }
      if(stripos($img,'.jpg')){
        $pos = strpos($img,'.jpg');
        $str = substr($img, 0, $pos);
        if (strcmp($str,$name) == 0){
          $img = '<img src="img/'.$img.'" width="50" height="50" />';
          break;
        }
      }
    }
    return $img;
  }
  /**
    * @Find the city with ipaddresslabs service
    * @param string $key - the key provided by service
    * @param string $ip - ip address of the city
    * @return  string|false
  */
  public function find_with_service($key,$ip){
    $url = "http://api.ipaddresslabs.com/iplocation/v1.8/locateip?key=".$key."&ip=".$ip."&format=XML";
    $data = simplexml_load_file($url);
    $city_name_eng = $data->geolocation_data->city;
    if (!$city_name_eng){
      return $city_name_eng;  
    } else return false;    
  }
  /**
    * @Get transltiteration of provided word or letter
    * @param string $word - letter or word
    * @return  string 
  */
  public function get_transliteration($word){
     $translit = array(
   
            'а' => 'a',   'б' => 'b',   'в' => 'v',
  
            'г' => 'g',   'д' => 'd',   'е' => 'e',
  
            'ё' => 'yo',   'ж' => 'zh',  'з' => 'z',
  
            'и' => 'i',   'й' => 'j',   'к' => 'k',
  
            'л' => 'l',   'м' => 'm',   'н' => 'n',
  
            'о' => 'o',   'п' => 'p',   'р' => 'r',
  
            'с' => 's',   'т' => 't',   'у' => 'u',
  
            'ф' => 'f',   'х' => 'x',   'ц' => 'c',
  
            'ч' => 'ch',  'ш' => 'sh',  'щ' => 'shh',
  
            'ь' => '\'',  'ы' => 'y',   'ъ' => '\'\'',
  
            'э' => 'e\'',   'ю' => 'yu',  'я' => 'ya',
          
  
            'А' => 'A',   'Б' => 'B',   'В' => 'V',
  
            'Г' => 'G',   'Д' => 'D',   'Е' => 'E',
  
            'Ё' => 'YO',   'Ж' => 'Zh',  'З' => 'Z',
  
            'И' => 'I',   'Й' => 'J',   'К' => 'K',
  
            'Л' => 'L',   'М' => 'M',   'Н' => 'N',
  
            'О' => 'O',   'П' => 'P',   'Р' => 'R',
  
            'С' => 'S',   'Т' => 'T',   'У' => 'U',
  
            'Ф' => 'F',   'Х' => 'X',   'Ц' => 'C',
  
            'Ч' => 'CH',  'Ш' => 'SH',  'Щ' => 'SHH',
  
            'Ь' => '\'',  'Ы' => 'Y\'',   'Ъ' => '\'\'',
  
            'Э' => 'E\'',   'Ю' => 'YU',  'Я' => 'YA',
  
        );
 
     $word = strtr($word, array_flip($translit)); // reverse transliteration 
     $str = iconv('UTF-8','windows-1251//TRANSLIT',$word); 
     return $str; 
  }
}
?>
