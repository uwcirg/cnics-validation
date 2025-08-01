# Development Quick Start

These steps start the frontend and backend using Docker Compose.

1. Copy `.env.example` to `.env`.
2. Docker Compose reads variables from `.env` automatically. Run `docker-compose up --build` to build images and start containers.
3. Open <https://frontend.cnics-validation.pm.ssingh20.dev.cirg.uw.edu/> in your browser. The backend API will be
   available at <https://backend.cnics-validation.pm.ssingh20.dev.cirg.uw.edu/>.

You can stop the containers with `Ctrl+C` or by running `docker-compose down`.
