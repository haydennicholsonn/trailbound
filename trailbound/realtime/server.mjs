import Redis from 'ioredis';
import { WebSocketServer } from 'ws';

const port = Number(process.env.REALTIME_PORT || 8080);
const redisHost = process.env.REDIS_HOST || 'redis';
const redisPort = Number(process.env.REDIS_PORT || 6379);
const redisPassword = process.env.REDIS_PASSWORD || process.env.REDIS_PASSWORD_VALUE || undefined;
const channel = process.env.REALTIME_CHANNEL || 'trailbound:realtime';

const redis = new Redis({ host: redisHost, port: redisPort, password: redisPassword, lazyConnect: false });
const wss = new WebSocketServer({ port });

const send = (ws, payload) => {
  if (ws.readyState === ws.OPEN) ws.send(JSON.stringify(payload));
};

wss.on('connection', (ws) => {
  send(ws, { type: 'connected', at: new Date().toISOString() });
  ws.isAlive = true;
  ws.on('pong', () => { ws.isAlive = true; });
});

setInterval(() => {
  for (const ws of wss.clients) {
    if (!ws.isAlive) {
      ws.terminate();
      continue;
    }
    ws.isAlive = false;
    ws.ping();
  }
}, 30000);

redis.subscribe(channel);
redis.on('message', (_channel, message) => {
  let payload;
  try {
    payload = JSON.parse(message);
  } catch {
    payload = { type: 'refresh', at: new Date().toISOString() };
  }
  for (const ws of wss.clients) send(ws, payload);
});

console.log(`Trailbound realtime listening on :${port} and Redis channel ${channel}`);
