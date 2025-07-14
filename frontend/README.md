# React + Vite

This template provides a minimal setup to get React working in Vite with HMR and some ESLint rules.

Currently, two official plugins are available:

- [@vitejs/plugin-react](https://github.com/vitejs/vite-plugin-react/blob/main/packages/plugin-react) uses [Babel](https://babeljs.io/) for Fast Refresh
- [@vitejs/plugin-react-swc](https://github.com/vitejs/vite-plugin-react/blob/main/packages/plugin-react-swc) uses [SWC](https://swc.rs/) for Fast Refresh

## Expanding the ESLint configuration

If you are developing a production application, we recommend using TypeScript with type-aware lint rules enabled. Check out the [TS template](https://github.com/vitejs/vite/tree/main/packages/create-vite/template-react-ts) for information on how to integrate TypeScript and [`typescript-eslint`](https://typescript-eslint.io) in your project.

## Running the development server

From this directory install dependencies and start Vite:

```bash
npm install
npm run dev
```

The app will be available at <http://localhost:5173/>.

## Using Docker Compose

The root `docker-compose.yml` includes a `web` service that builds the React app
and serves the production build on port `3000`.

```bash
docker-compose up web
```

For local development you can use the provided `docker-compose.override.yml`
which mounts the source and runs the Vite dev server. This lets you edit files
and immediately see changes without rebuilding the image:

```bash
docker-compose up web
```

When the override file is present the frontend will be available at
<http://localhost:3000/>.

You can override the `VITE_API_URL` environment variable in the compose file to
point the frontend to a different backend API.

## Building for production

To create static files under `dist/` run:

```bash
npm run build
```