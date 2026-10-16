import { defineConfig } from '@playwright/test';

const database = '/tmp/klartfoto-e2e.sqlite';

const env = {
    APP_ENV: 'local',
    APP_URL: 'http://127.0.0.1:8765',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: database,
    DB_URL: '',
    SESSION_DRIVER: 'file',
    SESSION_SECURE_COOKIE: 'false',
    CACHE_STORE: 'file',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'log',
    BROADCAST_CONNECTION: 'log',
};

export default defineConfig({
    testDir: './tests/Browser',
    outputDir: '/tmp/klartfoto-playwright',
    timeout: 60_000,
    workers: 1,
    fullyParallel: false,
    reporter: 'line',
    use: {
        baseURL: 'http://127.0.0.1:8765',
        headless: true,
    },
    webServer: {
        command: 'php tests/Browser/prepare.php && php artisan serve --host=127.0.0.1 --port=8765',
        url: 'http://127.0.0.1:8765/up',
        reuseExistingServer: false,
        timeout: 120_000,
        env,
    },
});
