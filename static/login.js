function base64_encode(s) {
  return (btoa(String.fromCharCode.apply(null, new Uint8Array(s))));
}

// from https://stackoverflow.com/questions/40314257/export-webcrypto-key-to-pem-format
// Micah Henning
async function encode_public_key (pub_key) {
  const spki = await window.crypto.subtle.exportKey('spki', pub_key);
  let text = window.btoa(String.fromCharCode(...new Uint8Array(spki)));
  text = text.match(/.{1,64}/g).join('\n');
  return `-----BEGIN PUBLIC KEY-----\n${text}\n-----END PUBLIC KEY-----`;
}

async function get_key_pair () {
  let key_store = new KeyStore ();
  await key_store.open();

  const key_name = window.location.host;

  key = await key_store.getKey("name", key_name);
  if (! key) {
    let key_pair = await window.crypto.subtle.generateKey({
      name: "RSASSA-PKCS1-v1_5",
      modulusLength: 1024,
      publicExponent: new Uint8Array([1, 0, 1]),
      hash: "SHA-256",
    }, true, ["sign", "verify"]);

  
    await key_store.saveKey(key_pair.publicKey, key_pair.privateKey, key_name);
  }

  return (await key_store.getKey("name", key_name));
}

async function wcauth_start () {
  let key_pair = await get_key_pair ();

  pub = await encode_public_key (key_pair.publicKey);

  var enc = new TextEncoder();
  let nonce_bytes = enc.encode(wcauth_nonce)

  let sig = await window.crypto.subtle.sign({
    name: "RSASSA-PKCS1-v1_5",
  }, key_pair.privateKey, nonce_bytes);

  let sig_view = new Uint8Array(sig);
  let sig_base64 = base64_encode (sig);

  let dest = "/login.php" +
      "?sig=" + encodeURIComponent(sig_base64) +
      "&pub=" + encodeURIComponent(pub);

  window.location = dest;
}

var wcauth_send_key = 0;
var wcauth_nonce = "";

$(function () {
  if (wcauth_send_key)
    wcauth_start ();
});


