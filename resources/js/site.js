import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import intersect from '@alpinejs/intersect';
import 'lite-youtube-embed';
import 'lite-youtube-embed/src/lite-yt-embed.css';

Alpine.plugin(collapse);
Alpine.plugin(intersect);

Alpine.data('galleryApp', () => ({
    activeTab: '',
    loadedTabs: [],
    albums: {},
    lightbox: { open: false, src: '', alt: '', photos: [], index: 0, trigger: null },

    init() {
        const firstBtn = this.$el.querySelector('button[data-slug]');
        if (firstBtn) this.switchTab(firstBtn.dataset.slug);
    },

    switchTab(slug) {
        if (!this.loadedTabs.includes(slug)) {
            this.loadedTabs.push(slug);
        }
        this.activeTab = slug;
        this.$nextTick(() => this.scanPanel(slug));
    },

    scanPanel(slug) {
        if (this.albums[slug]) return;
        const panel = this.$el.querySelector(`[data-album-id="${slug}"]`);
        if (!panel) return;
        this.albums[slug] = Array.from(panel.querySelectorAll('img')).map(img => ({
            src: img.dataset.fullSrc || img.src,
            alt: img.alt || ''
        }));
    },

    openFromEl(event) {
        const btn = event.currentTarget;
        const panel = btn.closest('[data-album-id]');
        const albumId = panel.dataset.albumId;
        const buttons = Array.from(panel.querySelectorAll('button'));
        this.lightbox.trigger = btn;
        this.openLightbox(albumId, buttons.indexOf(btn));
    },

    openLightbox(albumId, photoIndex) {
        const photos = this.albums[albumId] || [];
        if (photos.length === 0) return;
        this.lightbox.photos = photos;
        this.lightbox.index = photoIndex;
        this.lightbox.src = photos[photoIndex].src;
        this.lightbox.alt = photos[photoIndex].alt;
        this.lightbox.open = true;
        document.body.style.overflow = 'hidden';
        this.$nextTick(() => this.$refs.lightboxClose?.focus());
    },

    closeLightbox() {
        this.lightbox.open = false;
        document.body.style.overflow = '';
        this.lightbox.trigger?.focus();
    },

    navigate(dir) {
        const len = this.lightbox.photos.length;
        if (len === 0) return;
        this.lightbox.index = (this.lightbox.index + dir + len) % len;
        this.lightbox.src = this.lightbox.photos[this.lightbox.index].src;
        this.lightbox.alt = this.lightbox.photos[this.lightbox.index].alt;
    },

    trapFocus(e) {
        const overlay = this.$el.querySelector('[role="dialog"]');
        if (!overlay) return;
        const focusable = overlay.querySelectorAll('button');
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
}));

window.Alpine = Alpine;
Alpine.start();

const recaptchaMeta = document.querySelector('meta[name="recaptcha-site-key"]');
if (recaptchaMeta) {
    const siteKey = recaptchaMeta.content;
    document.querySelectorAll('form').forEach(form => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'g-recaptcha-response';
        form.appendChild(input);

        form.addEventListener('submit', e => {
            if (input.value) return;
            e.preventDefault();
            window.grecaptcha.ready(() => {
                window.grecaptcha.execute(siteKey, { action: 'submit' }).then(token => {
                    input.value = token;
                    form.submit();
                });
            });
        });
    });
}
