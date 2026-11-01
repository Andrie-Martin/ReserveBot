# ReserveBot web app image. Built automatically by `docker compose up --build`.
FROM node:22-alpine

WORKDIR /app

# Install packages first, so this step is reused until package.json changes
COPY package.json package-lock.json ./
RUN npm ci --omit=dev

# Then copy the app code
COPY . .

# Don't run as the root user inside the container
USER node

EXPOSE 3000
CMD ["node", "app.js"]
