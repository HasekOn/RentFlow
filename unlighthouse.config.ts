let isLoggedIn = false;
let isLoggingIn = false;

export default {
    puppeteerClusterOptions: {
        maxConcurrency: 1
    },
    urls: [
        '/login',
        '/register',
        '/',
        '/properties',
        '/leases',
        '/payments',
        '/tickets',
        '/people',
        '/documents',
        '/settings',
    ],
    scanner: {
        crawler: false,
    },
    hooks: {
        'puppeteer:before-goto': async (page) => {
            if (!isLoggedIn && !isLoggingIn) {
                isLoggingIn = true;
                try {
                    console.log('Jdu na login...');
                    await page.goto('http://localhost:5173/login');
                    await new Promise(r => setTimeout(r, 1000));

                    await page.type('input[type="email"]', 'landlord@rentflow.cz', {delay: 50});
                    await page.type('input[type="password"]', 'password', {delay: 50});

                    await page.click('button[type="submit"]');

                    console.log('Kliknuto na Submit, čekám...');

                    await page.waitForFunction(() => window.location.pathname === '/', {timeout: 10000});

                    console.log('Úspěšně přihlášeno!');
                    isLoggedIn = true;
                } catch (error) {
                    console.error('Přihlášení selhalo. Běží backend?');
                } finally {
                    isLoggingIn = false;
                }
            }
        }
    }
}
