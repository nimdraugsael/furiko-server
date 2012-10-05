<?php
	$line = "SIP/010-000000ae      3      f                  1    f               zxcv               1234 asdf         f";
	$result = preg_split('/ /', $line, -1, PREG_SPLIT_NO_EMPTY);

	print_r($result);

?>