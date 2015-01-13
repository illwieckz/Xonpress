<?php

/**
 * \file
 * \author Mattia Basaglia
 * \copyright Copyright 2013-2015 Mattia Basaglia
 * \section License
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
require_once("table.php");

/**
 * \brief Basic connection to darkplaces
 */
class DarkPlaces_Connection
{
	protected $dpheader = "\xff\xff\xff\xff";
	protected $dpresponses = array(
		"rcon" => "n",
		"srcon" => "n",
		"getchallenge" => "challenge ",
		"getinfo" => "infoResponse\n",
		"getstatus" => "statusResponse\n",
	);
	protected $dpreceivelen = 1399;

	public $host;
	public $port;
	protected $socket;

	function __construct($host="127.0.0.1", $port=26000) 
	{
		$this->host = $host;
		$this->port = $port;
	}

	function socket()
	{
		if ( $this->socket == null )
		{
			$this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
			if ( $this->socket ) {
				socket_bind($this->socket, $this->host);
				// default timeout = 1, 40 millisecond
				$this->socket_timeout(1000,40000);
			}
		}
		return $this->socket;
	}
	
	function socket_timeout($send_microseconds, $receive_microseconds = -1)
	{
		if ( !$this->socket() ) return false;
		
		if ( $receive_microseconds < 0 )
			$receive_microseconds = $send_microseconds;
		
		socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, 
			array('sec' => 0, 'usec' => $send_microseconds));
			
		socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, 
			array('sec' => 0, 'usec' => $receive_microseconds));
		
	}
	
	function request($request)
	{
		if ( !$this->socket() ) return false;
		
		$request_command = strtok($request," ");
		$contents = $this->dpheader.$request;
		
		socket_sendto($this->socket, $contents, strlen($contents), 
			0, $this->host, $this->port);
		
		$received = "";
		socket_recvfrom($this->socket, $received, $this->dpreceivelen,
			0, $this->host, $this->port);
		
		$header = $this->dpheader;
		if ( !empty($this->dpresponses[$request_command]) )
			$header .= $this->dpresponses[$request_command];
		if ( $header != substr($received, 0, strlen($header)) )
			return false;
		return substr($received, strlen($header));
	}
	
	function status() 
	{
		$status_response = $this->request("getstatus");
		if ( !$status_response )
			return array("error" => true);
		
		$lines = explode("\n",trim($status_response,"\n"));
		
		$info = explode("\\",trim($lines[0],"\\"));
		
		$result = array ( 
			"error" => false,
			"host" => $this->host,
			"port" => $this->port,
		);
		
		for ( $i = 0; $i < count($info)-1; $i += 2 )
			$result[$info[$i]] = $info[$i+1];
		
		if ( count($lines) > 1 )
		{
			$result["players"] = array();
			for ( $i = 1; $i < count($lines); $i++ )
			{
				$player = new stdClass();
				$player->score = strtok($lines[$i]," ");
				$player->ping = strtok(" ");
				$player->name = substr(strtok("\n"),1,-1);
				$result["players"][$i-1] = $player;
			}
		}
		
		return $result;
	}
}

/**
 * \brief Static access to darkplaces servers and caches results
 */
class DarkPlaces_Singleton
{
	private $connections = array();
	
	protected function __construct() {}
	protected function __clone() {}
	
	static function instance()
	{
		static $instance = null;
		if ( !$instance ) 
			$instance = new DarkPlaces_Singleton();
		return $instance;
	}
	
	// "Factory" that ensures a single instance for each server (per page load)
	protected function get_connection($host, $port)
	{
		$server = "$host:$port";
		if (isset($this->connections[$server]))
			return $this->connections[$server];
		return $this->connections[$server] = new DarkPlaces_Connection($host,$port);
	}
	
	function status($host="127.0.0.1", $port=26000)
	{
		$conn = $this->get_connection($host,$port);
		if ( !empty($conn->cached_status) )
			return $conn->cached_status;
		return $conn->cached_status = $conn->status();
	}
	
	function status_html($host = "127.0.0.1", $port = 26000, 
		$public_host = null, $css_prefix="dptable_")
	{
		$status = $this->status($host, $port);
		
		if ( empty($public_host) ) $public_host = $host;

		$html = "";
		$status_table = new HTML_Table("{$css_prefix}status");

		$status_table->simple_row("Server","$public_host:$port");

		if ( $status["error"] )
		{
			$status_table->simple_row("Error", 
				"<span class='{$css_prefix}error'>Could not retrieve server info</span>", 
				false);
			$html .= $status_table;
		}
		else
		{
			$status_table->simple_row("Name", 
				DarkPlacesStringConverter::string_dp2none($status["hostname"]));
			$status_table->simple_row("Map", $status["mapname"]);
			$status_table->simple_row("Players", 
				"{$status['clients']}/{$status['sv_maxclients']}".
					((int)$status['bots'] > 0 ? " ({$status['bots']} bots)": "")
			);

			$html .= $status_table;

			if (!empty($status["players"]))
			{
				$players = new HTML_Table('xonpress_players');
				$players->header_row(array("Name", "Score", "Ping"));

				foreach ( $status["players"] as $player )
					$players->data_row( array (
						DarkPlacesStringConverter::string_dp2html($player->name),
						$player->score == -666 ? "spectator" : $player->score,
						$player->ping != 0 ? $player->ping : "bot",
					), false );
				$html .= $players;
			}
		}
		
		return $html;
	}
	
	
	
}


/**
 * \brief Simple, 12 bit rgb color
 */
class Color_12bit
{
	public $r, $g, $b;
	
	function __construct ($r=0, $g=0, $b=0)
	{
		$this->r = $r;
		$this->g = $g;
		$this->b = $b;
	}
	
	/**
	 * \brief Get the 12bit integer
	 */
	function bitmask()
	{
		return ($this->r<<8)|($this->g<<4)|$this->b;
	}
	
	function luma()
	{
		return (0.3*$this->r + 0.59*$this->g + 0.11*$this->b) / 15;
	}
	
	function multiply($value)
	{
		$this->r = (int)($this->r*$value);
		$this->g = (int)($this->g*$value);
		$this->b = (int)($this->b*$value);
	}
	
	/**
	 * \brief Decode darkplaces color
	 */
	static function decode_dp($dpcolor)
	{
		$dpcolor = ltrim($dpcolor,"^x");
		
		if ( strlen($dpcolor) == 3 )
			return new Color_12bit(hexdec($dpcolor[0]),hexdec($dpcolor[1]),hexdec($dpcolor[2]));
		else if ( strlen($dpcolor) == 1 )
			switch ( $dpcolor[0])
			{
				case 0: return new Color_12bit(0,0,0);
				case 1: return new Color_12bit(0xf,0,0);
				case 2: return new Color_12bit(0,0xf,0);
				case 3: return new Color_12bit(0xf,0xf,0);
				case 4: return new Color_12bit(0,0,0xf);
				case 5: return new Color_12bit(0,0xf,0xf);
				case 6: return new Color_12bit(0xf,0,0xf);
				case 7: return new Color_12bit(0xf,0xf,0xf);
				case 8:
				case 9: return new Color_12bit(0x8,0x8,0x8);
			}
		return new Color_12bit();
	}
	
	/**
	 * \brief Encode to html
	 */
	function encode_html()
	{
		return "#".dechex($this->r).dechex($this->g).dechex($this->b);
	}
	
	function __toString()
	{
		return $this->encode_html();
	}
}

// DarkPlaces to HTML functor
class DarkPlacesStringConverter
{	
	private $open = false;
	
	function html_close() 
	{
		if ( $this->open )
		{
			$this->open = false;
			return "</span>";
		}
		return "";
	}
	
	function __invoke($matches="") 
	{
		if (!empty($matches[2]))
		{
			$close = $this->html_close();
			$this->open = true;
			
			$color = Color_12bit::decode_dp($matches[2]);
			
			$luma = $color->luma();
			if ( $luma > 0.8 )
				$color->multiply(0.8);
			
			return "$close<span style='color: $color;'>";
		}
		
		if (!empty($matches[1]))
			$text = "^";
		else if (is_array($matches))
			$text = $matches[0];
		else
			$text = $matches;
		
		return htmlspecialchars($text);
	}
	
    //                              1      2                                   3
	static private $color_regex = "/(\^\^)|((?:\^[0-9])|(?:\^x[0-9a-fA-F]{3}))|([^^]*(?:^$)?)/";
	
	/**
	 * \brief Strip colors from a DP colored string
	 */
	static function string_dp2none($string)
	{
		return preg_replace_callback(self::$color_regex,
			function ($matches)
			{
				if ( $matches[0] == "^^" )
					return "^";
				else if ( !empty($matches[3]) )
					return $matches[3];
				return "";
			}
			,self::string_dp_convert($string));
	}
	
	/**
	 * \brief Convert a colored DP string to a colored HTML string
	 */
	static function string_dp2html($string)
	{
		$functor = new DarkPlacesStringConverter();
		
		return preg_replace_callback(self::$color_regex,$functor,
			self::string_dp_convert($string)).$functor->html_close();
	}
	
	static private $qfont_table = array(
	'',   ' ',  '-',  ' ',  '_',  '#',  '+',  '·',  'F',  'T',  ' ',  '#',  '·',  '<',  '#',  '#', // 0
	'[',  ']',  ':)', ':)', ':(', ':P', ':/', ':D', '«',  '»',  '·',  '-',  '#',  '-',  '-',  '-', // 1
	'?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?', // 2 
	'?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?', // 3
	'?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?', // 4
	'?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?', // 5
	'?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?', // 6
	'?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?', // 7
	'=',  '=',  '=',  '#',  '¡',  '[o]','[u]','[i]','[c]','[c]','[r]','#',  '¿',  '>',  '#',  '#', // 8
	'[',  ']',  ':)', ':)', ':(', ':P', ':/', ':D', '«',  '»',  '#',  'X',  '#',  '-',  '-',  '-', // 9
	' ',  '!',  '"',  '#',  '$',  '%',  '&',  '\'', '(',  ')',  '*',  '+',  ',',  '-',  '.',  '/', // 10
	'0',  '1',  '2',  '3',  '4',  '5',  '6',  '7', '8',  '9',  ':',  ';',  '<',  '=',  '>',  '?',  // 11
	'@',  'A',  'B',  'C',  'D',  'E',  'F',  'G', 'H',  'I',  'J',  'K',  'L',  'M',  'N',  'O',  // 12
	'P',  'Q',  'R',  'S',  'T',  'U',  'V',  'W', 'X',  'Y',  'Z',  '[',  '\\', ']',  '^',  '_',  // 13
	'.',  'A',  'B',  'C',  'D',  'E',  'F',  'G', 'H',  'I',  'J',  'K',  'L',  'M',  'N',  'O',  // 14
	'P',  'Q',  'R',  'S',  'T',  'U',  'V',  'W', 'X',  'Y',  'Z',  '{',  '|',  '}',  '~',  '<'   // 15
	);
	
	/**
	 * \brief Convert special DP characters to Unicode
	 * \note Supports for up to 3 byte long UTF-8 characters
	 */
	static function string_dp_convert($string)
	{
		$out = "";
		
		$unicode = array();        
		$v = array();
		
		for ($i = 0; $i < strlen( $string ); $i++ ) 
		{
			$c = $string[$i];
			$o = ord($c);
			
			if ( $o < 128 ) 
				$out .= $c;
			else 
			{
			
				if ( count($v) == 0 )
				{
					$s = "";
					$length = ( $o < 224 ) ? 2 : 3;
				}
				
				$v[] = $o;
				$s .= $c;
				
				if ( count( $v ) == $length ) 
				{
					$unicode = ( $length == 3 ) ?
						( ( $v[0] % 16 ) << 12 ) + ( ( $v[1] % 64 ) << 6 ) + ( $v[2] % 64 ):
						( ( $v[0] % 32 ) << 6 ) + ( $v[1] % 64 );
					$out .= ( ($unicode & 0xFF00) == 0xE000 ) ? self::$qfont_table[$unicode&0xff] : $s;
					
					$v = array();
				}
			} 
		}
		return $out;
	}
}


function DarkPlaces()
{
	return DarkPlaces_Singleton::instance();
}
