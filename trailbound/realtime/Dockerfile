FROM node:24-alpine

WORKDIR /realtime
COPY package.json package-lock.json ./
RUN npm ci --omit=dev
COPY server.mjs ./

CMD ["node", "server.mjs"]
