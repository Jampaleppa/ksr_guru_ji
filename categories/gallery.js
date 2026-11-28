/**
 * categories/gallery.js
 * Shared multi-image card switcher helper for KSR GURU JI category pages.
 */
(function (window) {
    'use strict';

    window.ksrEscHtml = function (str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    };

    window.ksrGalleryMediaHtml = function (product, options) {
        var opts = options || {};
        var fallbackEmoji = opts.emoji || product.emoji || '🛒';
        var alt = window.ksrEscHtml(opts.alt || product.nameEn || product.name || '');
        var primary = opts.primary || product.image || product.image_url || product.imageUrl || product.imageurl || '';
        var imgs = (Array.isArray(product.images) && product.images.length) ? product.images : (primary ? [primary] : []);

        if (imgs.length === 0) {
            return '<span class="emoji-fallback">' + window.ksrEscHtml(fallbackEmoji) + '</span>';
        }

        if (imgs.length === 1) {
            var singleSrc = window.ksrEscHtml(imgs[0]);
            var singleOnErr = opts.onError || ("this.style.display='none';this.parentElement.innerHTML='" + window.ksrEscHtml(fallbackEmoji) + "'");
            var cls = opts.imgClass ? ' class="' + window.ksrEscHtml(opts.imgClass) + '"' : '';
            return '<img' + cls + ' src="' + singleSrc + '" alt="' + alt + '" loading="lazy" onerror="' + singleOnErr + '">';
        }

        var encodedList = window.ksrEscHtml(JSON.stringify(imgs));
        var thumbsHtml = imgs.map(function (src, idx) {
            return '<img src="' + window.ksrEscHtml(src) + '" alt="" loading="lazy" class="ksr-gal-thumb' + (idx === 0 ? ' active' : '') + '" onclick="window.ksrSelectImg(this,' + idx + ',event)" onerror="this.style.display=\'none\'">';
        }).join('');

        return '<div class="ksr-gallery" data-imgs="' + encodedList + '" data-idx="0">' +
            '<div class="ksr-gal-stage">' +
                '<img class="ksr-gallery-main" src="' + window.ksrEscHtml(imgs[0]) + '" alt="' + alt + '" loading="lazy" onerror="this.onerror=null;this.style.opacity=\'0.3\'">' +
                '<button type="button" class="ksr-gal-arrow prev" aria-label="Previous image" onclick="window.ksrStepImg(this,-1,event)">&#8249;</button>' +
                '<button type="button" class="ksr-gal-arrow next" aria-label="Next image" onclick="window.ksrStepImg(this,1,event)">&#8250;</button>' +
                '<span class="ksr-gal-badge">1/' + imgs.length + '</span>' +
            '</div>' +
            '<div class="ksr-gal-thumbs">' + thumbsHtml + '</div>' +
        '</div>';
    };

    function applyGalleryIndex(gallery, nextIdx) {
        if (!gallery) return;
        var raw = gallery.getAttribute('data-imgs');
        if (!raw) return;
        var imgs;
        try {
            imgs = JSON.parse(raw);
        } catch (err) {
            return;
        }
        if (!Array.isArray(imgs) || imgs.length === 0) return;

        var total = imgs.length;
        var idx = ((nextIdx % total) + total) % total;
        gallery.setAttribute('data-idx', String(idx));

        var mainImg = gallery.querySelector('.ksr-gallery-main');
        if (mainImg) {
            mainImg.src = imgs[idx];
        }
        var badge = gallery.querySelector('.ksr-gal-badge');
        if (badge) {
            badge.textContent = (idx + 1) + '/' + total;
        }
        var thumbs = gallery.querySelectorAll('.ksr-gal-thumb');
        thumbs.forEach(function (t, i) {
            t.classList.toggle('active', i === idx);
        });
    }

    window.ksrSelectImg = function (el, idx, ev) {
        if (ev) {
            ev.preventDefault();
            ev.stopPropagation();
        }
        var gallery = el && el.closest ? el.closest('.ksr-gallery') : null;
        applyGalleryIndex(gallery, idx);
    };

    window.ksrStepImg = function (btn, delta, ev) {
        if (ev) {
            ev.preventDefault();
            ev.stopPropagation();
        }
        var gallery = btn && btn.closest ? btn.closest('.ksr-gallery') : null;
        if (!gallery) return;
        var cur = parseInt(gallery.getAttribute('data-idx') || '0', 10) || 0;
        applyGalleryIndex(gallery, cur + delta);
    };
})(window);
