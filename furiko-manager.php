<?php 
	
	if (isset($argv[1])) {
		if ($argv[1] == "stop") {
			$output = shell_exec("ls | grep *.manager.pid");
			if ($output != "") {
				preg_match('/([\d]+)/i', $output, $result);
				$pid = $result[1];
				echo "Manager pid found: $pid. Stopping\n";
				shell_exec("kill $pid");
				exit();
			}
			else {
				echo "Manager pid not found\n";
				exit();
			}
		}
	}

	$output = shell_exec("ls | grep *.manager.pid");
	if ($output != "") {
		preg_match('/([\d]+)/i', $output, $result);
		$pid = $result[0];
		echo "Manager is already running. To stop previous manager type:\n  kill $pid\n";
		exit();
	}

	$child_pid = pcntl_fork();
	$child_pid_global = 0;

	if ($child_pid) {
	    // Выходим из родительского, привязанного к консоли, процесса
			$manager_pid_file_handle = $child_pid.".manager.pid";
			$manager_pid_file_handle = fopen($manager_pid_file_handle, 'w') or die("cannot create manager pid file");
			fclose($manager_pid_file_handle);
			global $child_pid_global;
			$child_pid_global = $child_pid;

	    exit();
	}
	// Делаем основным процессом дочерний.
	posix_setsid();
	echo "Started manager daemon for furiko_server.php\n";
	declare(ticks = 1);

	// signal handler function
	function sig_handler($signo)
	{
	 	switch ($signo) {
	     case SIGTERM:
	         // handle shutdown tasks
	     		 echo "\nExiting, removing manager pid file $child_pid_global\n";
	     		 shell_exec("rm *.manager.pid");
	         exit;
	         break;
	     case SIGHUP:
	         // handle restart tasks
	         break;
	     case SIGUSR1:
	         echo "Caught SIGUSR1...\n";
	         break;
	     default:
	         // handle all other signals
	 	}
	}

	pcntl_signal(SIGTERM, "sig_handler");
	pcntl_signal(SIGHUP,  "sig_handler");
	pcntl_signal(SIGUSR1, "sig_handler");



	$baseDir = dirname(__FILE__);
	ini_set('error_log',$baseDir.'/error.log');
	fclose(STDIN);
	fclose(STDOUT);
	fclose(STDERR);
	$STDIN = fopen('/dev/null', 'r');
	$STDOUT = fopen($baseDir.'/application.log', 'ab');
	$STDERR = fopen($baseDir.'/daemon.log', 'ab');

	while (1)
	{
		$output = shell_exec('php furiko-server.php debug');
		echo "furiko-server restarted ::\n";
		echo $output;
		sleep(1);
	}

?>