let isLoggedIn = false;
let isLoggingIn = false;

export default {
    // Omezíme to na 1 vlákno, aby se nepřihlašovalo více robotů najednou a neprali se
    puppeteerClusterOptions: {
        maxConcurrency: 1
    },
    hooks: {
        'puppeteer:before-goto': async (page) => {
            if (!isLoggedIn && !isLoggingIn) {
                isLoggingIn = true;
                try {
                    console.log('Jdu na login...');
                    await page.goto('http://localhost:5173/login');
                    await new Promise(r => setTimeout(r, 1000)); // Chvíli počkáme na render

                    // Přidáno { delay: 50 } - robot bude psát pomaleji, aby to React (onChange) stihl chytit
                    await page.type('input[type="email"]', 'landlord@rentflow.cz', {delay: 50});
                    await page.type('input[type="password"]', 'password', {delay: 50});

                    await page.click('button[type="submit"]');

                    console.log('Kliknuto na Submit, čekám...');

                    // Čekáme na změnu URL
                    await page.waitForFunction(() => window.location.pathname === '/', {timeout: 10000});

                    console.log('Úspěšně přihlášeno!');
                    isLoggedIn = true;
                } catch (error) {
                    console.error('Přihlášení selhalo (nepřesměrovalo se to). Běží backend?');
                } finally {
                    isLoggingIn = false;
                }
            }
        }
    }
}
