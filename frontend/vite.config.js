import react from '@vitejs/plugin-react';
import { defineConfig, loadEnv } from 'vite';

export default defineConfig(({ mode }) => {
  // Load env file based on `mode` in the current working directory (handle vite development server env overrides).
	// Set the third parameter to '' to load all env regardless of the `VITE_` prefix.
	process.env = {...process.env, ...loadEnv(mode, process.cwd(), '')};
  // Configure HMR to work when accessing the dev server via a reverse proxy/HTTPS domain
  const configuredHmrHost = process.env.HMR_HOST || process.env.SERVER_NAME;
  const isExternalHost = Boolean(
    configuredHmrHost && !['localhost', '127.0.0.1'].includes(configuredHmrHost)
  );
  const hmrProtocol = process.env.HMR_PROTOCOL || (isExternalHost ? 'wss' : 'ws');
  const inferredClientPort = isExternalHost ? 443 : undefined;
  const hmrClientPort = process.env.HMR_CLIENT_PORT
    ? Number(process.env.HMR_CLIENT_PORT)
    : inferredClientPort;
  const serverOrigin = process.env.VITE_DEV_ORIGIN
    || (configuredHmrHost ? `${hmrProtocol === 'wss' ? 'https' : 'http'}://${configuredHmrHost}` : undefined);

  return {
    base: '/',
    plugins: [react()],
    server: {
      watch: process.env.HOT_RELOAD ? { usePolling: true } : null,
      host: '0.0.0.0',
      // Limit hosts that can access the server to configured domain(s) or localhost
      allowedHosts: [process.env.SERVER_NAME, process.env.HMR_HOST, 'localhost'].filter(Boolean),
      // Allow local (non-containerized) development deployment to use a different port
      port: process.env.DEV_SERVER_PORT ? process.env.DEV_SERVER_PORT : 3000,
      strictPort: true,
      // Ensure asset URLs and websocket client resolve correctly behind a proxy
      ...(serverOrigin ? { origin: serverOrigin } : {}),
      hmr: {
        host: configuredHmrHost || 'localhost',
        protocol: hmrProtocol,
        ...(hmrClientPort ? { clientPort: hmrClientPort } : {}),
      },
    },
    build: {
      // Use sourcemap for debugging `vite build` output
      sourcemap: process.env.DEBUG ?? false
    }
  };
});
