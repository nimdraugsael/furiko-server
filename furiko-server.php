<?php
	require_once 'PAMI/Autoloader/Autoloader.php';  
	require_once 'log4php/Logger.php';
	PAMI\Autoloader\Autoloader::register(); 
	
	use PAMI\Client\Impl\ClientImpl as PamiClient;  
	use PAMI\Message\Action\OriginateAction;
	use PAMI\Message\Action\HangupAction;
	use PAMI\Message\Action\StatusAction;
	use PAMI\Message\Action\AttendedTransferAction;

	use PAMI\Message\Event\EventMessage;

	// for actions : ActionId = microtime(true)

	declare(ticks=1);

	require 'JAXL/jaxl.php';

	if (isset($argv[1]) == false) {
		$child_pid = pcntl_fork();
		if ($child_pid) {
		    // Выходим из родительского, привязанного к консоли, процесса
		    exit();
		}
		// Делаем основным процессом дочерний.
		posix_setsid();

		$baseDir = dirname(__FILE__);
		ini_set('error_log',$baseDir.'/furiko_error.log');
		fclose(STDIN);
		fclose(STDOUT);
		fclose(STDERR);
		$STDIN = fopen('/dev/null', 'r');
		$STDOUT = fopen($baseDir.'/furiko_application.log', 'ab');
		$STDERR = fopen($baseDir.'/furiko_daemon.log', 'ab');
	}

	// include 'XMPPHP/XMPP.php';
	function sendMessage($to, $body)
	{
		$stanza = new XMPPStanza("message");
		$stanza->to = $to;
		$stanza->body = $body;
		$stanza->type = "headline";
		$stanza->from = "pbx@avanpx";
		global $xmpp_client;
		$xmpp_client->send($stanza);
	}

	function processMessage( $stanza )
	{
		global $pamiClient;
		global $xmpp_client;
		global $db;
		global $astdb;
		global $users;
		global $channels; # channels[$extension] = $channel
		global $originating_calls;

		global $listeners;

		// var_dump($stanza);
		$msg_type = "headline";

		$error_401 = array(	'Action' => 'Error',
											 	'ErrorCode' => '401',
											 	'ErrorMessage' => 'Not registered. Handshake first');

		// $users['nimdraugtest@avanpbx'] = '011';
		// $users['nimdraug@avanpbx'] = '010';
		// var_dump($users);
		// $body = $msg['body'];
		$body = str_replace("&quot;", '"', $stanza->body);

		// echo "body :> $body";
    $request = json_decode($body, true);
    $from = bare_jid($stanza->from);
    // echo bare_jid($stanza->from);
    // --
    // unset( $request );
    if ( isset($request) ) 
    {
    	if ( isset($request['Action']) )
    		switch($request['Action']) {
            case 'Handshake':
              $ext = $astdb->getExtension($from);
              $response_array = array(	'Action' => 'Handshake', 
										'Success' => True );
              $response = json_encode($response_array);
              $users[$from] = $ext;
            	sendMessage($from, $response);
            break;
            case 'Goodbye':
          		unset($users[$from]);
            	$response_array = array(	'Action' => 'Goodbye', 
                							'Success' => True );
              $response = json_encode($response_array);
          		sendMessage($from, $response);
          	break;
            
            break;
            case 'History':
            	if (isset($users[$from]))
            	{
            		if ( isset($request['With']) )
            		{
	            		$from = bare_jid($from);
	            		$with = $request['With'];
	            		// echo "getHistory: from $from , with $with";
	            		$history = $db->getHistory($from, $with);
	            		if ($history)
	            		{
		            		$response_array = array(	'Action' => 'History', 
		                								'Success' => True,
		                								'History' => $history );
	            		}
	            		else
	            		{
		            		$response_array = array(	'Action' => 'History', 
			                							'Success' => False,
			                							'Error' => 'No history for entry' );		            			
	            		}
            		}
            		else
            		{
            			$response_array = array(	'Action' => 'History', 
                									'Success' => False,
                									'Error' => "Need 'with' parameter" );	
            		}
            	}
            	else
            	{
            		$response_array = $error_401;
            	}
                $response = json_encode($response_array);
            		sendMessage($from, $response);
            break;
            case 'OutgoingCall':
            	echo "Got outgoing call\n";
            	if (isset($users[$from]))
            	{
            		if ( isset($request['With']) )
            		{
	            		$with = $request['With'];
	            		// echo "getHistory: from $from , with $with";
	            		$from_ext = $astdb->getExtension($from);
	            		$with_ext = $astdb->getExtension($with);
	            		echo "\nNew call from $from to $with";
	            		echo "\nFound extensions: from $from_ext, with $with_ext";
	            		if ( ($from_ext) && ($with_ext) )
	            		{
	            			$oa = new OriginateAction("SIP/$from_ext");
		            		$oa->setExtension($with_ext);
		            		$oa->setContext("from-internal");
		            		$oa->setPriority("1");
		            		$oa->setCallerId($from);
		            		try {
		            			$response = $pamiClient->send($oa);
			            		$response_array = array(	'Action' => 'OutgoingCall', 
			            									'Extension' => $with_ext,
			                								'Success' => $response->isSuccess() );
		            		} catch (Exception $e) {
		            			$response_array = array(	'Action' => 'OutgoingCall', 
			                								'Success' => false );		            		
		            		}
	            		}
	            		else 
	            		{
	            			$response_array = array(	'Action' => 'OutgoingCall', 
		                								'Success' => false,
		                								'Error' => 'Extensions not found' );
	            		}
            		}
            		else
            		{
            			$response_array = array(	'Action' => 'OutgoingCall', 
                									'Success' => False,
                									'Error' => "Need 'with' parameter" );	
		   			}
            	}
            	else
            	{
            		$response_array = $error_401;
            	}
                $response = json_encode($response_array);
            		sendMessage($from, $response);
              $response = json_encode($response_array);
          		sendMessage($from, $response);
            break;
            case 'TransferCall':
            	echo "Got transfer\n";
            	echo "$body \n";
            	if (isset($users[$from]) && isset($request["Channel"]) )
            	{
            		// AttendedTransferAction
            		if (isset($request["To"])) {
            			$to_extension = $astdb->getExtension($request["To"]);
            			if ($to_extension != null) {
            				$channel = $request["Channel"]; 
            				
            				$listeners[bare_ext($channel)][] = $from;

			            	echo "Got transfer: To $to_extension channel $channel" ;
            				$ra = new AttendedTransferAction($channel, $to_extension, "from-internal", 1);
            				try {
	            				$response = $pamiClient->send($ra);
			            		$response_array = array(	'Action' => 'TransferCall', 
			            									'To' => $request["To"],
			            									'Channel' => $channel,
			            									'ToExtension' => $to_extension,
			                								'Success' => $response->isSuccess() );
		            		} catch (Exception $e) {
		            			$response_array = array(	'Action' => 'TransferCall', 
			                								'Success' => false );		            		
		            		}
            			}
            			else 
            			{
            				$response_array = array(	'Action' 	=> 'TransferCall', 
				                						'Success' 	=> false,
				                						'Error'		=> "No jid found for " . $request["To"] );
            			}
            		}
            		else
	            	{
	            		$response_array = array(	'Action' 	=> 'TransferCall', 
			                						'Success' 	=> false );
	            	}
            	}
            	else
            	{
            		$response_array = array(	'Action' 	=> 'TransferCall', 
		                						'Success' 	=> false );
            	}
                $response = json_encode($response_array);
           		sendMessage($from, $response);
            break;
            case 'Hangup':
            	echo "Got hangup\n";
            	if (isset($users[$from]))
            	{
            		if ( isset($request['Channel']) )
            		{
            			$astdb->hangupByChannel($request['Channel']);
            		}
            		else 
            		{
            	    	$astdb->hangupByJid($from);
            		}
            		// $sa = new StatusAction($channel);
            		// $response = $pamiClient->send($sa);
            		// var_dump($response);
            		// $ha = new HangupAction($channel);
								// $response = $pamiClient->send($ha);
								// echo "Hangup: $channel";
								$response_array = array(	'Action' 	=> 'HangupEvent',
															'Success' 	=> 'True',
															'Channel'	=> $request['Channel'] );
            	}
            	else
            	{
            		$response_array = $error_401;
            	}
                $response = json_encode($response_array);
           			sendMessage($from, $response);
           	break;
        }
    }
	}

	function bare_jid($jid)
	{
		return preg_replace('/([^\/]+)\/[^\/]+/i', '$1', $jid);		
	}

	function bare_ext($channel)
	{
		preg_match('/[^\/]+\/([^\-|@]+)/i', $channel, $result);
		if (isset($result[1])) return $result[1];
	}

	class AsteriskDB
	{
		public $users;

		public function getList()
		{
			try {
				system("sudo /usr/sbin/asterisk -rx \"database show AMPUSER\" | grep jid > /tmp/asterisk_jid_list.txt");
				$fd=fopen("/tmp/asterisk_jid_list.txt","r");
				while ($line=fgets($fd,1000)) {
					preg_match('/\/AMPUSER\/([^\/]+)\/jid[\s]+:[\s]+([^\s]+)/i', $line, $result);
					if ($result) {
						$list[trim($result[2])] = trim($result[1]);
					} 
					else 
					{
						// echo "\n Cannot get info from asterisk db";
						// echo "\n line from astdb: $line";						
					}
				}
				fclose ($fd);
				return $list;
			} catch (Exception $e) {
				echo "Exception in AsteriskDB ~> $e";
			}
		}

		public function hangupByChannel($channel) 
		{
				system("sudo /usr/sbin/asterisk -rx \"channel request hangup $channel\"");
				echo "Hangup by channel: hangup on $channel\`n";
		}

		public function hangupByJid($jid)
		{
			$ext = $this->getExtension($jid);
			$this->hangupByExt($ext);
		}

		public function hangupByExt($ext)
		{
			$list = $this->getChannel($ext);
			foreach ($list as $c) {
				system("sudo /usr/sbin/asterisk -rx \"channel request hangup $c\"");
				echo "hangup on $c\n";
			}
		}

		public function getChannel($ext)
		{
			try {
				system("sudo /usr/sbin/asterisk -rx \"core show channels\" | grep $ext > /tmp/asterisk_channel_list.txt");
				$fd=fopen("/tmp/asterisk_channel_list.txt","r");
				$list = array();
				while ($line=fgets($fd,1000)) {
					echo "$line\n";
					preg_match('/^([^\s]+)[\s]/i', $line, $result);
					if ($result[0]) $list[] = $result[0];
				}
				fclose ($fd);
				return $list;
				// return null;
			} catch (Exception $e) {
				echo "Exception in AsteriskDB ~> $e";
			}			
		}

		public function getBridgedCanal($channel)
		{	
			try {
				system("sudo /usr/sbin/asterisk -rx \"core show channels verbose\" | grep ^$channel > /tmp/asterisk_channel_verbose.txt");
				$fd=fopen("/tmp/asterisk_channel_verbose.txt","r");
				$list = array();
				while ($line=fgets($fd,1000)) {
					$result = preg_split('/ /', $line, -1, PREG_SPLIT_NO_EMPTY);
				}
				fclose ($fd);
				return $result[9];
				// return null;
			} catch (Exception $e) {
				echo "Exception in AsteriskDB ~> $e";
			}	

		}

		public function getExtension($jid)
		{
			$list = $this->getList();
			// var_dump($list);
			// var_dump($list);
			if ($list) {
				if (isset($list[$jid])) {
					return $list[$jid];
				}
			}
			return null;
		}

		public function getJid($extension)
		{
			$list = $this->getList();
			// var_dump($list);
			if ($list) {
				return array_search($extension, $this->getList());	
			}
			// var_dump($list);
			if ($list) {
				return array_search($extension, $this->getList());	
			}
			return null;
		}
	}

	class Database 
	{
		private $mysqli_asterisk; // connection
		private $mysqli_openfire; // connection

		public function __construct() {
			$this->mysqli_asterisk = new mysqli("localhost", "root", "123456", "asterisk");
			$this->mysqli_openfire = new mysqli("localhost", "root", "123456", "openfire");
			$this->mysqli_openfire->query("set names utf8;");
		}
		/**
		 * Get user extension
		 *
		 * @param string $jid
		 */

		public function getExtension($jid) {
			$jid = bare_jid($jid);
			$sql = "select extension 
					from users 
					where jid='$jid'";
			echo "\n jid = $jid \n";
			// $sql = "select * from users";
			// var_dump($this);
			$res = $this->mysqli_asterisk->query($sql);
			if ($res != null)
			{
				$res->data_seek(0);
				$row = $res->fetch_assoc();
				return ($row['extension']);
			}
			else 
			{
				return null;
			}
		}

		public function getJid($extension) {
			$sql = "select jid 
					from users 
					where extension='$extension'";
			$res = $this->mysqli_asterisk->query($sql);
			$res->data_seek(0);
			$row = $res->fetch_assoc();
			return ($row['jid']) ? $row['jid'] : $extension;
		}

		public function getHistory($from, $with)
		{
			$sql = 'from_unixtime(m.time/1000, "%Y-%m-%d %H:%i:%s") time,
					if( m.direction = "to", c.ownerJid , c.withJid ) jid,
					c.withJid as "with",
				    body
					from archiveMessages m, archiveConversations c
					where m.conversationId = c.conversationId
					and ownerJid = "'.$from.'"
					and withJid = "'.$with.'"';
			$res = $this->mysqli_openfire->query($sql);
			if ($res != null) {
				$res->data_seek(0);
				while ($row = $res->fetch_assoc()) {
				    $output[] = array(	'time' 	=> $row['time'],
				   						'with' 	=> $row['with'],
    							 		'jid' 	=> $row['jid'],
    							 		'body' 	=> $row['body'] );
				}
				// var_dump($output);
				return $output;
			}
			else 
			{
				echo "mysqli error : " . $this->mysqli_openfire->error . "\n";				
			}
		}
	}

	global $users;


	date_default_timezone_set('Asia/Vladivostok');
	$pamiClientOptions = array(  
		'log4php.properties' =>  __DIR__ . '/log4php.properties',  
	    'host' => 'avanpbx',        
	    'scheme' => 'tcp://',         
	    'port' => 5038,               
	    'username' => 'furiko',        
	    'secret' => '123456',       
	    'connect_timeout' => 10000,   
	    'read_timeout' => 10000
	);  
	
	$pamiClient = new PamiClient($pamiClientOptions);  
	// Open the connection  
	$pamiClient->open();

	$pamiClient->registerEventListener(  
	    function (EventMessage $event) {
	        if ($event instanceof PAMI\Message\Event\BridgeEvent)
	        {
	        	// $listeners[ext] = [jid][jid][jid]
	        	global $listeners;
	        	global $users;
	        	$channel1 = $event->getChannel1();
	        	$channel2 = $event->getChannel2();
	        	echo "Calls now $channel1~$channel2:> \n";
	        	if ($listeners) {
	        		if ( isset($listeners[bare_ext($channel1)]) ) {
	        			if (is_array($listeners[bare_ext($channel1)])) {
		        			foreach ($listeners[bare_ext($channel1)] as $listener) {
		        				echo "listener $listener got for channel $channel1. Bridge \n";
		        				$response = json_encode(
												array(	'Action' 	=> 'BridgeEvent',
			        									'Success' 	=> 'True',
			        									'Channel'	=> $channel2,
			        									'Extension' => bare_ext($channel2) ));
								sendMessage($listener, $response);
			        		}
	        			}
	        		}
	        		if ( isset($listeners[bare_ext($channel2)]) ) {
	        			if (is_array($listeners[bare_ext($channel2)])) {
		        			foreach ($listeners[bare_ext($channel2)] as $listener) {
		        				echo "listener $listener got for channel $channel2. Bridge \n";
		        				$response = json_encode(
												array(	'Action' 	=> 'BridgeEvent',
			        									'Success' 	=> 'True',
			        									'Channel'	=> $channel1,
			        									'Extension' => bare_ext($channel1) ));
								sendMessage($listener, $response);
			        		}
	        			}
	        		}
	        	}
	        	if ($users != null ) {
		        		$from_jid = array_search(bare_ext($channel1), $users);
		        		if ($from_jid != null) {
		        			$response = json_encode(
											array(	'Action' 	=> 'BridgeEvent',
		        									'Success' 	=> 'True',
		        									'Channel'	=> $channel2,
			        								'Extension' => bare_ext($channel2) ));
							sendMessage($from_jid, $response);
		        		}
						$with_jid = array_search(bare_ext($channel2), $users);
		        		if ($with_jid != null) {
		        			$response = json_encode(
											array(	'Action' 	=> 'BridgeEvent',
			        								'Success' 	=> 'True',
			        								'Channel'	=> $channel1,
			        								'Extension' => bare_ext($channel2) ));
							sendMessage($with_jid, $response);
		        		}		
		        }
						// var_dump($users);
	        } 
	        if ($event instanceof PAMI\Message\Event\HangupEvent)
	        {
	        	global $users;
	        	global $listeners;
	        	$channel = $event->getChannel();
	        	echo "Hangup channel $channel";
	        	
	        	if ( isset($listeners[bare_ext($channel)]) ) {
        			if (is_array($listeners[bare_ext($channel)])) {
	        			foreach ($listeners[bare_ext($channel)] as $listener) {
	        				echo "listener $listener got for channel $channel. Hangup \n";
	        				$response = json_encode(
											array(	'Action' 	=> 'BridgeEvent',
		        									'Success' 	=> 'True',
		        									'Channel'	=> $channel ));
									sendMessage($listener, $response);
		        		}
        			}
        		}
        		
	        	if ($users) {
							$jid = array_search(bare_ext($channel), $users);
							if ($jid != null) {
								$response = json_encode(
									array(	'Action' 	=> 'HangupEvent',
											'Success' 	=> 'True',
											'Channel'	=> $channel ));
								sendMessage($jid, $response);
							}
	        	}
	        } 
	        if ($event instanceof PAMI\Message\Event\DialEvent)
	        {
	        	global $users;
	        	global $db;
	        	global $astdb;
	        	$destination = bare_ext($event->getDestination());
	        	$channel = $event->getChannel();
	        	$from = bare_ext($channel);
	        	
	        	// strange bug?
	        	if ( ($from != $destination) && ($destination != "") )
	        	{
		        	$sub_event = $event->getSubEvent();
							$from_caller_id = $event->getCallerIDName();
							$from_jid = $astdb->getJid($from);
		        	echo "\nDial event -> \n" ;
		        	echo "from $from to $destination\n";
	        		echo "still alive \n";
		        	try {
		        		if ( ($sub_event) && ($destination) && ($users) )
		        		{
				        	if ($sub_event == "Begin") {
										$jid = array_search($destination, $users);
										if ($jid != null) {
											$response = json_encode(
												array(	'Action' 	=> 'IncomingCallEvent',
														'Success' 	=> 'True',
														'From' => $from_caller_id,
														'FromJid' => $from_jid,
														'FromExt' => $from,  
														'Channel' => $event->getDestination() ));
											sendMessage($jid, $response);
										}
				        	}	
		        		}
		        	} catch (Exception $e) {
		        		echo "Excepition in DialEvent: $e\n";
		        	}
	        	}
	        } 
	    }
    );  
	
	// register_tick_function(array($pamiClient, 'process'));

	$db = new Database();
	$astdb = new AsteriskDB();

	$xmpp_client = new JAXL(array(
			'jid' => 'pbx',
			'pass' => '123456',
			'host' => 'avanpbx:5222'
		));

	$xmpp_client->require_xep(array(
		'0199'	// XMPP Ping
	));

	$connected = true;
	
	$xmpp_client->add_cb('on_auth_failure', function($reason) {
		global $xmpp_client;
		$xmpp_client->send_end_stream();
		_info("CALLBACK! got on_auth_failure cb with reason $reason");
	});

	$xmpp_client->add_cb('on_connect_error', function($reason) {
		_info("connect error $reason");
	});
  
	$xmpp_client->add_cb('on_auth_success', function() {
		_info("connected!!");
		global $xmpp_client;
		$xmpp_client->set_status("available!", "dnd", 10);
	});

	$xmpp_client->add_cb('on_disconnect', function() {
		_info("disconnected!!");
		// _info("reconnecting");
		// global $xmpp_client;
		// $xmpp_client->con();
		// global $xmpp_client;
		// $xmpp_client->set_status("available!", "dnd", 10);
	});

	$xmpp_client->add_cb('on_headline_message', function($stanza) {
		global $xmpp_client;
		// var_dump($stanza);
		processMessage($stanza);
	});

	$xmpp_client->start(array(
						'--with-unix-sock' => true), $pamiClient);

?>