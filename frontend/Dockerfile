FROM node:22-alpine

WORKDIR /app

RUN npm install --global pnpm@12.11.2

COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN pnpm install --frozen-lockfile

COPY . .

RUN chown -R node:node /app

USER node

EXPOSE 5173

HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 CMD wget -q -O /dev/null http://127.0.0.1:5173/ || exit 1

CMD ["pnpm", "dev", "--host", "0.0.0.0"]
