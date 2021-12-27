<?php

require_once("app.php");

$anon_ok = 1;

pstart ();

$body .= "<p>hello</p>\n";

$count = 1 + intval(@getsess ("count"));
putsess ("count", $count);
$body .= sprintf ("<p>count = %d</p>\n", $count);



pfinish ();
