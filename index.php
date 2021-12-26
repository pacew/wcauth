<?php

require_once("app.php");

$anon_ok = 1;

pstart ();

$body .= "<p>hello</p>\n";

$user_id = intval(getsess ("user_id"));

if ($user_id > 0) {
    $body .= sprintf ("<p>welcome user %d</p>\n", $user_id);
}





$count = 1 + intval(@getsess ("count"));
putsess ("count", $count);
$body .= sprintf ("<p>count = %d</p>\n", $count);



pfinish ();
