FROM node:20-alpine AS build
WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm install --production=false \
    && chmod +x node_modules/.bin/vite

COPY . .

# Allow overriding API base URL at build time so the generated bundle points at the proxy
ARG VITE_API_URL
ENV VITE_API_URL=${VITE_API_URL}

RUN npm run build

# Final stage: serve the app
FROM build AS serve
WORKDIR /app
RUN npm install -g serve

EXPOSE 3000
CMD ["serve", "-s", "dist", "-l", "3000", "--single"]

FROM build AS development_build
WORKDIR /app

EXPOSE 3000
CMD ["npm", "run", "dev"]
