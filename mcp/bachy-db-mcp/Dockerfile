FROM node:24-alpine

WORKDIR /app

COPY mcp/bachy-db-mcp/package*.json ./
RUN npm ci --omit=dev

COPY mcp/bachy-db-mcp/src ./src
COPY mcp/bachy-db-mcp/tests ./tests
COPY mcp/bachy-db-mcp/.env.example ./.env.example
COPY mcp/bachy-db-mcp/README.md ./README.md

EXPOSE 3101

CMD ["npm", "start"]