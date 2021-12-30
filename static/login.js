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

const database_name = 'wcauth';

function db_interface() {
  let self = this;
  self.db = null;
  self.table = 'key';

  self.open = function() {
    return new Promise (function (fulfill, reject) {
      let req = indexedDB.open(database_name, 4);
      req.onsuccess = function (evt) {
	self.db = evt.target.result;
	fulfill (self);
      }
      req.onupgradeneeded = function(evt) {
	self.db = evt.target.result;
	if (! self.db.objectStoreNames.contains(self.table)) {
	  self.db.createObjectStore(self.table);
	}
      }
    });
  }

  self.get_key = function () {
    return new Promise(function (fulfill, reject) {
      var trans = self.db.transaction([self.table], "readonly");
      var object_store = trans.objectStore(self.table);
      let req = object_store.get(1);
      req.onsuccess = function(evt) {
	fulfill(evt.target.result);
      };
      req.onerror = function(evt) {
	reject(evt.target.error);
      };
    });
  };

  self.save_key = function(item) {
    return new Promise(function(fulfill, reject) {
      var trans = self.db.transaction([self.table], "readwrite");
      trans.oncomplete = function(evt) {fulfill(item);};
      var objectStore = trans.objectStore(self.table);
      objectStore.put(item, 1);
    });
  };
}

async function get_key_pair () {
  let db = new db_interface();
  await db.open();
  let key = await db.get_key();
  if (! key) {
    let key_pair = await window.crypto.subtle.generateKey(
      {
	name: "RSASSA-PKCS1-v1_5",
	modulusLength: 1024,
	publicExponent: new Uint8Array([1, 0, 1]),
	hash: "SHA-256",
      }, 
      false, // not extractable
      ["sign", "verify"]);
    key = [key_pair.publicKey, key_pair.privateKey];
    await db.save_key(key);
  }
  return (key);
}

async function delete_database () {
  await window.indexedDB.deleteDatabase(database_name);
}

async function wcauth_login (nonce) {
  let key_pair = await get_key_pair ();
  let public_key = key_pair[0];
  let private_key = key_pair[1];

  pub = await encode_public_key (public_key);

  var enc = new TextEncoder();
  let nonce_bytes = enc.encode(nonce)

  let sig = await window.crypto.subtle.sign(
    {name:"RSASSA-PKCS1-v1_5"}, 
    private_key, 
    nonce_bytes
  );

  let sig_base64 = base64_encode (sig);

  let dest = window.location.pathname +
      "?sig=" + encodeURIComponent(sig_base64) +
      "&pub=" + encodeURIComponent(pub);

  // browser side redirect to dest
  window.location = dest;
}

document.addEventListener("DOMContentLoaded", function() {
  let elt;

  // if this element exists, it contains the nonce that needs signing
  if ((elt = document.getElementById('wcauth_signin')) != null) {
    wcauth_login (elt.innerHTML);
    // NORETURN
  }

  // if this element exists, user wants to forget the private key
  if ((elt = document.getElementById('wcauth_delete')) != null) {
    delete_database();
    // browser side redirect to site root
    window.location = '/';
  }

});

