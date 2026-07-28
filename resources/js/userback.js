/**
 * Lazy load Userback widget to improve initial page load performance.
 * Only loads when user interacts with the page or after a delay.
 */
function initUserback() {
    const userbackDataElement = document.querySelector('[data-userback-data]');
    if (!userbackDataElement) {
        return;
    }

    try {
        const config = JSON.parse(userbackDataElement.textContent || '{}');
        const { token, url, userData } = config;

        if (!token || !url) {
            return;
        }

        window.Userback = window.Userback || {};
        window.Userback.access_token = token;

        if (userData && Object.keys(userData).length > 0) {
            window.Userback.user_data = userData;
        }

        const script = document.createElement('script');
        script.async = true;
        script.src = url;
        script.onload = () => {};
        script.onerror = () => {
            console.warn('Failed to load Userback widget script');
        };
        (document.head || document.body).appendChild(script);
    } catch (error) {
        console.warn('Failed to initialize Userback widget', error);
    }
}

let userbackInitialized = false;

function loadUserback() {
    if (userbackInitialized) return;
    userbackInitialized = true;
    initUserback();
}

['click', 'scroll', 'touchstart', 'keydown'].forEach((event) => {
    document.addEventListener(event, loadUserback, { once: true, passive: true });
});

setTimeout(loadUserback, 3000);
