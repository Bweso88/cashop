# Image commune aux deux applications Next.js (web et admin).
ARG APP_DIR=web

FROM node:22-alpine AS base
WORKDIR /app
ENV NEXT_TELEMETRY_DISABLED=1

FROM base AS dev
ARG APP_DIR
COPY ${APP_DIR}/package*.json ./
RUN npm ci
CMD ["npm", "run", "dev", "--", "-H", "0.0.0.0", "-p", "3000"]

FROM base AS build
ARG APP_DIR
COPY ${APP_DIR}/package*.json ./
RUN npm ci
COPY ${APP_DIR}/ ./
RUN npm run build

# Sortie "standalone" de Next.js : image minimale, utilisateur non-root.
FROM node:22-alpine AS prod
WORKDIR /app
ENV NODE_ENV=production NEXT_TELEMETRY_DISABLED=1
COPY --from=build /app/.next/standalone ./
COPY --from=build /app/.next/static ./.next/static
COPY --from=build /app/public ./public
USER node
EXPOSE 3000
CMD ["node", "server.js"]
