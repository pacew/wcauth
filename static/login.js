const debug = false;


/*
 * this is the MDN recommended way to get base64 encoding and decoding (!)
 * (the old atob and btoa functions trip over utf8)
 *
 *  https://developer.mozilla.org/en-US/docs/Web
 * /JavaScript/Base64_encoding_and_decoding
 */
/* Array of bytes to Base64 string decoding */
function b64ToUint6 (nChr) {
  return nChr > 64 && nChr < 91 ?
      nChr - 65
    : nChr > 96 && nChr < 123 ?
      nChr - 71
    : nChr > 47 && nChr < 58 ?
      nChr + 4
    : nChr === 43 ?
      62
    : nChr === 47 ?
      63
    :
      0;
}

function base64DecToArr (sBase64, nBlocksSize) {
  var
    sB64Enc = sBase64.replace(/[^A-Za-z0-9\+\/]/g, ""), nInLen = sB64Enc.length,
    nOutLen = nBlocksSize ? Math.ceil((nInLen * 3 + 1 >> 2) / nBlocksSize) * nBlocksSize : nInLen * 3 + 1 >> 2, taBytes = new Uint8Array(nOutLen);

  for (var nMod3, nMod4, nUint24 = 0, nOutIdx = 0, nInIdx = 0; nInIdx < nInLen; nInIdx++) {
    nMod4 = nInIdx & 3;
    nUint24 |= b64ToUint6(sB64Enc.charCodeAt(nInIdx)) << 6 * (3 - nMod4);
    if (nMod4 === 3 || nInLen - nInIdx === 1) {
      for (nMod3 = 0; nMod3 < 3 && nOutIdx < nOutLen; nMod3++, nOutIdx++) {
        taBytes[nOutIdx] = nUint24 >>> (16 >>> nMod3 & 24) & 255;
      }
      nUint24 = 0;
    }
  }

  return taBytes;
}

/* Base64 string to array encoding */
function uint6ToB64 (nUint6) {
  return nUint6 < 26 ?
      nUint6 + 65
    : nUint6 < 52 ?
      nUint6 + 71
    : nUint6 < 62 ?
      nUint6 - 4
    : nUint6 === 62 ?
      43
    : nUint6 === 63 ?
      47
    :
      65;
}

function base64EncArr (aBytes) {
  var nMod3 = 2, sB64Enc = "";
  for (var nLen = aBytes.length, nUint24 = 0, nIdx = 0; nIdx < nLen; nIdx++) {
    nMod3 = nIdx % 3;
    if (nIdx > 0 && (nIdx * 4 / 3) % 76 === 0) { sB64Enc += "\r\n"; }
    nUint24 |= aBytes[nIdx] << (16 >>> nMod3 & 24);
    if (nMod3 === 2 || aBytes.length - nIdx === 1) {
      sB64Enc += String.fromCharCode(uint6ToB64(nUint24 >>> 18 & 63), uint6ToB64(nUint24 >>> 12 & 63), uint6ToB64(nUint24 >>> 6 & 63), uint6ToB64(nUint24 & 63));
      nUint24 = 0;
    }
  }
  return sB64Enc.substr(0, sB64Enc.length - 2 + nMod3) + (nMod3 === 2 ? '' : nMod3 === 1 ? '=' : '==');
}


// from https://stackoverflow.com/questions/40314257/export-webcrypto-key-to-pem-format
// Micah Henning
async function encode_public_key (pub_key) {
  const spki = await window.crypto.subtle.exportKey('spki', pub_key);
  let text = window.btoa(String.fromCharCode(...new Uint8Array(spki)));
  text = text.match(/.{1,64}/g).join('\n');
  return `-----BEGIN PUBLIC KEY-----\n${text}\n-----END PUBLIC KEY-----`;
}

/*
 * The "origin" is the triple SCHEME://HOST:PORT, and each origin gets
 * a set of indexedDB databases.  
 */
const database_name = 'wcauth'; // the name of our database
const object_store_name = 'key'; // we use a single object_store with this name
const row_id = 1; // we store a single value with this id

/* the value we store is [public_key, private_key] */

function db_interface() {
  let self = this;
  self.db = null;

  self.open = function() {
    return new Promise (function (fulfill, reject) {
      // the second argument to open is the version of the database.
      // fancy programs can use it to do database schema updates.
      // the current value is just an accident of development
      let req = indexedDB.open(database_name, 4);
      req.onsuccess = function (evt) {
	self.db = evt.target.result;
	fulfill (self);
      }
      req.onupgradeneeded = function(evt) {
	self.db = evt.target.result;
	if (! self.db.objectStoreNames.contains(object_store_name)) {
	  self.db.createObjectStore(object_store_name);
	}
      }
    });
  }

  self.get_key = function () {
    return new Promise(function (fulfill, reject) {
      var trans = self.db.transaction([object_store_name], "readonly");
      var object_store = trans.objectStore(object_store_name);
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
      var trans = self.db.transaction([object_store_name], "readwrite");
      trans.oncomplete = function(evt) {fulfill(item);};
      var objectStore = trans.objectStore(object_store_name);
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

async function wcauth_login (server_nonce_b64) {
  let key_pair = await get_key_pair ();
  let public_key = key_pair[0];
  let private_key = key_pair[1];

  let browser_nonce_bytes = new Uint8Array(8);
  window.crypto.getRandomValues(browser_nonce_bytes);

  let server_nonce_bytes = base64DecToArr(server_nonce_b64);

  // message is the contatenation of browser_nonce_bytes and server_nonce_bytes
  let message = new Uint8Array(browser_nonce_bytes.length 
			       + server_nonce_bytes.length)
  message.set (browser_nonce_bytes);
  message.set (server_nonce_bytes, browser_nonce_bytes.length);

  let sig = await window.crypto.subtle.sign(
    {name:"RSASSA-PKCS1-v1_5"}, 
    private_key, 
    message
  );

  let sig_b64 = base64EncArr(new Uint8Array(sig));
  let browser_nonce_b64 = base64EncArr(browser_nonce_bytes);
  let pub = await encode_public_key(public_key);

  let dest = window.location.pathname +
      "?sig=" + encodeURIComponent(sig_b64) +
      "&browser_nonce=" + encodeURIComponent(browser_nonce_b64) +
      "&pub=" + encodeURIComponent(pub);

  if (debug) {
    console.log(dest);
  } else {
    // browser side redirect to dest
    window.location = dest;
  }
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
    if (debug) {
      console.log ('deleted');
    } else {
      // browser side redirect to site root
      window.location = '/';
    }
  }

});

