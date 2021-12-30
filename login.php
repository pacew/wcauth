<?php

require_once("app.php");

$anon_ok = 1;

pstart ();

$extra_javascript .= "<script src='login.js'></script>\n";

$arg_pub = trim (@$_REQUEST['pub']);
$arg_sig = trim (@$_REQUEST['sig']);
$arg_return_to = trim(@$_REQUEST['return_to']);
$arg_delete = intval(@$_REQUEST['delete']);

if ($arg_delete == 1) {
    putsess ("key_id", 0);
    $body .= "<div style='display:none' id='wcauth_delete'></div>\n";
    pfinish ();
}


if (($nonce = @getsess ("nonce")) == "") {
    $nonce = generate_urandom_string(8);
    putsess ("nonce", $nonce);
}

if ($arg_pub == "") {
    putsess ("login_return_to", $arg_return_to);

    $body .= sprintf ("<div style='display:none' id='wcauth_signin'>"
        ."%s</div>\n", h($nonce));

    // $extra_javascript .= "<script>\n";
    // $extra_javascript .= "wcauth_send_key = 1;\n";
    // $extra_javascript .= sprintf ("wcauth_nonce = '%s';\n", $nonce);
    // $extra_javascript .= "</script>\n";
    pfinish ();
}

$sig_binary = base64_decode ($arg_sig);

$val = openssl_verify ($nonce, $sig_binary, $arg_pub, 'sha256');

if ($val <= 0) {
    $body .= "<p>invalid login</p>\n";
    putsess ("nonce", "");
    pfinish ();
}

$body .= "login ok";

$q = query ("select key_id from pub_keys where key_text = ?", $arg_pub);

if (($r = fetch ($q)) == NULL) {
    $key_id = get_seq ();
    $user_id = get_seq ();

    query ("insert into users (user_id) values (?)", $user_id);
    query ("insert into pub_keys (key_id, user_id, key_text)"
        ." values (?, ?, ?)",
        array ($key_id, $user_id, $arg_pub));
} else {
    $key_id = intval ($r->key_id);
}

putsess ("key_id", $key_id);

$t = getsess ("login_return_to");

if ($t)
    redirect ($t);

redirect ("/");
