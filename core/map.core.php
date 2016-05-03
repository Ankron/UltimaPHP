<?php

/**
 * Ultima PHP - OpenSource Ultima Online Server written in PHP
 * Version: 0.1 - Pre Alpha
 */
class Map {

    /**
     * Map loading variables
     */
    private static $maps = [];
    private static $mapSizes = [];
    private static $chunks = [];
    private static $chunkSize = 256; // Number in square
    private static $tileMatrix = [];

    public function __construct() {
		if (isset(UltimaPHP::$conf)==false)
			$lang="enu";
		elseif (isset(UltimaPHP::$conf['server'])==false)
			$lang="enu";
		elseif (isset(UltimaPHP::$conf['server']['lang'])==false)
			$lang="enu";
		else
			$lang=UltimaPHP::$conf['server']['lang'];
			
        $actualMap = 0;
        /**
         * Render the maps inside chunk arrays
         */
        while (isset(UltimaPHP::$conf["muls"]["map{$actualMap}"])) {
            $mapFile = UltimaPHP::$conf['muls']['location'] . "map{$actualMap}.mul";
            $mapSize = explode(",", UltimaPHP::$conf["muls"]["map{$actualMap}"]);

            UltimaPHP::setStatus(UltimaPHP::STATUS_FILE_LOADING, array(
                $mapFile,
            ));
			If (file_exists($mapFile)==FALSE){
			    UltimaPHP::setStatus(UltimaPHP::STATUS_FILE_LOAD_FAIL, array(UltimaPHP::localization("core/Localization/Log", $lang, 29, array($mapFile))));//UltimaPHP::Localization("core/Localization/Log", UltimaPHP::$conf['server']['lang'], array($mapFile)));
                UltimaPHP::stop();	
			}elseif (!is_file($mapFile)) {
                UltimaPHP::setStatus(UltimaPHP::STATUS_FILE_LOAD_FAIL, array(UltimaPHP::localization("core/Localization/Log", $lang, 28, array($mapFile))));//UltimaPHP::Localization("core/Localization/Log", UltimaPHP::$conf['server']['lang'], array($mapFile)));
                UltimaPHP::stop();
            }

            $chunks_x = ceil($mapSize[0] / self::$chunkSize);
            $chunks_y = ceil($mapSize[1] / self::$chunkSize);

            // Build the array that will store map chunks
            for ($xChunk = 0; $xChunk < $chunks_x; $xChunk++) {
                self::$chunks[$xChunk] = array();
                for ($yChunk = 0; $yChunk < $chunks_y; $yChunk++) {
                    self::$chunks[$xChunk][$yChunk] = array(
                        'objects' => array(),
                        'players' => array(),
                        'npcs' => array(),
                    );
                }
            }

            // Store information about the map muls and size
            self::$maps[$actualMap] = array(
                'mul' => null,
                'size' => array(
                    'x' => null,
                    'y' => null,
                ),
            );

            self::$mapSizes[$actualMap]['x'] = $mapSize[0];
            self::$mapSizes[$actualMap]['y'] = $mapSize[1];
            self::$maps[$actualMap]['mul'] = fopen($mapFile, "rb");
            self::$maps[$actualMap]['size']['x'] = (int) $mapSize[0] >> 3;
            self::$maps[$actualMap]['size']['y'] = (int) $mapSize[1] >> 3;

            for ($x = 0; $x < self::$maps[$actualMap]['size']['x']; ++$x) {
                self::$maps[$actualMap][$x] = array();
                for ($y = 0; $y < self::$maps[$actualMap]['size']['y']; ++$y) {
                    self::$maps[$actualMap][$x][$y] = array();

                    fseek(self::$maps[$actualMap]['mul'], ((($x * self::$maps[$actualMap]['size']['y']) + $y) * 196), SEEK_SET);
                    $header = hexdec(bin2hex(fread(self::$maps[$actualMap]['mul'], 4)));


                    for ($i = 0; $i < 64; ++$i) {
                        $tile = bin2hex(fread(self::$maps[$actualMap]['mul'], 2));
                        $z = hexdec(bin2hex(fread(self::$maps[$actualMap]['mul'], 1)));
                        if ((hexdec($tile) < 0) || ($tile >= 0x4000)) {
                            $tile = 0;
                        }
                        if ($z < -128) {
                            $z = -128;
                        }
                        if ($z > 127) {
                            $z = 127;
                        }
                        if ($tile > 0) {
                            //echo "$tile|$x,$y,$z,$actualMap\n";
                            // self::$maps[$actualMap][$x][$y][$z] = $tile;
                        }
                    }
                }
            }

            /* Send the server proccess and map the statics from actual map */
            // self::readStaticsFromPosition(0, 1429, 1695);
            // self::readStatics($actualMap);

            UltimaPHP::setStatus(UltimaPHP::STATUS_FILE_LOADED);
            $actualMap++;
        }

        $chunks_x = ceil($mapSize[0] / self::$chunkSize);
        $chunks_y = ceil($mapSize[1] / self::$chunkSize);

        // Build the array that will store map chunks
        for ($x = 0; $x < $chunks_x; $x++) {
            self::$chunks[$x] = array();
            for ($y = 0; $y < $chunks_y; $y++) {
                self::$chunks[$x][$y] = array(
                    'objects' => array(),
                    'players' => array(),
                    'npcs' => array(),
                );
            }
        }
    }

    /**
     * Read statics tiles from the .mul file
     */
    public static function readStatics($actualMap = false) {
        if ($actualMap === false) {
            return false;
        }

        $staticIdx = fopen(UltimaPHP::$conf['muls']['location'] . "statics{$actualMap}.mul", 'rb');
        $staticMul = fopen(UltimaPHP::$conf['muls']['location'] . "staidx{$actualMap}.mul", 'rb');
        $mapSize = explode(",", UltimaPHP::$conf["muls"]["map{$actualMap}"]);

        $block_x = (int) $mapSize[0] >> 3;
        $block_y = (int) $mapSize[1] >> 3;

        $binidx = $binmul = "";

        $byteStream;

        for ($x = 0; $x < $block_x; ++$x) {
            for ($y = 0; $y < $block_y; ++$y) {
                /* Set the position of file to the actual point of file */

                fseek($staticIdx, (($x * $block_y) + $y) * 12, SEEK_SET);
                $data = Functions::strToHex(fread($staticIdx, 12));

                // echo ((($x * $block_y) + $y) * 12) . ": " . $data . "\n";
                // continue;
                // $lookup = hexdec(fread($staticIdx, 4));
                // $length = hexdec(fread($staticIdx, 4));
                // $extra = hexdec(fread($staticIdx, 4));

                if ($lookup < 0 || $length <= 0) {
                    
                } else {
                    if (($lookup >= 0) && ($length > 0)) {
                        fseek($staticMul, $lookup, SEEK_SET);
                    }

                    $count = $length / 7;

                    $firstitem = true;

                    for ($i = 0; $i < $count; ++$i) {
                        $static_graphic = hexdec(fread($staticMul, 2)); // ReadUInt16();
                        $static_x = hexdec(fread($staticMul, 1)); // ReadByte();
                        $static_y = hexdec(fread($staticMul, 1)); // ReadByte();
                        $static_z = hexdec(fread($staticMul, 1)); // ReadSByte();
                        $static_hue = hexdec(fread($staticMul, 2)); // ReadInt16();
                        // echo "$static_graphic|$static_x|$static_y|$static_z|$static_hue\n";
                        // continue;
                        if ($static_graphic >= 0) {
                            if ($static_hue < 0) {
                                $static_hue = 0;
                            }

                            if ($firstitem) {
                                $binidx .= Functions::strToHex($lookup);
                                $firstitem = false;
                            }

                            if ($static_graphic > 0) {
                                //echo "$static_graphic|$static_x|$static_y|$static_z|$static_hue\n";
                            }

                            $binmul .= Functions::strToHex($static_graphic);
                            $binmul .= Functions::strToHex($static_x);
                            $binmul .= Functions::strToHex($static_y);
                            $binmul .= Functions::strToHex($static_z);
                            $binmul .= Functions::strToHex($static_hue);
                        } else {
                            $binmul .= Functions::strToHex("00");
                            $binmul .= Functions::strToHex("0");
                            $binmul .= Functions::strToHex("0");
                            $binmul .= Functions::strToHex("00");
                            $binmul .= Functions::strToHex("00");
                        }
                    }
                }
            }
        }
    }

    public static function readStaticsFromPosition($map, $pos_x, $pos_y) {
        $staticIdx = fopen(UltimaPHP::$conf['muls']['location'] . "statics{$map}.mul", 'rb');
        $staticMul = fopen(UltimaPHP::$conf['muls']['location'] . "staidx{$map}.mul", 'rb');

        $updateRange = array(
            'from' => array('x' => ($pos_x - 10), 'y' => ($pos_y - 10)),
            'to' => array('x' => ($pos_x + 10), 'y' => ($pos_y + 10)),
        );

        for ($x = $updateRange['from']['x']; $x < $updateRange['to']['x']; $x++) {
            for ($y = $updateRange['from']['y']; $y < $updateRange['to']['y']; $y++) {
                $index = (($x * self::$maps[$map]['size']['y']) + $y) * 12;
                fseek($staticIdx, $index, SEEK_SET);

                $lookup = (int) Functions::strToHex(fread($staticIdx, 4));
                $length = (int) Functions::strToHex(fread($staticIdx, 4));
                // $lookup = Functions::read_byte($staticIdx, 4);
                // $length = Functions::read_byte($staticIdx, 4);
                echo "$lookup|$length\n";
                if ($length > 0 && $lookup > 0) {
                    //echo "$lookup|$length\n";

                    // fseek($staticMul, $lookup, SEEK_SET);
                    // for ($i=0; $i < ($length/7); $i++) {
                    // 	$tileId = Functions::read_byte($staticMul, 2);
                    // 	$x = Functions::read_byte($staticMul, 1);
                    // 	$y = Functions::read_byte($staticMul, 1);
                    // 	$z = Functions::read_byte($staticMul, 1);
                    // 	$hue = Functions::read_byte($staticMul, 2);
                    // 	if ($tileId >= 0) {
                    // 		if ($hue < 0) {
                    // 			$hue = 0;
                    // 		}
                    // 		if ($tileId > 0) {
                    // 			echo "$tileId|$x|$y|$z|$hue\n";
                    // 		}
                    // 	}
                    // }
                }
            }
        }
        exit();

        $data = bin2hex(fread($staticMul, $length));
        echo "\n\n\nFIM\n\n\n";
        exit();
    }

    /**
     * Return the chunk number of desired map position
     */
    public static function getChunk($pos_x = null, $pos_y = null) {
        if ($pos_x === null || $pos_y === null || $pos_x <= 0 || $pos_y <= 0 || $pos_x > self::$mapSize_x || $pos_y > self::$mapSize_y) {
            return false;
        }

        return array(
            'x' => (int) ceil($pos_x / self::$chunkSize),
            'y' => (int) ceil($pos_y / self::$chunkSize),
        );
    }

    /**
     * Add the player to into the map and store information inside the right chunk
     */
    public static function addPlayerToMap(Player $player) {
        $chunk = self::getChunk($player->position['x'], $player->position['y']);
        self::$chunks[$chunk['x']][$chunk['y']]['players'][$player->client] = true;
        self::updateChunk($chunk);
        return true;
    }

    /**
     * 	Add the desired object into the map and store information inside the right chunk
     */
    public static function addObjectToMap(Object $object, $pos_x, $pos_y, $pos_z, $pos_m) {
        $object->pos_x = $pos_x;
        $object->pos_y = $pos_y;
        $object->pos_z = $pos_z;
        $object->location = "map";

        $chunk = self::getChunk($pos_x, $pos_y);
        self::$chunks[$chunk['x']][$chunk['y']]['objects'][] = $object;
        self::updateChunk($chunk);
        return true;
    }

    /**
     * Update the player position and other players around
     */
    public static function updatePlayerLocation($client, $oldPosition = null, $newPosition = null) {
        if ($oldPosition === NULL) {
            $tmp = UltimaPHP::$socketClients[$client]['account']->player;
            $oldPosition = $newPosition = $tmp->position;
            unset($tmp);
        }

        $oldChunk = self::getChunk($oldPosition['x'], $oldPosition['y']);
        $newChunk = self::getChunk($newPosition['x'], $newPosition['y']);

        /* Update the chunk of player, if changed */
        if ($oldChunk['x'] != $newChunk['x'] || $oldChunk['y'] != $newChunk['y']) {
            unset(self::$chunks[$oldChunk['x']][$oldChunk['y']]['players'][$client]);
            self::$chunks[$newChunk['x']][$newChunk['y']]['players'][$client] = true;
        }

        /* Send update packet information for players around player */
        $chunk = self::$chunks[$newChunk['x']][$newChunk['y']];
        $updateRange = array(
            'from' => array('x' => ($newPosition['x'] - 10), 'y' => ($newPosition['y'] - 10)),
            'to' => array('x' => ($newPosition['x'] + 10), 'y' => ($newPosition['y'] + 10)),
        );

        $actual_player = UltimaPHP::$socketClients[$client]['account']->player;

        foreach ($chunk['players'] as $client_id => $alive) {
            $player = UltimaPHP::$socketClients[$client_id]['account']->player;

            if ($actual_player->serial != $player->serial && $player->position['x'] >= $updateRange['from']['x'] && $player->position['x'] <= $updateRange['to']['x'] && $player->position['y'] >= $updateRange['from']['y'] && $player->position['y'] <= $updateRange['to']['y']) {
                if (!array_key_exists($client_id, $actual_player->mapRange['players'])) {
                    $actual_player->mapRange['players'][$client_id] = true;
                    $actual_player->drawChar(false, $client_id);
                }
                $actual_player->updatePlayer($client_id);

                if (!array_key_exists($actual_player->client, $player->mapRange['players'])) {
                    $player->mapRange['players'][$actual_player->client] = true;
                    $player->drawChar(false, $actual_player->client);
                }
                $player->updatePlayer($client);
            } else {
                if (isset($actual_player->mapRange['players'][$player->client])) {
                    unset($actual_player->mapRange['players'][$player->client]);
                }
                if (isset($player->mapRange['players'][$actual_player->client])) {
                    unset($player->mapRange['players'][$actual_player->client]);
                }
            }
        }
    }

    /**
     * Send desired packet to a range of players around the client
     */
    public static function sendPacketRange($packet = null, $client) {
        if ($packet === null) {
            return false;
        }

        $actual_player = UltimaPHP::$socketClients[$client]['account']->player;

        $chunkInfo = self::getChunk($actual_player->position['x'], $actual_player->position['y']);
        $chunk = self::$chunks[$chunkInfo['x']][$chunkInfo['y']];

        $updateRange = array(
            'from' => array('x' => ($actual_player->position['x'] - 10), 'y' => ($actual_player->position['y'] - 10)),
            'to' => array('x' => ($actual_player->position['x'] + 10), 'y' => ($actual_player->position['y'] + 10)),
        );

        foreach ($chunk['players'] as $client_id => $alive) {
            $player = UltimaPHP::$socketClients[$client_id]['account']->player;

            if ($actual_player->serial != $player->serial && $player->position['x'] >= $updateRange['from']['x'] && $player->position['x'] <= $updateRange['to']['x'] && $player->position['y'] >= $updateRange['from']['y'] && $player->position['y'] <= $updateRange['to']['y']) {
                Sockets::out($client_id, $packet, false);
            }
        }
    }

    /**
     * Update players with objects from desired chunk
     */
    public static function updateChunk($chunk) {
        $chunk = self::$chunks[$chunk['x']][$chunk['y']];

        /* Update items on map */
        foreach ($chunk['objects'] as $object) {
            $packet = "F3";
            $packet .= "0001";
            $packet .= "00";
            $packet .= $object->serial;
            $packet .= str_pad(dechex($object->graphic), 4, "0", STR_PAD_LEFT);
            $packet .= "00";
            $packet .= str_pad(dechex($object->amount), 4, "0", STR_PAD_LEFT);
            $packet .= str_pad(dechex($object->amount), 4, "0", STR_PAD_LEFT);
            $packet .= str_pad(dechex($object->pos_x), 4, "0", STR_PAD_LEFT);
            $packet .= str_pad(dechex($object->pos_y), 4, "0", STR_PAD_LEFT);
            $packet .= str_pad("00", 2, "0", STR_PAD_LEFT);
            $packet .= str_pad(dechex($object->layer), 2, "0", STR_PAD_LEFT);
            $packet .= str_pad(dechex($object->color), 4, "0", STR_PAD_LEFT);
            $packet .= "20";
            $packet .= "0000";

            $updateRange = array(
                'from' => array('x' => ($object->pos_x - 10), 'y' => ($object->pos_y - 10)),
                'to' => array('x' => ($object->pos_x + 10), 'y' => ($object->pos_y + 10)),
            );

            foreach ($chunk['players'] as $client => $alive) {
                $player = UltimaPHP::$socketClients[$client]['account']->player;
                if ($player->position['x'] >= $updateRange['from']['x'] && $player->position['x'] <= $updateRange['to']['x'] && $player->position['y'] >= $updateRange['from']['y'] && $player->position['y'] <= $updateRange['to']['y']) {
                    Sockets::out($player->client, $packet, false);
                }
            }
        }
    }

}
