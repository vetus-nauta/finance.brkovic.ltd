const fs = require('fs');
const path = require('path');
const dns = require('dns').promises;
const tls = require('tls');
const { MongoClient } = require('mongodb');

const ROOT = path.resolve(__dirname, '..');
const URI_FILE = process.env.FINDESK_MONGO_URI_FILE || path.join(ROOT, 'storage', 'secrets', 'mongodb_uri');

function readMongoUri() {
  if (process.env.FINDESK_MONGO_URI) return process.env.FINDESK_MONGO_URI.trim();
  return fs.readFileSync(URI_FILE, 'utf8').trim();
}

function maskedUriInfo(uri) {
  const masked = uri.replace(/^(mongodb(?:\+srv)?:\/\/)([^:@/]+)(?::([^@/]*))?@/i, '$1<user>:<password>@');
  const hostMatch = masked.match(/^mongodb(?:\+srv)?:\/\/[^@]+@([^/?]+)/i) || masked.match(/^mongodb(?:\+srv)?:\/\/([^/?]+)/i);
  const dbMatch = masked.match(/\/([^/?]+)(?:\?|$)/);
  const params = masked.includes('?') ? masked.split('?').pop().split('&').map((part) => part.split('=')[0]).filter(Boolean) : [];
  return {
    scheme: uri.startsWith('mongodb+srv://') ? 'mongodb+srv' : uri.startsWith('mongodb://') ? 'mongodb' : 'unknown',
    host: hostMatch ? hostMatch[1] : '',
    db: dbMatch ? dbMatch[1] : '',
    param_keys: params,
  };
}

function tlsProbe(target, port) {
  return new Promise((resolve) => {
    const started = Date.now();
    const socket = tls.connect({
      host: target,
      port,
      servername: target,
      timeout: 7000,
    }, () => {
      const result = {
        target,
        port,
        ok: true,
        authorized: socket.authorized,
        protocol: socket.getProtocol(),
        ms: Date.now() - started,
      };
      socket.end();
      resolve(result);
    });
    socket.on('timeout', () => {
      socket.destroy();
      resolve({ target, port, ok: false, error: 'timeout', ms: Date.now() - started });
    });
    socket.on('error', (error) => {
      resolve({
        target,
        port,
        ok: false,
        code: error.code,
        error: String(error.message || '').split('\n')[0],
        ms: Date.now() - started,
      });
    });
  });
}

async function main() {
  const uri = readMongoUri();
  const info = maskedUriInfo(uri);
  const result = {
    checked_at: new Date().toISOString(),
    uri: info,
    diagnosis: null,
    srv: [],
    tls: [],
    mongo_ping: null,
  };

  if (info.scheme === 'mongodb+srv' && info.host) {
    try {
      result.srv = await dns.resolveSrv(`_mongodb._tcp.${info.host}`);
    } catch (error) {
      result.diagnosis = {
        layer: 'dns',
        code: error.code || error.name,
        message: `Atlas SRV record was not found for ${info.host}. Update the MongoDB Atlas connection string in ${URI_FILE} or FINDESK_MONGO_URI.`,
      };
      console.log(JSON.stringify(result, null, 2));
      process.exit(1);
    }
    for (const record of result.srv) {
      result.tls.push(await tlsProbe(record.name, record.port));
    }
  }

  const started = Date.now();
  const client = new MongoClient(uri, {
    serverSelectionTimeoutMS: 8000,
    connectTimeoutMS: 8000,
  });
  try {
    await client.connect();
    const ping = await client.db('admin').command({ ping: 1 });
    result.mongo_ping = { ok: true, ping: ping.ok, ms: Date.now() - started };
  } catch (error) {
    result.mongo_ping = {
      ok: false,
      name: error.name,
      message: String(error.message || '').split('\n')[0],
      ms: Date.now() - started,
    };
  } finally {
    await client.close().catch(() => {});
  }

  console.log(JSON.stringify(result, null, 2));
  const tlsOk = !result.tls.length || result.tls.every((item) => item.ok);
  if (!tlsOk || !result.mongo_ping || !result.mongo_ping.ok) process.exit(1);
}

main().catch((error) => {
  console.error(JSON.stringify({
    ok: false,
    name: error.name,
    message: String(error.message || '').split('\n')[0],
  }, null, 2));
  process.exit(1);
});
