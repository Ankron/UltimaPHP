<?php

/**
 * Ultima PHP - OpenSource Ultima Online Server written in PHP
 * Version: 0.1 - Pre Alpha
 */
class UltimaPHP {
    /* Server Status */

    const STATUS_START = 1;
    const STATUS_STOP = 2;
    const STATUS_FATAL = 4;
    const STATUS_FILE_LOADING = 8;
    const STATUS_FILE_LOAD_FAIL = 16;
    const STATUS_FILE_LOADED = 32;
    const STATUS_DATABASE_CONNECTING = 64;
    const STATUS_DATABASE_CONNECTED = 128;
    const STATUS_DATABASE_CONNECTION_FAILED = 256;
    const STATUS_UNHANDLED = 512;
    const STATUS_UNKNOWN = 1024;
    const STATUS_LISTENING = 2048;
    const STATUS_RUNNING = 4096;

    /* Server Log Types */
    const LOG_NORMAL = "NORMAL";
    const LOG_WARNING = "WARNING";
    const LOG_DANGER = "DANGER";
    const LOG_ERROR = "ERROR";

    /* Server bitwise masks */
    const BITMASK_UNUSED = 0xFFFFFFFF;
    const BITMASK_RESOURCE = 0x80000000;
    const BITMASK_ITEM = 0x40000000;
    const BITMASK_EQUIPPED = 0x20000000;
    const BITMASK_CONTAINED = 0x10000000;
    const BITMASK_DISCONNECT = 0x30000000;
    const BITMASK_INDEX_MASK = 0x0FFFFFFF;
    const BITMASK_INDEX_FREE = 0x01000000;

    /* Server Variables */

    static $status = self::STATUS_UNKNOWN;
    static $start_time;
    static $basedir;
    static $servers = array();

    /* Server Clients Sockets Variables */
    static $socketServer;
    static $socketClients = array();
    static $socketEvents = array();
	static $conf=array();
    /* Server Database Connection Variables */
    static $db;
	
	
    /* Shard Variables */
    static $starting_locations = array();
    static $clients = 0;
    static $items = 0;
    static $npcs = 0;
    static $commands = array(
        'add' => array(
            'minPlevel' => 4
        )
    );

    function __construct($dir) {
        self::$basedir = $dir . "/";
    }
	
	public static function localization($file, $lang, $id){
		global $server;
		unset($string);
		/*
		Command Format 
		
		class::localization(<file>, <lang>, <id>, [array(<argument 1>, <argument 2>, <argument 3>, ...)])
		
		Command Arguments:
		
		$file is the base file name the localization is in.
		$lang is the three character abbreviation of the language.
		$id is the id of the localization
		The fourth unstated variable is called "Arguments" this 
		should be an array of arguments, if it is not, it will 
		automatically be converted to an array.
		
		localization Format:
		
		Ignore [SOF] and [EOF], these will never be stated in the file. 
		These just signify the start and end points of a file for instruction purposes.
		"ln x | " signify a line of text in a file.
		
		[SOF]
		ln 1 | The %0% %1% fox %2% over the log.
		ln 2 | I ate a %0% for %1%.
		[EOF]*/
		
		if (isset($server->localization[$file.".".$lang])==FALSE){ //load a file and put it in the localization array
			if (file_exists($file.".".$lang)==FALSE){
				if ($file==="core/localization/log"){
					Die("UltimaPHP Fatal Error #:0000 Cannot provide translation from a non-existent system file named \"".$file.".".$lang."\"\n");
				}else{
					UltimaPHP::log(0, $file.".".$lang);
				}
			}else{
				$server->localization[$file.".".$lang]=file_get_contents($file.".".$lang);//Load file and break into lines
				$server->localization[$file.".".$lang]=explode("\n",$server->localization[$file.".".$lang]);
			}
		}
		$arguments=func_get_arg(3);


		if (!isset($string)){
			if (isset($server->localization[$file.".".$lang][$id])){
				$string=$server->localization[$file.".".$lang][$id];

				foreach ($arguments as $k => $v){ //$k = key, $v = value

					$string=str_replace("%".$k."%", $v, $string);
					
				}
				return $string;
			
			}elseif ($file==="core/Localization/Log"){
				$string="UltimaPHP Fatal Error #:0000 Cannot provide translation from a non-existent system error with id #".$id."\" in localization file named \"".$file.".".$lang."\"\n";
				die($string);
			}else{
				UltimaPHP::log(1, $file.".".$lang);
			}
		}else{
			return $string;
		}
	}
    public function start() {
        self::setStatus(self::STATUS_START);

        // Load the ini configuration file
        self::loadIni();

        // Load the core files
        foreach (glob(self::$basedir . "core/*.core.php") as $file) {
            self::setStatus(self::STATUS_FILE_LOADING, array(
                "core/" . basename($file),
            ));

            if (!require_once ($file)) {
                self::setStatus(self::STATUS_FILE_LOAD_FAIL);
                self::stop();
            }

            $name = basename($file, ".core.php");
            $className = ucfirst($name);
            $$name = new $className();

            self::setStatus(self::STATUS_FILE_LOADED);
        }

        // Load included classes
        foreach (glob(self::$basedir . "core/*.class.php") as $file) {
            self::setStatus(self::STATUS_FILE_LOADING, array(
                "class/" . basename($file),
            ));

            if (!require_once ($file)) {
                self::setStatus(self::STATUS_FILE_LOAD_FAIL);
                self::stop();
            }
            self::setStatus(self::STATUS_FILE_LOADED);
        }

        // Load items
        foreach (self::$conf['scripts']['load'] as $folder) {
            foreach (glob(self::$basedir . $folder . "*.php") as $file) {
                self::setStatus(self::STATUS_FILE_LOADING, array(
                    $folder . basename($file),
                ));

                if (!require_once ($file)) {
                    self::setStatus(self::STATUS_FILE_LOAD_FAIL);
                    self::stop();
                }
                self::setStatus(self::STATUS_FILE_LOADED);
            }
        }

        self::setStatus(self::STATUS_DATABASE_CONNECTING);
        try {
            $dnsString = self::$conf['database']['type'] . ":host=" . self::$conf['database']['host'] . ";dbname=" . self::$conf['database']['schema'] . ";charset=utf8";
            self::$db = new PDO($dnsString, self::$conf['database']['user'], self::$conf['database']['password']);
            self::setStatus(self::STATUS_DATABASE_CONNECTED);
        } catch (PDOException $e) {
            self::setStatus(self::STATUS_DATABASE_CONNECTION_FAILED, array(
                "\n" . $e->getMessage(),
            ));
        }

        self::updateStartingLocations();

        self::setStatus(self::STATUS_RUNNING, array(
            self::$conf['server']['ip'],
            self::$conf['server']['port'],
        ));

        while (self::STATUS_FATAL != self::$status && self::STATUS_STOP != self::$status) {
            Sockets::monitor();
            Sockets::runEvents();
        }
    }

    public static function stop() {
        self::setStatus(self::STATUS_STOP);
        exit();
    }

    private static function loadIni() {
        self::setStatus(self::STATUS_FILE_LOADING, array(
            "ultimaphp.ini",
        ));
        if (!is_file(self::$basedir . "ultimaphp.ini")) {
            self::setStatus(self::STATUS_FILE_LOAD_FAIL);
            self::stop();
        }

        self::$conf = array_change_key_case(parse_ini_file(self::$basedir . "ultimaphp.ini", true), CASE_LOWER);
		date_default_timezone_set(self::$conf['server']['timezone']);
        // Ini validations
        if (!isset(self::$conf['server'])) {
            $iniMessage = "No [server] configuration section";
        } elseif (!isset(self::$conf['database'])) {
            $iniMessage = "No [databe] configuration section";
        } elseif (!isset(self::$conf['accounts'])) {
            $iniMessage = "No [accounts] configuration section";
        } elseif (!isset(self::$conf['logs'])) {
            $iniMessage = "No [logs] configuration section";
        } elseif (!isset(self::$conf['server']['name'])) {
            $iniMessage = "Server name not defined";
        } elseif (!isset(self::$conf['server']['ip'])) {
            $iniMessage = "Server ip not defined";
        } elseif (!isset(self::$conf['server']['port'])) {
            $iniMessage = "Server port not defined";
        } elseif (!isset(self::$conf['server']['timezone'])) {
            $iniMessage = "Server timezone not defined";
            $iniNote = "More information at: https://en.wikipedia.org/wiki/List_of_tz_database_time_zones";
        } elseif (!isset(self::$conf['server']['lang'])) {
            $iniMessage = "Server language not defined";
        } elseif (!isset(self::$conf['server']['save_time'])) {
            $iniMessage = "Server save time not defined";
        } elseif (!isset(self::$conf['server']['client'])) {
            $iniMessage = "Server client not defined";
        } elseif (!isset(self::$conf['database']['type'])) {
            $iniMessage = "Server database type not defined";
        } elseif (!isset(self::$conf['database']['host'])) {
            $iniMessage = "Server database host not defined";
        } elseif (!isset(self::$conf['database']['user'])) {
            $iniMessage = "Server database user not defined";
        } elseif (!isset(self::$conf['database']['password'])) {
            $iniMessage = "Server database password not defined";
        } elseif (!isset(self::$conf['database']['schema'])) {
            $iniMessage = "Server database schema not defined";
        } elseif (!isset(self::$conf['accounts']['auto_create'])) {
            $iniMessage = "Server accounts auto create not defined";
        } elseif (!isset(self::$conf['accounts']['password_crypt'])) {
            $iniMessage = "Server accounts password encrypt not defined";
        } elseif (!isset(self::$conf['accounts']['max_chars'])) {
            $iniMessage = "Server accounts maximum characters per account not defined";
        } elseif (!isset(self::$conf['accounts']['char_delete_time'])) {
            $iniMessage = "Server accounts character deletion minimum time not defined";
        } elseif (!isset(self::$conf['accounts']['login_tries'])) {
            $iniMessage = "Server accounts login tries not defined";
        } elseif (!isset(self::$conf['accounts']['login_tries_block_time'])) {
            $iniMessage = "Server accounts login tries block time not defined";
        } elseif (!isset(self::$conf['accounts']['allow_crypt'])) {
            $iniMessage = "Server accounts allow crypt not defined";
        } elseif (!isset(self::$conf['accounts']['allow_nocrypt'])) {
            $iniMessage = "Server accounts allow nocrypt not defined";
        } elseif (!isset(self::$conf['accounts']['ConnectingMaxIp'])) {
            $iniMessage = "Server accounts maximum connection per ip not defined";
        } elseif (!isset(self::$conf['logs']['debug'])) {
            $iniMessage = "Server logs debug not defined";
        }

        $ip = explode(".", self::$conf['server']['ip']);

        if (count($ip) > 4 || count($ip) < 4) {
            $iniMessage = "The defined server IP is not valid";
        } else {
            foreach ($ip as $key => $value) {
                if (!(int) $value && "0" !== $value) {
                    $iniMessage = "The defined server IP is not valid";
                    break;
                } elseif ($value > 255) {
                    $iniMessage = "The defined server IP is not valid";
                    break;
                } elseif ($value < 0) {
                    $iniMessage = "The defined server IP is not valid";
                    break;
                }
            }
        }
        date_default_timezone_set(self::$conf['server']['timezone']);
        // Update the variable as array
        $clientVersion = explode(".", self::$conf['server']['client']);
        self::$conf['server']['client'] = array(
            'major' => $clientVersion[0],
            'minor' => $clientVersion[1],
            'revision' => $clientVersion[2],
            'prototype' => $clientVersion[3],
        );

        // Update the debug variable
        self::$conf['logs']['debug'] = (bool) self::$conf['logs']['debug'];

        if (isset($iniMessage)) {
            self::log($iniMessage . " in ultimaphp.ini.", self::LOG_ERROR);
            if (isset($iniNote)) {
                self::log($iniNote, self::LOG_NORMAL);
            }
            self::stop();
        }

        self::$servers[] = array(
            'name' => UltimaPHP::$conf['server']['name'],
            'ip' => UltimaPHP::$conf['server']['ip'],
            'port' => UltimaPHP::$conf['server']['port'],
            'full' => (0 == UltimaPHP::$conf['server']['max_players'] ? 0 : ceil((UltimaPHP::$clients / UltimaPHP::$conf['server']['max_players']) * 100)),
            'timezone' => UltimaPHP::$conf['server']['timezone'],
        );

        self::setStatus(self::STATUS_FILE_LOADED);
    }

    public static function setStatus($status, $args = array()) {
        switch ($status) {
            case self::STATUS_START:
                $message = 17;
				$arguments=array();
                $type = self::LOG_NORMAL;
                break;

            case self::STATUS_STOP:
				$message = 18;
				$arguments=array();
                $type = self::LOG_NORMAL;
                break;

            case self::STATUS_FATAL:
				$message = 27;
				$arguments=array();
                $type = self::LOG_DANGER;
                $shutdown = true;
                break;

            case self::STATUS_FILE_LOADING:
                //$message = "Loading file: ";
				$message = 19;
				$arguments=array($args[0]);
                $type = self::LOG_NORMAL;
                break;

            case self::STATUS_FILE_LOAD_FAIL:
                $message = 20;
				if (isset($args[0])){
					$arguments=array($args[0]);
				}else{
					$arguments=array();
				}
                $type = self::LOG_DANGER;
                break;

            case self::STATUS_FILE_LOADED:
                $message = 22;
				$arguments=array();
                $type = self::LOG_NORMAL;
                break;

            case self::STATUS_DATABASE_CONNECTING:
                $message = 21;
				$arguments=array();
                $type = SELF::LOG_NORMAL;
                break;

            case self::STATUS_DATABASE_CONNECTED:
                $message = 23;
				$arguments=array();
                $type = SELF::LOG_NORMAL;
                break;

            case self::STATUS_DATABASE_CONNECTION_FAILED:
                $message = 24;
				$arguments=array($args[0]);
                $type = SELF::LOG_DANGER;
                $shutdown = true;
                break;

            case self::STATUS_UNKNOWN:
                $message = 25;
				$arguments=array();
                $type = self::LOG_WARNING;
                break;

            case self::STATUS_LISTENING:
                $message = 26;
				$arguments=array($args[0], $args[1]);
                $type = self::LOG_NORMAL;
                break;

            case self::STATUS_RUNNING:
                $message = 27;
                $type = self::LOG_NORMAL;
                break;

            default:
                $message = 32;
				$arguments=array();
                $type = self::LOG_DANGER;
                $status = self::$status;
                break;
        }
        self::$status = $status;
		if (is_null($message)==FALSE)
			self::log($message, $arguments, $type);

        if (isset($shutdown)) {
            self::stop();
        }
    }

    public static function log($message, $arguments) {
		global $server;
        if (isset($message)!==FALSE){
			if (is_array($arguments)==FALSE){
				$arguments=array($arguments);
			}
			if (isset(UltimaPHP::$conf)==false)
				$lang="enu";
			elseif (isset(UltimaPHP::$conf['server'])==false)
				$lang="enu";
			elseif (isset(UltimaPHP::$conf['server']['lang'])==false)
				$lang="enu";
			else
				$lang=UltimaPHP::$conf['server']['lang'];

			array_unshift($arguments, date("H:i:s", time()));

            $string=UltimaPHP::localization("core/Localization/Log", $lang, $message, $arguments)."\n";
			echo $string;
			file_put_contents("logs/".date("Y.m.d").".log" , $string, FILE_APPEND );
        }
    }

    public static function updateStartingLocations() {
        $query = "SELECT
                        a.name,
                        a.area,
                        a.position,
                        a.clioc
                    FROM
                        starting_locations a";

        $sth = UltimaPHP::$db->prepare($query);
        $sth->execute();
        $result = $sth->fetchAll(PDO::FETCH_ASSOC);

        foreach ($result as $key => $location) {
            $position = explode(",", $location['position']);
            self::$starting_locations[] = array(
                'name' => $location['name'],
                'area' => $location['area'],
                'position' => array(
                    "x" => $position[0],
                    "y" => $position[1],
                    "z" => $position[2],
                    'map' => $position[3],
                ),
                'clioc' => $location['clioc'],
            );
        }

        return true;
    }

}

?>