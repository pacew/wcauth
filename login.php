<?php

require_once("app.php");

$anon_ok = 1;

pstart ();

$extra_javascript .= "<script src='keystore.js'></script>\n";
$extra_javascript .= "<script src='login.js'></script>\n";

$arg_pub = trim (@$_REQUEST['pub']);
$arg_sig = trim (@$_REQUEST['sig']);

if (($nonce = @getsess ("nonce")) == "") {
    $nonce = generate_urandom_string(8);
    putsess ("nonce", $nonce);
}

if ($arg_pub == "") {
    $extra_javascript .= "<script>\n";
    $extra_javascript .= "wcauth_send_key = 1;\n";
    $extra_javascript .= sprintf ("wcauth_nonce = '%s';\n", $nonce);
    $extra_javascript .= "</script>\n";
    pfinish ();
}

$body .= "have pub ";
$body .= "<pre>\n";
$body .= h($arg_pub);
$body .= "</pre>\n";

$sig = base64_decode ($arg_sig);

pfinish ();
