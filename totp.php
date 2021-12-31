<?php

function base32_decode($d) {
    list($t, $b, $r) = array("ABCDEFGHIJKLMNOPQRSTUVWXYZ234567", "", "");

    foreach(str_split($d) as $c)
        $b = $b . sprintf("%05b", strpos($t, $c));

    foreach(str_split($b, 8) as $c)
        $r = $r . chr(bindec($c));
    
    return($r);
}

function base32_encode($d) {
    list($t, $b, $r) = array("ABCDEFGHIJKLMNOPQRSTUVWXYZ234567", "", "");

    foreach(str_split($d) as $c)
        $b = $b . sprintf("%08b", ord($c));

    foreach(str_split($b, 5) as $c)
        $r = $r . $t[bindec($c)];

    return($r);
}


function totp_generate() {
	$f = fopen ("/dev/urandom", "r");
    $val = base32_encode (fread ($f, 10));
    fclose ($f);
    return ($val);
}



function time_code_to_llword ($counter) {
	$bin_counter = "";
	$bin_counter .= chr(0);
	$bin_counter .= chr(0);
	$bin_counter .= chr(0);
	$bin_counter .= chr(0);
	$bin_counter .= chr(($counter >> 24) & 0xff);
	$bin_counter .= chr(($counter >> 16) & 0xff);
	$bin_counter .= chr(($counter >> 8) & 0xff);
	$bin_counter .= chr($counter & 0xff);
	return ($bin_counter);
}

function totp ($key_base32, $time_offset = 0) {
	$time_code = intval (floor (time () / 30)) + $time_offset;

	$hmac = hash_hmac ('sha1',
			   time_code_to_llword ($time_code),
			   base32_decode ($key_base32),
			   true);
	$offset = ord($hmac[19]) & 0x0f;
	$p = ((ord ($hmac[$offset]) & 0x7f) << 24)
		| (ord ($hmac[$offset+1]) << 16)
		| (ord ($hmac[$offset+2]) << 8)
		| (ord ($hmac[$offset+3]));

	$code = sprintf ("%06d", $p % 1000000);
	return ($code);
}

require_once ("qrcode.php");
function make_qr_svg ($msg) {

	$qr_level = "M";

	switch ($qr_level) {
	case "L": $level = QR_ERROR_CORRECT_LEVEL_L; break;
	default:
	case "M": $level = QR_ERROR_CORRECT_LEVEL_M; break;
	case "Q": $level = QR_ERROR_CORRECT_LEVEL_Q; break;
	case "H": $level = QR_ERROR_CORRECT_LEVEL_H; break;
	}

	$qr = QRCode::getMinimumQRCode ($msg, $level);
	$size = $qr->getModuleCount ();

	$pixels_per_cell = 8;
	$keep_clear = 4;
	$raster_size = ($size + $keep_clear * 2) * $pixels_per_cell;

	$svg = "";
	$svg .= sprintf ("<svg xmlns='http://www.w3.org/2000/svg'"
			 ." xmlns:xlink='http://www.w3.org/1999/xlink'"
			 ." width='%d'"
			 ." height='%d'"
			 ." version='1.1'>\n",
			 $raster_size, $raster_size);

	$svg .= "<g id='surface1'>\n";

	$svg .= sprintf ("<rect x='0' y='0' width='%d' height='%d'"
			 ." style='fill:#ffffff;"
			 ." fill-opacity:1;stroke:none;'/>",
			 $raster_size, $raster_size);

	for ($row = 0; $row < $size; $row++) {
		for ($col = 0; $col < $size; $col++) {
			if ($qr->isDark ($row, $col)) {
				$x = ($col + $keep_clear) * $pixels_per_cell;
				$y = ($row + $keep_clear) * $pixels_per_cell;
				$svg .= sprintf ("<rect x='%d.5' y='%d.5'"
						 ." height='%d' width='%d'"
						 ." style='stroke:#000000;"
						 ."  fill:#000000'/>\n",
						 $x, $y,
						 $pixels_per_cell, $pixels_per_cell);
			}
		}
	}
	$svg .= "</g></svg>\n";

	return ($svg);
}
