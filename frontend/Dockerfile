FROM node:20-alpine AS build
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --include=dev

COPY . .

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
