import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

Alpine.plugin(collapse);

document.addEventListener('alpine:init', () => {
    Alpine.data('authHeroHq', (hqUrl) => ({
        loaded: false,
        init() {
            const img = new Image();
            img.onload = () => {
                this.loaded = true;
            };
            img.src = hqUrl;
        },
    }));
});

window.Alpine = Alpine;

Alpine.start();

import.meta.glob([
    '../images/**',
    '../fonts/**',
], { eager: true, query: '?url', import: 'default' });
