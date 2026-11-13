/* FurniCraft — storefront behaviour. Bootstrap is loaded globally before this file. */
(function () {
    'use strict';

    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    function money(value) {
        return value;
    }

    /* ------------------------------------------------------- wishlist */
    function initWishlist() {
        document.querySelectorAll('[data-wishlist]').forEach(function (button) {
            button.addEventListener('click', function () {
                // Visual only until Phase 4 builds the wishlist module.
                var active = button.classList.toggle('is-active');
                var icon = button.querySelector('i');
                if (icon) {
                    icon.className = active ? 'bi bi-heart-fill' : 'bi bi-heart';
                }
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        });
    }

    /* -------------------------------------------------------- gallery */
    function initGallery() {
        var gallery = document.querySelector('[data-gallery]');
        if (!gallery) { return; }

        var main = gallery.querySelector('#galleryMain');
        var stage = gallery.querySelector('.fc-gallery__stage');

        gallery.querySelectorAll('.fc-gallery__thumb').forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                gallery.querySelectorAll('.fc-gallery__thumb').forEach(function (t) {
                    t.classList.remove('is-active');
                });
                thumb.classList.add('is-active');
                main.src = thumb.dataset.full;
                main.alt = thumb.dataset.alt || '';
                main.style.transformOrigin = 'center center';
            });
        });

        // Hover zoom: move the transform origin to the cursor and scale up.
        if (stage && main && window.matchMedia('(hover: hover)').matches) {
            stage.addEventListener('mousemove', function (event) {
                var rect = stage.getBoundingClientRect();
                var x = ((event.clientX - rect.left) / rect.width) * 100;
                var y = ((event.clientY - rect.top) / rect.height) * 100;
                main.style.transformOrigin = x + '% ' + y + '%';
            });

            stage.addEventListener('mouseenter', function () { stage.classList.add('is-zoomed'); });
            stage.addEventListener('mouseleave', function () {
                stage.classList.remove('is-zoomed');
                main.style.transformOrigin = 'center center';
            });
        }
    }

    /* ------------------------------------------------------- variants */
    function initVariants() {
        var wrap = document.querySelector('[data-variants]');
        if (!wrap) { return; }

        var variants;
        try {
            variants = JSON.parse(wrap.dataset.variants);
        } catch (e) {
            return;
        }

        var priceNow = document.querySelector('[data-price-now]');
        var stockLine = document.querySelector('[data-stock-line]');
        var skuLine = wrap.querySelector('[data-variant-sku]');

        function select(variant, button) {
            wrap.querySelectorAll('.fc-variant').forEach(function (b) { b.classList.remove('is-active'); });
            if (button) { button.classList.add('is-active'); }

            if (priceNow) { priceNow.textContent = money(variant.priceLabel); }
            if (skuLine) { skuLine.textContent = 'SKU ' + variant.sku; }

            if (stockLine) {
                stockLine.innerHTML = variant.stock > 0
                    ? '<i class="bi bi-check-circle-fill text-success"></i><span>In stock — ' + variant.stock + ' available</span>'
                    : '<i class="bi bi-x-circle-fill text-danger"></i><span>This option is out of stock</span>';
            }
        }

        wrap.querySelectorAll('.fc-variant').forEach(function (button) {
            button.addEventListener('click', function () {
                var id = Number(button.dataset.variantId);
                var variant = variants.filter(function (v) { return v.id === id; })[0];
                if (variant) { select(variant, button); }
            });
        });

        // Reflect the pre-selected first option on load.
        var first = wrap.querySelector('.fc-variant.is-active');
        if (first) {
            var id = Number(first.dataset.variantId);
            var variant = variants.filter(function (v) { return v.id === id; })[0];
            if (variant) { select(variant, first); }
        }
    }

    /* -------------------------------------------------- pincode check */
    function initPincode() {
        var box = document.querySelector('[data-pincode-check]');
        if (!box) { return; }

        var input = box.querySelector('[data-pincode-input]');
        var button = box.querySelector('[data-pincode-submit]');
        var result = box.querySelector('[data-pincode-result]');

        function render(data) {
            var cls = data.serviceable ? 'is-ok' : (data.found ? 'is-no' : 'is-unknown');
            var icon = data.serviceable ? 'bi-truck' : (data.found ? 'bi-x-circle' : 'bi-question-circle');

            var html = '<span class="' + cls + '"><i class="bi ' + icon + '"></i> ' + data.message + '</span>';

            if (data.serviceable) {
                html += '<small>Estimated delivery by <strong>' + data.eta + '</strong>'
                    + (data.cod ? ' · Cash on delivery available' : ' · Prepaid only')
                    + '</small>';
            }

            if (data.note) {
                html += '<small>' + data.note + '</small>';
            }

            result.innerHTML = html;
        }

        function check() {
            var value = (input.value || '').trim();

            if (!value) {
                result.innerHTML = '<span class="is-no">Please enter your pincode.</span>';
                return;
            }

            button.disabled = true;
            result.innerHTML = '<span class="is-unknown">Checking…</span>';

            fetch(box.dataset.url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ pincode: value })
            })
                .then(function (r) { return r.json(); })
                .then(render)
                .catch(function () {
                    result.innerHTML = '<span class="is-no">Could not check right now. Please try again.</span>';
                })
                .finally(function () { button.disabled = false; });
        }

        button.addEventListener('click', check);
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                check();
            }
        });

        // Digits only.
        input.addEventListener('input', function () {
            input.value = input.value.replace(/[^0-9]/g, '').slice(0, 6);
        });
    }

    /* --------------------------------------------- search suggestions */
    function initSearchSuggest() {
        var form = document.querySelector('.fc-search');
        if (!form) { return; }

        var input = form.querySelector('input[name="q"]');
        var url = form.dataset.suggestUrl;
        if (!input || !url) { return; }

        var panel = document.createElement('div');
        panel.className = 'fc-suggest';
        panel.setAttribute('role', 'listbox');
        form.appendChild(panel);

        var timer = null;
        var controller = null;

        function hide() { panel.classList.remove('is-open'); }

        function render(data) {
            var html = '';

            if (data.categories && data.categories.length) {
                html += '<div class="fc-suggest__group">Categories</div>';
                data.categories.forEach(function (c) {
                    html += '<a class="fc-suggest__item" href="' + c.url + '">'
                        + '<i class="bi bi-diagram-3"></i><span>' + c.name + '</span></a>';
                });
            }

            if (data.products && data.products.length) {
                html += '<div class="fc-suggest__group">Products</div>';
                data.products.forEach(function (p) {
                    html += '<a class="fc-suggest__item" href="' + p.url + '">'
                        + (p.image ? '<img src="' + p.image + '" alt="">' : '<i class="bi bi-box-seam"></i>')
                        + '<span class="fc-suggest__name">' + p.name + '<small>' + p.sku + '</small></span>'
                        + '<span class="fc-suggest__price">' + p.price + '</span></a>';
                });
                html += '<a class="fc-suggest__all" href="' + data.all_url + '">See all results</a>';
            }

            if (!html) {
                html = '<div class="fc-suggest__empty">No matches</div>';
            }

            panel.innerHTML = html;
            panel.classList.add('is-open');
        }

        input.addEventListener('input', function () {
            var term = input.value.trim();

            window.clearTimeout(timer);

            if (term.length < 2) {
                hide();
                return;
            }

            timer = window.setTimeout(function () {
                if (controller) { controller.abort(); }
                controller = new AbortController();

                fetch(url + '?q=' + encodeURIComponent(term), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: controller.signal
                })
                    .then(function (r) { return r.json(); })
                    .then(render)
                    .catch(function () { /* aborted or offline — leave the panel as is */ });
            }, 220);
        });

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { hide(); }
        });

        document.addEventListener('click', function (event) {
            if (!form.contains(event.target)) { hide(); }
        });
    }

    /* ------------------------------------------- auto-submit selects */
    function initAutoSubmit() {
        document.querySelectorAll('select[data-autosubmit]').forEach(function (select) {
            select.addEventListener('change', function () {
                if (select.form) { select.form.submit(); }
            });
        });
    }

    /* ----------------------------------------------- mobile filters */
    function initFilterToggle() {
        var toggle = document.querySelector('[data-filters-toggle]');
        var panel = document.getElementById('productFilters');

        if (!toggle || !panel) { return; }

        toggle.addEventListener('click', function () {
            var open = panel.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initWishlist();
        initGallery();
        initVariants();
        initPincode();
        initSearchSuggest();
        initFilterToggle();
        initAutoSubmit();

        // Auto-dismiss flash messages.
        document.querySelectorAll('.alert-dismissible[data-auto-dismiss]').forEach(function (alert) {
            setTimeout(function () {
                window.bootstrap.Alert.getOrCreateInstance(alert).close();
            }, 6000);
        });
    });
})();
