FROM node:20-alpine AS build
WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm install --production=false \
    && chmod +x node_modules/.bin/vite

COPY . .

# Set base path for Vite build
ARG VITE_BASE_PATH=/validation-ssingh20/
ENV VITE_BASE_PATH=${VITE_BASE_PATH}
ENV VITE_API_URL=${VITE_API_URL}

# Pass VITE_ variables explicitly to the build
RUN VITE_BASE_PATH=$VITE_BASE_PATH VITE_API_URL=$VITE_API_URL npm run build

# Final stage: serve the app
FROM node:20-alpine
WORKDIR /app
RUN npm install -g serve

COPY --from=build /app/dist ./dist

EXPOSE 3000
CMD ["serve", "-s", "dist", "-l", "3000", "--single"]

FROM build AS development_build
WORKDIR /app

EXPOSE 3000
CMD ["npm", "run", "dev"]
