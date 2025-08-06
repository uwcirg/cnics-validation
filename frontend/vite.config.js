import react from '@vitejs/plugin-react';
import { defineConfig, loadEnv } from 'vite';

export default defineConfig(({ mode }) => {
  // Load env file based on `mode` in the current working directory (handle vite development server env overrides).
	// Set the third parameter to '' to load all env regardless of the `VITE_` prefix.
	process.env = {...process.env, ...loadEnv(mode, process.cwd(), '')};
  return {
    base: '/',
    plugins: [react()],
    server: {
      watch: process.env.HOT_RELOAD ? { usePolling: true } : null,
      host: '0.0.0.0',
      // Limit hosts that can access the server to configured domain or localhost
      allowedHosts: [process.env.SERVER_NAME ?? 'localhost'],
      // Allow local (non-containerized) development deployment to use a different port
      port: process.env.DEV_SERVER_PORT ? process.env.DEV_SERVER_PORT : 3000,
      strictPort: true,
    },
    build: {
      // Use sourcemap for debugging `vite build` output
      sourcemap: process.env.DEBUG ?? false
    }
  };
});
