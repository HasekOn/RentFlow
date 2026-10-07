import {defineConfig, loadEnv} from 'vite'
import react from '@vitejs/plugin-react-swc'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig(({mode}) => {
    const env = loadEnv(mode, process.cwd(), '')

    return {
        plugins: [react(), tailwindcss()],
        build: {
            rolldownOptions: {
                output: {
                    codeSplitting: {
                        groups: [
                            {
                                name: 'vendor',
                                test: /[\\/]node_modules[\\/](react|react-dom|react-router|react-router-dom)[\\/]/,
                            },
                            {
                                name: 'charts',
                                test: /[\\/]node_modules[\\/]recharts[\\/]/,
                            },
                        ],
                    },
                },
            },
        },
        server: {
            proxy: {
                '/api': {
                    // Backend URL: Herd (http://rentflow.test) or `php artisan serve` (default)
                    target: env.API_URL || 'http://localhost:8000',
                    changeOrigin: true,
                },
            },
        },
    }
})
