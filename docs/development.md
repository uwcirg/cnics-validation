# Development Quick Start

These steps start the frontend and backend using Docker Compose.

1. Copy `.env.example` to `.env`.
2. Docker Compose reads variables from `.env` automatically. Run `docker-compose up --build` to build images and start containers.
3. Open <http://localhost:3000/> in your browser. The backend API will be
   available at <http://localhost:3001/>.

You can stop the containers with `Ctrl+C` or by running `docker-compose down`.
