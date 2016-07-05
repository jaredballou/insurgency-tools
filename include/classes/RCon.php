<?php
/*
	Basic CS:S Rcon class by Freman.  (V1.00)
	Modified by Jared Ballou <insurgency@jballou.com>
	----------------------------------------------
	Ok, it's a completely working class now with with multi-packet responses

	Contact: printf("%s%s%s%s%s%s%s%s%s%d%s%s%s","rc","on",chr(46),"cl","ass",chr(64),"pri","ya",chr(46),2,"y",chr(46),"net")

	Behaviour I've noticed:
		rcon is not returning the packet id.
*/

// Packet command types
define("SERVERDATA_EXECCOMMAND",2);
define("SERVERDATA_AUTH",3);

class RCon {
	var $Password;
	var $Host;
	var $Port = 27015;
	var $_Sock = null;
	var $_Id = 0;
	var $Authenticated = 0;
	var $ConnectTimeout=30;
	var $TimeoutSeconds = 2;
	var $TimeoutMicroseconds = 500;
	// Constructor - 
	function RCon ($Host='127.0.0.1',$Port=27015,$Password,$ConnectTimeout=30,$Seconds=2,$Microseconds=500) {
		$this->Password = $Password;
		// If Host came in as ip:port format, unpack it
		$bits = explode(':',$Host);
		var_dump($bits,count($bits));
		if (count($bits) == 2) {
			if (is_numeric($bits[1])) {
				$Host = $bits[0];
				$Port = (int)$bits[1];
			}
		}
		$this->Host = $Host;
		$this->Port = $Port;
		$this->ConnectTimeout = $ConnectTimeout;
		$this->TimeoutSeconds = $Seconds;
		$this->TimeoutMicroseconds = $Microseconds;

		// Create Socket interface for this instance
		$this->_Sock = @fsockopen($this->Host,$this->Port, $errno, $errstr, $this->ConnectTimeout) or
	    		die("Unable to open socket: $errstr ($errno)\n");
		// Set timeout
		$this->_Set_Timeout($this->_Sock,$this->TimeoutSeconds,$this->TimeoutMicroseconds);
		// Attempt to authenticate with server
//		if (!$this->Authenticated) {
//			$this->Auth();
//		}
		var_dump($this);
    	}
	// Authenticate this session with the server
	function Auth () {
		if ($this->Authenticated) {
			echo "Already authenticated!\n";
			return $this->Authenticated;
		}
		$PackID = $this->_Write(SERVERDATA_AUTH,$this->Password);

		// Real response (id: -1 = failure)
		$ret = $this->_PacketRead();
		if ($ret[1]['id'] == -1) {
			die("Authentication Failure\n");
		} else {
			$this->Authenticated = $ret[1]['id'];
			var_dump($ret[1]['id']);
		}
		return $this->Authenticated;
	}
	// Set timeout on commands
	function _Set_Timeout(&$socket,$seconds,$microseconds=0) {
		// Save these settings in the instance
		$this->TimeoutSeconds = $seconds;
		$this->TimeoutMicroseconds = $microseconds;
		// Older PHP uses socket, new PHP calls it stream
		if (version_compare(phpversion(),'4.3.0','<')) {
			return socket_set_timeout($socket,$seconds,$microseconds);
		}
		return stream_set_timeout($socket,$seconds,$microseconds);
	}
	// Send a single packet to the server
	function _Write($cmd, $s1='', $s2='') {
		// Get and increment the packet id
		$id = ++$this->_Id;

		// Put our packet together. This is the payload.
		$data = pack("VV",$id,$cmd).$s1.chr(0).$s2.chr(0);

		// Prefix the packet size, complete packet is <size><packet id><command id><s1>\0<s2>\0

		$data = pack("V",strlen($data)).$data;
var_dump($cmd,$s1,$s2,$data);
		// Send packet
		fwrite($this->_Sock,$data,strlen($data));

		// In case we want it later we'll return the packet id
		return $id;
	}

	// Get the returned packets off the wire, unpack the response data we want
	function _PacketRead() {
		//Declare the return array
		$retarray = array();
		//Fetch the packet size
		while ($size = @fread($this->_Sock,4)) {
			$size = unpack('V1Size',$size);
			//Work around valve breaking the protocol
			if ($size["Size"] > 4096) {
				//pad with 8 nulls
				$packet = "\x00\x00\x00\x00\x00\x00\x00\x00".fread($this->_Sock,4096);
			} else {
				//Read the packet back
				$packet = fread($this->_Sock,$size["Size"]);
			}
			array_push($retarray,unpack("V1ID/V1Response/a*S1/a*S2",$packet));
		}
		return $retarray;
	}
	// Read the full response to the last command
	function Read() {
		$Packets = $this->_PacketRead();

		foreach($Packets as $pack) {
			if (isset($ret[$pack['ID']])) {
				$ret[$pack['ID']]['S1'] .= $pack['S1'];
				$ret[$pack['ID']]['S2'] .= $pack['S1'];
			} else {
				$ret[$pack['ID']] = array(
					'Response' => $pack['Response'],
					'S1' => $pack['S1'],
					'S2' =>	$pack['S2'],
				);
			}
		}
		return $ret;
	}
	// Prep and process command string and pass it to writer
	function sendCommand($Command) {
		$Command = '"'.trim(str_replace(' ','" "', $Command)).'"';
		$this->_Write(SERVERDATA_EXECCOMMAND,$Command,'');
	}
	// Send RCON command to server
	function rconCommand($Command) {
		// Send command to server
		$this->sendCommand($Command);

		// Read response
		$ret = $this->Read();

		//ATM: Source servers don't return the request id, but if they fix this the code below should read as
		// return $ret[$this->_Id]['S1'];
		return $ret[0]['S1'];
	}
}
?>
