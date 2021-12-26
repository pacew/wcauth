<?php

require_once("app.php");

/* example protected file by leaving $anon_ok at the default 0 value */

pstart ();

$body .= "<p>private stuff...</p>\n";

pfinish ();
