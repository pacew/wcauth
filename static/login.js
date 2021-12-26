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

  const key_name = "wcauth6";

  key = await key_store.getKey("name", key_name);
  if (! key) {
    console.log ("make key");
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

//  nonce_bytes = new Uint8Array([0]);
//  nonce_bytes[0] = 32;

  console.log(['input', nonce_bytes]);
  let sig = await window.crypto.subtle.sign({
    name: "RSASSA-PKCS1-v1_5",
  }, key_pair.privateKey, nonce_bytes);

  console.log(['sig', sig]);

  let sig_view = new Uint8Array(sig);

  var cksum = 0;
  for (var i = 0; i < sig.byteLength; i++)
    cksum += sig_view[i];
  console.log(cksum);
  // 16159

  // 132 174 55 246 ... for 128 bytes
  console.log(sig);
  let sig_base64 = base64_encode (sig);
  console.log(sig_base64);

  let dest = "/login.php" +
      "?cksum=" + cksum +
      "&sig=" + encodeURIComponent(sig_base64) +
      "&pub=" + encodeURIComponent(pub);

  console.log(dest);
  window.location = dest;
}

  // priv_key = key_pair.privateKey

  // var nonce = new Uint8Array(8);
  // self.crypto.getRandomValues(nonce);




var wcauth_send_key = 0;
var wcauth_nonce = "";

$(function () {
  if (wcauth_send_key)
    wcauth_start ();
});


