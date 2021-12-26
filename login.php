<?php

require_once("app.php");

$anon_ok = 1;

pstart ();

$extra_javascript .= "<script src='keystore.js'></script>\n";
$extra_javascript .= "<script src='login.js'></script>\n";

$arg_pub = trim (@$_REQUEST['pub']);
$arg_sig = trim (@$_REQUEST['sig']);
$arg_cksum = intval(@$_REQUEST['cksum']);

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

$body .= sprintf ("<p>cksum %d</p>\n", $arg_cksum);

if (0) {
    $body .= "<pre>\n";
    $body .= h($arg_pub);
    $body .= "</pre>\n";

    $body .= "<pre>\n";
    $body .= h($arg_sig);
    $body .= "</pre>\n";
}

$sig_binary = base64_decode ($arg_sig);

$len = strlen($sig_binary);
$body .= sprintf ("<p>sig len %d</p>\n", $len);

$cksum = 0;
for ($i = 0; $i < $len; $i++) {
    $cksum += ord($sig_binary[$i]);
}
$body .= sprintf ("<p>cksum %d</p>\n", $cksum);

$key = openssl_pkey_get_public ($arg_pub);

if (0) {
    $k = openssl_pkey_get_details($key);
    $body .= "<pre>\n";
    $body .= h($k['key']);
    $body .= h($arg_pub);
    $body .= "</pre>\n";
}

$nonce = 'x';
var_dump ($nonce);
$val = openssl_verify ($nonce, $sig_binary, $arg_pub, 'sha256');
$body .= sprintf ("<p>result %d</p>\n", $val);


pfinish ();
