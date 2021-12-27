<?php

require_once("app.php");

$anon_ok = 1;

pstart ();

putsess ("key_id", 0);

redirect ("/");
