/* FurniCraft — admin panel behaviour. Bootstrap is loaded globally before this file. */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var bs = window.bootstrap;

        // Mobile sidebar
        var toggle = document.querySelector('[data-sidebar-toggle]');
        var backdrop = document.querySelector('.ad-backdrop');

        if (toggle) {
            toggle.addEventListener('click', function () {
                document.body.classList.toggle('sidebar-open');
            });
        }
        if (backdrop) {
            backdrop.addEventListener('click', function () {
                document.body.classList.remove('sidebar-open');
            });
        }

        // Tooltips
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            new bs.Tooltip(el);
        });

        // Confirm before destructive actions
        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!window.confirm(form.dataset.confirm)) {
                    event.preventDefault();
                }
            });
        });

        // Check / uncheck a whole permission module
        document.querySelectorAll('[data-check-module]').forEach(function (master) {
            var scope = master.closest('.ad-perm-module');
            var boxes = scope ? Array.prototype.slice.call(scope.querySelectorAll('input[name="permissions[]"]')) : [];

            function sync() {
                var checked = boxes.filter(function (b) { return b.checked; }).length;
                master.checked = checked === boxes.length && boxes.length > 0;
                master.indeterminate = checked > 0 && checked < boxes.length;
            }

            master.addEventListener('change', function () {
                boxes.forEach(function (b) { b.checked = master.checked; });
            });
            boxes.forEach(function (b) { b.addEventListener('change', sync); });
            sync();
        });

        // Preview an image before upload
        document.querySelectorAll('input[type="file"][data-preview]').forEach(function (input) {
            input.addEventListener('change', function () {
                var target = document.querySelector(input.dataset.preview);
                var file = input.files && input.files[0];
                if (target && file) {
                    target.src = URL.createObjectURL(file);
                    target.classList.remove('d-none');
                }
            });
        });

        // Auto-dismiss flash messages
        document.querySelectorAll('.alert-dismissible[data-auto-dismiss]').forEach(function (alert) {
            setTimeout(function () {
                bs.Alert.getOrCreateInstance(alert).close();
            }, 6000);
        });

        /* ------------------------------------------ repeatable form rows */
        // Variant and specification rows. The template carries __INDEX__,
        // which is replaced with the next free index on each insert.
        document.querySelectorAll('[data-repeat-add]').forEach(function (button) {
            button.addEventListener('click', function () {
                var selector = button.dataset.repeatAdd;
                var container = document.querySelector(selector);
                var template = document.querySelector('[data-repeat-template="' + selector + '"]');

                if (!container || !template) {
                    return;
                }

                var index = container.querySelectorAll('[data-repeat-row]').length;
                var seen = {};

                // Index by what is already there, so removing then adding
                // cannot collide with an existing row name.
                container.querySelectorAll('[name]').forEach(function (field) {
                    var match = field.name.match(/\[(\d+)\]/);
                    if (match) { seen[match[1]] = true; }
                });
                while (seen[index]) { index++; }

                var html = template.innerHTML.split('__INDEX__').join(String(index));
                var holder = container.tagName === 'TBODY'
                    ? document.createElement('tbody')
                    : document.createElement('div');

                holder.innerHTML = html.trim();

                while (holder.firstElementChild) {
                    container.appendChild(holder.firstElementChild);
                }
            });
        });

        document.addEventListener('click', function (event) {
            var remove = event.target.closest('[data-repeat-remove]');
            if (!remove) { return; }

            var row = remove.closest('[data-repeat-row]');
            var container = row && row.parentElement;

            if (!row || !container) { return; }

            // Keep one blank row so the section never looks broken.
            if (container.querySelectorAll('[data-repeat-row]').length > 1) {
                row.remove();
            } else {
                row.querySelectorAll('input[type="text"], input[type="number"]').forEach(function (field) {
                    field.value = '';
                });
            }
        });

        /* ------------------------------------- product image drag-sorting */
        var gallery = document.getElementById('productImages');

        if (gallery) {
            var dragged = null;

            gallery.querySelectorAll('[data-drag-handle]').forEach(function (handle) {
                var card = handle.closest('[data-image-id]');

                handle.addEventListener('dragstart', function () {
                    dragged = card;
                    card.style.opacity = '.4';
                });

                handle.addEventListener('dragend', function () {
                    card.style.opacity = '';
                    persistOrder();
                });
            });

            gallery.addEventListener('dragover', function (event) {
                event.preventDefault();

                var target = event.target.closest('[data-image-id]');
                if (!target || !dragged || target === dragged) { return; }

                var cards = Array.prototype.slice.call(gallery.querySelectorAll('[data-image-id]'));
                var from = cards.indexOf(dragged);
                var to = cards.indexOf(target);

                if (from < to) {
                    target.after(dragged);
                } else {
                    target.before(dragged);
                }
            });

            function persistOrder() {
                var order = Array.prototype.map.call(
                    gallery.querySelectorAll('[data-image-id]'),
                    function (card) { return Number(card.dataset.imageId); }
                );

                var token = document.querySelector('meta[name="csrf-token"]');

                fetch(gallery.dataset.reorderUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token ? token.content : '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ order: order }),
                }).catch(function () {
                    /* A failed save just means the old order stays; no need to shout. */
                });
            }
        }
    });
})();
