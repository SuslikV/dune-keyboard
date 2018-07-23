<?php


class HD
{
    public static function is_map($a){
        return is_array($a) &&
            array_diff_key($a, array_keys(array_keys($a)));
    }

    public static function has_attribute($obj, $n){
        $arr = (array) $obj;
        return isset($arr[$n]);
    }
    

    public static function get_map_element($map, $key){
        return isset($map[$key]) ? $map[$key] : null;
    }

    public static function starts_with($str, $pattern){
        return strpos($str, $pattern) === 0;
    }

    public static function format_timestamp($ts, $fmt = null){
        // NOTE: for some reason, explicit timezone is required for PHP
        // on Dune (no builtin timezone info?).

        if (is_null($fmt))
            $fmt = 'Y:m:d H:i:s';

        $dt = new DateTime('@' . $ts);
        return $dt->format($fmt);
    }

    public static function format_duration($msecs){
        $n = intval($msecs);

        if (strlen($msecs) <= 0 || $n <= 0)
            return "--:--";

        $n = $n / 1000;
        $hours = $n / 3600;
        $remainder = $n % 3600;
        $minutes = $remainder / 60;
        $seconds = $remainder % 60;

        if (intval($hours) > 0){
            return sprintf("%d:%02d:%02d", $hours, $minutes, $seconds);
        }
        else
        {
            return sprintf("%02d:%02d", $minutes, $seconds);
        }
    }

    public static function sec_format_duration($secs){
        $n = intval($secs);

        if (strlen($secs) <= 0 || $n <= 0)
            return "--:--";

        $hours = $n / 3600;
        $remainder = $n % 3600;
        $minutes = $remainder / 60;
        $seconds = $remainder % 60;

        if (intval($hours) > 0){
            return sprintf("%d:%02d:%02d", $hours, $minutes, $seconds);
        }
        else
        {
            return sprintf("%02d:%02d", $minutes, $seconds);
        }
    }

    public static function encode_user_data($a, $b = null){
        $media_url = null;
        $user_data = null;

        if (is_array($a) && is_null($b)){
            $media_url = '';
            $user_data = $a;
        }
        else
        {
            $media_url = $a;
            $user_data = $b;
        }

        if (!is_null($user_data))
            $media_url .= '||' . json_encode($user_data);

        return $media_url;
    }

    public static function decode_user_data($media_url_str, &$media_url, &$user_data){
        $idx = strpos($media_url_str, '||');

        if ($idx === false){
            $media_url = $media_url_str;
            $user_data = null;
            return;
        }

        $media_url = substr($media_url_str, 0, $idx);
        $user_data = json_decode(substr($media_url_str, $idx + 2));
    }

    public static function create_regular_folder_range($items,
        $from_ndx = 0, $total = -1, $more_items_available = false){
        if ($total === -1)
            $total = $from_ndx + count($items);

        if ($from_ndx >= $total){
            $from_ndx = $total;
            $items = array();
        }
        else if ($from_ndx + count($items) > $total){
            array_splice($items, $total - $from_ndx);
        }

        return array
        (
            PluginRegularFolderRange::total => intval($total),
            PluginRegularFolderRange::more_items_available => $more_items_available,
            PluginRegularFolderRange::from_ndx => intval($from_ndx),
            PluginRegularFolderRange::count => count($items),
            PluginRegularFolderRange::items => $items
        );
    }

    public static function http_get_document($url, $opts = null){
		
		$ch = curl_init();
		$p = '';
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 	0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 	0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT,    40);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION,    1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,    true);
        curl_setopt($ch, CURLOPT_TIMEOUT,           40);
        curl_setopt($ch, CURLOPT_USERAGENT,         "Mozilla/5.0 (Windows NT 10.0; WOW64; rv:53.0) Gecko/20100101 Firefox/53.0");
		curl_setopt($ch, CURLOPT_ENCODING,          1);
        curl_setopt($ch, CURLOPT_URL,               $url);

        if (isset($opts)){
            foreach ($opts as $k => $v)
                curl_setopt($ch, $k, $v);
        }

        hd_print($p."HTTP fetching '$url'...");

        $content = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if($content === false){
            $err_msg = "HTTP error: $http_code (" . curl_error($ch) . ')';
            hd_print($err_msg);
        }

        if ($http_code != 200){
            $err_msg = "HTTP request failed ($http_code)";
            hd_print($err_msg);
        }

        hd_print("HTTP OK ($http_code)");

        curl_close($ch);

        return $content;
    }

    public static function http_post_document($url, $post_data, $opts=null){
        $arr [CURLOPT_POST] = true;
        $arr [CURLOPT_POSTFIELDS] = $post_data;
		if (isset($opts)){
            foreach ($opts as $k => $v)
               $arr[$k] = $v;
        }
		return self::http_get_document($url, $arr);
    }
	
    public static function parse_xml_document($doc){
        $xml = simplexml_load_string($doc);

        if ($xml === false){
            hd_print("Error: can not parse XML document.");
            hd_print("XML-text: $doc.");
            hd_print('Illegal XML document');
        }

        return $xml;
    }

    public static function make_json_rpc_request($op_name, $params){
        static $request_id = 0;

        $request = array
        (
            'jsonrpc' => '2.0',
            'id' => ++$request_id,
            'method' => $op_name,
            'params' => $params
        );

        return $request;
    }
    
    public static function get_ip_address(){
        static $ip_address = null;
        
        if (is_null($ip_address)){
            preg_match_all('/inet'.(false ? '6?' : '').' addr: ?([^ ]+)/', `ifconfig`, $ips);
			if ($ips[1][0]!= '127.0.0.1')
				$ip_address = $ips[1][0];
			else if ($ips[1][1]!= '127.0.0.1')
				$ip_address = $ips[1][1];
        }
        
        return $ip_address;
    }
    
    public static function get_mac_addr($clr=null){
        static $mac_addr = null;

        if (is_null($mac_addr)){
            $mac_addr = shell_exec(
                'ifconfig  eth0 | head -1 | sed "s/^.*HWaddr //"');
            $mac_addr = trim($mac_addr);
        }
		if ($clr == true)
			return str_replace(":", "",$mac_addr);
        return $mac_addr;
    }

    // TODO: localization
    private static $MONTHS = array(
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December',
    );

    public static function format_date_time_date($tm){
        $lt = localtime($tm);
        $mon = self::$MONTHS[$lt[4]];
        return sprintf("%02d %s %04d", $lt[3], $mon, $lt[5] + 1900);
    }

    public static function format_date_time_time($tm, $with_sec = false){
        $format = '%H:%M';
        if ($with_sec)
            $format .= ':%S';
        return strftime($format, $tm);
    }
	public static function date_time(){
		$ini_array = parse_ini_file("/config/settings.properties");
        return ($ini_array['time_zone'] * -3600);
    }
    public static function print_backtrace(){
        hd_print('Back trace:');
        foreach (debug_backtrace() as $f){
            hd_print(
                '  - ' . $f['function'] . 
                ' at ' . $f['file'] . ':' . $f['line']);
        }
    }
    public static function get_filesize_str($size){
		if( $size < 1024 ){
			$size_num = $size;
			$size_suf = "B";
		}
		else if( $size < 1048576 ){
			$size_num = round($size / 1024, 2);
			$size_suf = "KiB";
		}
		else if( $size < 1073741824 ){
			$size_num = round($size / 1048576, 2);
			$size_suf = "MiB";
		}
		else{
			$size_num = round($size / 1073741824, 2);
			$size_suf = "GiB";
		}
		return "$size_num $size_suf";
    }
	
	public static function get_items($path) {
		$items = array();
		if ($path=='data_dir_path')
			$link = DuneSystem::$properties['data_dir_path'] . '/'. $path;
		else
			$link = SmartConfig::get_data_path() . '/'. $path;
		if (file_exists($link))
			$items = unserialize(file_get_contents($link));
		return $items;
	}
	
	public static function save_items($path, $items){
		if ($path=='data_dir_path')
			$link = DuneSystem::$properties['data_dir_path'] . '/'. $path;
		else
			$link = SmartConfig::get_data_path() . '/'. $path;
		file_put_contents ($link, serialize($items));
	}
	
	public static function save_items_tmp($path, $items) {
		$data_path = DuneSystem::$properties['tmp_dir_path']. '/' .$path;
		$skey = serialize($items);
		$data = fopen($data_path,"w");
		if (!$data)
			return ActionFactory::show_title_dialog("Не могу записать items Что-то здесь не так!!!");
		fwrite($data, $skey);
		@fclose($data);
	}
	
	public static function get_items_tmp($path) {
		$item = '';
		$data_path = DuneSystem::$properties['tmp_dir_path']. '/' .$path;
		if (file_exists($data_path))
			$item = unserialize(file_get_contents($data_path));
		return $item;
	}
	
	public static function save_item($path, $item) {
		$link = SmartConfig::get_data_path() . '/'. $path;
		$data = fopen($link,"w");
		if (!$data)
			return ActionFactory::show_title_dialog("Не могу записать items Что-то здесь не так!!!");
		fwrite($data, $item);
		@fclose($data);
	}
	
	public static function get_item($path) {
		$item = '';
		$link = SmartConfig::get_data_path() . '/'. $path;
		if (file_exists($link))
			$item = file_get_contents($link);
		return $item;
	}
	
	public static function save_item_tmp($path, $item) {
		$link = DuneSystem::$properties['tmp_dir_path'] . '/'. $path;
		$data = fopen($link,"w");
		if (!$data)
			return ActionFactory::show_title_dialog("Не могу записать items Что-то здесь не так!!!");
		fwrite($data, $item);
		@fclose($data);
	}
	public static function get_item_tmp($path) {
		$item = '';
		$link = DuneSystem::$properties['tmp_dir_path'] . '/'. $path;
		if (file_exists($link))
			$item = file_get_contents($link);
		return $item;
	}
	public static function str_remove_tag($str){
		$search = array('@<script[^>]*?>.*?</script>@si',   // Strip out javascript 
                   '@<[\/\!]*?[^<>]*?>@si',                 // Strip out HTML tags 
                   '@<style[^>]*?>.*?</style>@siU'          // Strip style tags properly 
        );
		$str = preg_replace($search, '', $str); 
		$str = strip_tags($str);
		$str = str_replace("●","*",$str);
		$str = str_replace("/","-",$str);
		$str = str_replace("&nbsp;"," ",$str);
		$str = str_replace("&mdash;","",$str);
		$str = str_replace(array("&hellip;",'&thinsp;'),"",$str);
		$str = str_replace("&#8211;"," - ",$str);
		$str = str_replace("&ndash;"," - ",$str);
		$str = str_replace(array("&laquo;","&raquo;"),"'",$str);
		$str = preg_replace("|&#.*?;|", "", $str);
		$str = html_entity_decode($str);
		$str = str_replace(array ('"', '«', '»'),"'", $str);
		$str = preg_replace('/\s{2,}/', ' ', $str);
		if (Youtube::get_dash_platform_kind() == false)
			$str = Youtube::get_repl_mips_bug($str);
		
		$str = trim($str);
		return $str;
	}
	public static function get_codec_start_info()
	{	
		$check = shell_exec('ps | grep httpd | grep -c 81');
		if ( $check <= 1){
			shell_exec("httpd -h /codecpack/WWW -p 81");
			usleep(500000);
		}
	}
	
	public static function ver(){
		$ver = file_get_contents(DuneSystem::$properties['install_dir_path'].'/dune_plugin.xml');
			if (is_null($ver)) {
					hd_print('Can`t load dune_plugin.xml');
					return 'n/a';
				}
		$xml = HD::parse_xml_document($ver);
		$plugin_version = strval($xml->version);
		return $plugin_version;
	}
	
	public static function alphabet(){
        return array(
		'0' => '0', '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6', '7' => '7', '8' => '8', '9' => '9', 
		'А' => 'А', 'Б' => 'Б', 'В' => 'В', 'Г' => 'Г', 'Д' => 'Д', 'Е' => 'Е', 'Ё' => 'Ё', 'Ж' => 'Ж', 'З' => 'З', 'И' => 'И', 'Й' => 'Й', 'К' => 'К', 'Л' => 'Л', 'М' => 'М', 'Н' => 'Н', 'О' => 'О', 'П' => 'П', 'Р' => 'Р', 'С' => 'С', 'Т' => 'Т', 'У' => 'У', 'Ф' => 'Ф', 'Х' => 'Х', 'Ц' => 'Ц', 'Ч' => 'Ч', 'Ш' => 'Ш', 'Щ' => 'Щ', 'Ъ' => 'Ъ', 'Ы' => 'Ы', 'Ь' => 'Ь', 'Э' => 'Э', 'Ю' => 'Ю', 'Я' => 'Я', 
		'A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D', 'E' => 'E', 'F' => 'F', 'G' => 'G', 'H' => 'H', 'I' => 'I', 'J' => 'J', 'K' => 'K', 'L' => 'L', 'M' => 'M', 'N' => 'N', 'O' => 'O', 'P' => 'P', 'Q' => 'Q', 'R' => 'R', 'S' => 'S', 'T' => 'T', 'U' => 'U', 'V' => 'V', 'W' => 'W', 'X' => 'X', 'Y' => 'Y', 'Z' => 'Z',
		);
    }
	public static function get_id_key($str)
	{	
		$captions = mb_strtolower($str, 'UTF-8');
		$captions = str_replace(array(" ", "-", ".", "\r", "\n", "\"", " "), '', $captions);
		$id_key = md5($captions);
		return $id_key;
	}
	public static function get_vsetv_epg($channel_id, $day_start_ts)
	{	
		$epg = array();
		$epg_date = date("Y-m-d", $day_start_ts);
		if (file_exists(DuneSystem::$properties['tmp_dir_path']."/channel_".$channel_id."_".$day_start_ts))
			$epg = unserialize(file_get_contents(DuneSystem::$properties['tmp_dir_path']."/channel_".$channel_id."_".$day_start_ts));
		else {
			try {
				$doc = iconv('WINDOWS-1251', 'UTF-8', self::http_get_document("http://www.vsetv.com/schedule_channel_".$channel_id."_day_".$epg_date."_nsc_1.html"));
			}
			catch (Exception $e) {
				hd_print("Can't fetch EPG ID:$id DATE:$epg_date");
				return array();
			}
			$patterns = array("/<div class=\"desc\">/", "/<div class=\"onair\">/", "/<div class=\"pasttime\">/", "/<div class=\"time\">/", "/<br><br>/", "/<br>/", "/&nbsp;/");
			$replace = array("|", "\n", "\n", "\n", ". ", ". ", "");
			$doc = strip_tags(preg_replace($patterns, $replace, $doc));
			preg_match_all("/([0-2][0-9]:[0-5][0-9])([^\n]+)\n/", $doc, $matches);
			$last_time = 0;
			foreach ($matches[1] as $key => $time) {
				$str = preg_split("/\|/", $matches[2][$key], 2);
				$name = $str[0];
				$desc = array_key_exists(1, $str) ? $str[1] : "";
				$u_time = strtotime("$epg_date $time EEST");
				$last_time = ($u_time < $last_time) ? $u_time + 86400 : $u_time;
				$epg[$last_time]["name"] = $name;
				$epg[$last_time]["desc"] = $desc;
			}
		file_put_contents(DuneSystem::$properties['tmp_dir_path']."/channel_".$channel_id."_".$day_start_ts, serialize($epg));
		}
		ksort($epg, SORT_NUMERIC);
		return $epg;
	}
	public static function get_kostil($str)
	{	
		$xml=new DomDocument();
		if (preg_match_all('|<channel>(.*?)</channel>|ms', $str, $matches)){
			foreach ($matches[1] as $k => $v){
				$v = str_replace(array ('<![CDATA[', ']]>'),'',$v);
				if (preg_match('|<title>(.*?)</title>|ms', $v, $match)){
					if ($match[1]!='')
						$xml->channel->$k->title = $match[1];
					else
						$xml->channel->$k->title = 'empty';
				}
				if (preg_match('|<description>(.*?)</description>|ms', $v, $match)){
					if ($match[1]!='')
						$xml->channel->$k->description = $match[1];
					else
						$xml->channel->$k->description = 'empty';
				}
				if (preg_match('|<logo_30x30>(.*?)</logo_30x30>|ms', $v, $match))
					if ($match[1]!='')
						$xml->channel->$k->logo_30x30 = $match[1];
				if (preg_match('|<playlist_url>(.*?)</playlist_url>|ms', $v, $match))
					if ($match[1]!='')
						$xml->channel->$k->playlist_url = $match[1];
					else
						$xml->channel->$k->playlist_url = 'empty';
				else
					$xml->channel->$k->playlist_url = 'empty';
				if (preg_match('|<parser>(.*?)</parser>|ms', $v, $match))
					if ($match[1]!='')
						$xml->channel->$k->parser = $match[1];
			}
		}
		return $xml;
	}
	public static function ucfirst_utf8($string){ 
		$char=mb_strtoupper(substr($string,0,2),"utf-8");
		$string[0]=$char[0];
		$string[1]=$char[1];
		return $string; 
	}
	public static function baseitem($string){
		if (!preg_match("|iframe|",$string))
			$string = str_replace(array ('360p_','480p_','720p_','1080p_'),"",basename($string)); 
		return $string;
	}
	public static function get_aria2c_dload_info(){ 
			$q = array();
			$preferred_width = 1400;
			$nmr = $size = $speed = $downloaded = $prsnt = $eta =
			$dfile = $qq = $d_complete = $status = false;
			$arr['dfile'] = '';
			if (file_exists(DuneSystem::$properties['tmp_dir_path']. '/aria.log')){
				$log = file_get_contents (DuneSystem::$properties['tmp_dir_path']. '/aria.log');
				if (preg_match('|================================================================================|', $log)){
					$tmp = explode ("================================================================================", $log);
					$log = array_pop($tmp);
					$fp = fopen(DuneSystem::$properties['tmp_dir_path']. '/aria.log', 'w');
					fwrite($fp, $log);
					fclose($fp);
				}
				$q = file(DuneSystem::$properties['tmp_dir_path']. '/aria.log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
				if (isset($q[0])){
					foreach ($q as $v){
						if (preg_match("|#(.*?)\s|", $v, $matches)){
							$nmr = $matches[1];
							if (preg_match("|SIZE:(.*?)\/(.*?)\((.*?)\)\s|", $v, $matches)){
								$arr['downloaded'] = $matches[1];
								$arr['size'] = $matches[2];
								$arr['prsnt'] = $matches[3];
							}
							if (preg_match("|\s(.*?)\/(.*?)\((.*?)\)\s|", $v, $matches)){
								$arr['downloaded'] = $matches[1];
								$arr['size'] = $matches[2];
								$arr['prsnt'] = $matches[3];
							}
							if (preg_match("|SPD:(.*?)\s|", $v, $matches))
								$arr['speed'] = $matches[1];
							if (preg_match("|ETA:(.*?)\]|", $v, $matches))
								$arr['eta']= $matches[1];
							if (preg_match("|DL:(.*?)\s|", $v, $matches))
								$arr['speed'] = $matches[1];
						}else if (preg_match("|Download complete: (.*)|", $v, $matches))
							$arr['d_complete'] = basename($matches[1])."\n" . $d_complete;
						else if (preg_match("|FILE: (.*)|", $v, $matches))
							$arr['dfile'] = basename($matches[1]);
						else if (preg_match("#(\d*?)\|(.*?)\|(.*?)\|(.*)#", $v, $matches)){
							$arr['nmr'] = $matches[1];
							$arr['status'] = trim($matches[2]);
							if ($arr['status']== 'stat')
								continue;
							$arr['speed'] = trim($matches[3]);
							$arr['name'] = basename($matches[4]);
							$qq .= "[$nmr][$status] ".$arr['name']. " => [$speed]\n";
						}else if (preg_match("|download completed|", $v)){
							$arr['qq2'] = "Закачки завершены";
							$arr['dfile'] = '';
						}
					}
				}
				else{
					$arr['qq2'] = "Закачка запущена";
					$arr['qq'] = "Старт...";
					$arr['status'] = 2;
					$arr['preferred_width'] = 400;
				}
			}else{
				$arr['qq2'] = "Закачки остановленны";
				$arr['qq'] = "Закачек нет";
				$arr['status'] = true;
				$arr['preferred_width'] = 400;
			}
		$arr['qq'] = $qq;
		return $arr;
	}
	
	public static function get_lock_items($lock = null){
        static $lock_items = false;
        if ($lock==true)
			$lock_items = $lock;
        return $lock_items;
    }
	
	public static function get_dload_app(){
        $dload_app = false;
		if (file_exists('/ltu/bin/aria2c'))
			$dload_app = '/ltu/bin/aria2c';
		if (file_exists('/opt/bin/aria2c'))
			$dload_app = '/opt/bin/aria2c';
        return $dload_app;
    }
	public static function get_upper($str){
        $first = mb_substr($str,0,1, 'UTF-8');
		$last = mb_substr($str,1);
		$first = mb_strtoupper($first, 'UTF-8');
		$last = mb_strtolower($last, 'UTF-8');
		$str = $first.$last;
        return $str;
    }
	public static function search_local_transmission(){
        if ((file_exists('/opt/bin/transmission-daemon'))||
		(file_exists('/ltu/bin/transmission-daemon'))||
		(file_exists('/tmp/www/plugins/Transmission/cgi-bin/service_api')))
			return true;
        return false;
    }
	public static function isDomainAvailible($domain, $timeout=null){
		if ($timeout==false)
			$timeout=10;
		$curlInit = curl_init($domain);
		curl_setopt($curlInit,CURLOPT_CONNECTTIMEOUT,$timeout);
		curl_setopt($curlInit,CURLOPT_HEADER,true);
		curl_setopt($curlInit,CURLOPT_NOBODY,true);
		curl_setopt($curlInit,CURLOPT_RETURNTRANSFER,true);
		$response = curl_exec($curlInit);
		curl_close($curlInit);
		if ($response) return true;
		return false;
	}
	
	public static function get_versions($t=null){
		static $versions = null;
		if (is_null($versions))
            $versions = parse_ini_file('/tmp/run/versions.txt');
		if ($t == true)
			return $versions[$t];
		if (preg_match('|r11|', $versions['firmware_version']))
			return true;
		else
			return false;
    }
	
	public static function known_languages(){
		//all known translations
		return array(
			'arabic' => 'العربية',
			'bulgarian' => 'Български',
			'chinese_simplified' => '简体中文',
			'chinese_traditional' => '繁體中文',
			'czech' => 'Čeština',
			'danish' => 'Dansk',
			'dutch' => 'Nederlands',
			'english' => 'English',
			'estonian' => 'Eesti',
			'french' => 'Français',
			'german' => 'Deutsch',
			'greek' => 'Ελληνικά',
			'hebrew' => 'עברית',
			'hungarian' => 'Magyar',
			'italian' => 'Italiano',
			'japanese' => '日本語',
			'korean' => '한국어',
			'polish' => 'Polski',
			'romanian' => 'Română',
			'russian' => 'Русский',
			'slovak' => 'Slovenčina',
			'spanish' => 'Español',
			'swedish' => 'Svenska',
			'turkish' => 'Türkçe',
			'ukrainian' => 'Українська',
			'vietnamese' => 'Tiếng Việt'
		);
	}
	
	//function to get list(array) of localization files of the plugin
	public static function get_localizations_list(){
		$localeList = array();

		//all known languages
		$lang = HD::known_languages();

		foreach(new DirectoryIterator(DuneSystem::$properties['install_dir_path']."/translations/") as $file)
			if ($file->isFile() && ($file->getExtension() === 'txt')) { //php 5.3.6 minimum
				$langFileName = $file->getFilename(); //path not included
				$pos = strpos($langFileName, "dune_language_");
				
				if ($pos !== false) {
					$langFileName = substr($langFileName, $pos + strlen("dune_language_"), -4); // -4 -> exclude extention
				}
				
				if (isset($lang[$langFileName]))
					$localeList[$langFileName] = $lang[$langFileName];
				else
					$localeList[$langFileName] = $localeList[$langFileName];
				
			};
		hd_print("Available plugin's translations:");
		hd_print(print_r($localeList, true));
		return $localeList;
	}

	//function to get array of translation's strings
	//	$filePath - full path to translations txt file
	//	$oldTr - array that contains english translation
	//  $debugKeys - boolean, if true - returns strings compatible with build-in localization aka "%tr%key" format
	public static function get_translations($filePath, &$oldTr, $debugKeys){
		$newTr = array(); //set temporary array
		$result = true;
		hd_print("Reading translations file: {$filePath}");
		if (file_exists($filePath)) {
			$handle = fopen($filePath, "r");
			if ($handle) {
				while (($buffer = fgets($handle)) !== false) {
					$trName = trim(strtok($buffer, "="));

					if ($debugKeys)
						$trValue = "%tr%".$trName;
					else
						$trValue = trim(strtok("\n"), " "); //only remove leading/trailing spaces

					$newTr[$trName] = $trValue; //fill array with translation strings: ["key"] => value";
				}
				if (!feof($handle)) {
					$result = false;
					hd_print("Get array of translations failed.");
				}
				fclose($handle);
			}
		}
		$oldTr = $newTr + $oldTr; //append any missing
		unset($newTr); //remove temporary array
		//hd_print(print_r($oldTr, true)); //debug info
		return $result;
	}
	
	//function to get current player locale setting
	public static function get_STB_locale(){
		//symbolic links, file_exists() wouldn't work here
		$propertiesFile = realpath("/config/settings.properties");
		if ($propertiesFile) {
			$handle = fopen($propertiesFile, "r");
			if ($handle) {
				while (($buffer = fgets($handle)) !== false) {
					$pos = strpos($buffer, "interface_language = "); //robust but fast comparison
					if ($pos !== false) {
						$result = trim(substr($buffer, $pos + strlen("interface_language = ")));
						hd_print("STB locale = ".print_r($result, true));
						fclose($handle);
						return $result;
					}
				}
				if (!feof($handle))
					hd_print("Get current player locale failed.");
				fclose($handle);
			}
		}
		hd_print("interface_language setting not found. Default STB locale = english");
		return "english"; //default and always available locale
	}
	
	//function to get default localization strings (english)
	public static function get_default_translations(&$arrayTr){
		HD::get_translations(DuneSystem::$properties['install_dir_path']."/translations/dune_language_english.txt", $arrayTr, false);
		return true;
	}
	
	//function to get native localization strings (%tr%key format)
	public static function get_native_translations(&$arrayTr){
		HD::get_translations(DuneSystem::$properties['install_dir_path']."/translations/dune_language_english.txt", $arrayTr, true);
		return true;
	}
}


?>
