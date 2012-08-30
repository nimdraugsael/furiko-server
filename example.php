<?php
$json = '{"Action":"Handshake"}';

var_dump(json_decode($json));
var_dump(json_decode($json, true));

?>