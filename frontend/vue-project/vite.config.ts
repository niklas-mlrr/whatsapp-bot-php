import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
// import vueDevTools from 'vite-plugin-vue-devtools' // <-- comment out

// https://vite.dev/config/
export default defineConfig(({ command, mode }) => ({
  define: {
    'process.env': process.env,
  },
  plugins: [
    vue(),
    // vueDevTools(), 
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    },
  },
  server: {
    proxy: {
      '/api': 'http://localhost:8000',
      '/storage': 'http://localhost:8000',
      '/broadcasting': 'http://localhost:8000',
      '/ws': {
        target: 'ws://localhost:6001',
        ws: true,
      },
    }
  }
}))
