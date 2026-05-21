import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import intersect from '@alpinejs/intersect';
import 'lite-youtube-embed';
import 'lite-youtube-embed/src/lite-yt-embed.css';

Alpine.plugin(collapse);
Alpine.plugin(intersect);

window.Alpine = Alpine;
Alpine.start();
