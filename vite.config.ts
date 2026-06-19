import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import laravel from 'laravel-vite-plugin'
import tailwindcss from '@tailwindcss/vite'
import { resolve } from 'path'

export default defineConfig({
  plugins: [
    tailwindcss(),
    laravel({
      input: ['resources/js/main.ts', 'resources/css/app.css'],
      refresh: true,
    }),
    vue({
      template: {
        transformAssetUrls: {
          base: null,
          includeAbsolute: false,
        },
      },
    }),
  ],
  resolve: {
    alias: {
      '@': resolve(__dirname, 'resources/js'),
    },
  },
  build: {
    rollupOptions: {
      output: {
        manualChunks: {
          'vue-vendor':    ['vue', 'vue-router', 'pinia'],
          'primevue':      ['primevue', '@primeuix/themes'],
          'form-vendor':   ['vee-validate', 'yup'],
          'map-vendor':    ['leaflet'],
          'utils-vendor':  ['axios', '@vueuse/core'],
        },
      },
    },
  },
})
