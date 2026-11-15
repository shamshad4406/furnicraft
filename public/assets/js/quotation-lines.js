/**
 * The quotation line editor.
 *
 * Rows are added and removed in the page and submitted as one form, so a
 * quotation is always saved whole — half-saved lines are how a customer ends
 * up holding a document whose total disagrees with its items.
 *
 * Its own file rather than inline script: the Content-Security-Policy allows
 * neither inline handlers nor inline scripts.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var table = document.querySelector('[data-quotation-lines]');

        if (!table) {
            return;
        }

        var body = table.querySelector('tbody');
        var template = document.getElementById('quotation-line-template');
        var addButton = document.querySelector('[data-add-line]');
        var catalogue = document.getElementById('quotation-products');

        /**
         * Renumber every row's field names after an add or a remove.
         *
         * The pattern has to match whatever is between the brackets, not
         * just digits: a cloned row arrives as items[__INDEX__], and leaving
         * that in place means two added rows share a key and the second
         * quietly overwrites the first when PHP builds the array.
         */
        function reindex() {
            Array.prototype.forEach.call(body.rows, function (row, index) {
                Array.prototype.forEach.call(row.querySelectorAll('[name]'), function (field) {
                    field.name = field.name.replace(/^items\[[^\]]*\]/, 'items[' + index + ']');
                });
            });
            recalculate();
        }

        /**
         * Formatted the way PHP's money() helper does it, so the figure that
         * updates as staff type matches the one that comes back after saving.
         * en-IN would group in lakh here and leave the two disagreeing.
         */
        function money(value) {
            return (Math.round(value * 100) / 100).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        }

        /**
         * Totals shown as staff type. Advisory only — the server prices the
         * quotation when it saves, and that is the figure that counts.
         */
        function recalculate() {
            var subtotal = 0;
            var tax = 0;

            Array.prototype.forEach.call(body.rows, function (row) {
                var qty = parseFloat(row.querySelector('[data-qty]').value) || 0;
                var price = parseFloat(row.querySelector('[data-price]').value) || 0;
                var rate = parseFloat(row.querySelector('[data-rate]').value) || 0;

                var line = qty * price;
                subtotal += line;
                tax += rate > 0 ? line - line / (1 + rate / 100) : 0;

                var cell = row.querySelector('[data-line-total]');
                if (cell) {
                    cell.textContent = money(line);
                }
            });

            var discountField = document.querySelector('[data-discount]');
            var deliveryField = document.querySelector('[data-delivery]');

            var discount = discountField ? parseFloat(discountField.value) || 0 : 0;
            var delivery = deliveryField ? parseFloat(deliveryField.value) || 0 : 0;

            set('[data-total-subtotal]', money(subtotal));
            set('[data-total-tax]', money(tax));
            set('[data-total-grand]', money(Math.max(0, subtotal - discount + delivery)));
        }

        function set(selector, text) {
            var el = document.querySelector(selector);
            if (el) {
                el.textContent = text;
            }
        }

        function addRow() {
            var row = template.content.firstElementChild.cloneNode(true);
            body.appendChild(row);
            reindex();
            var name = row.querySelector('[name$="[name]"]');
            if (name) {
                name.focus();
            }
        }

        if (addButton) {
            addButton.addEventListener('click', addRow);
        }

        body.addEventListener('click', function (event) {
            var remove = event.target.closest('[data-remove-line]');

            if (!remove) {
                return;
            }

            // Never leave the editor with nothing in it: a quotation with no
            // lines cannot be saved, and an empty table looks broken.
            if (body.rows.length === 1) {
                addRow();
            }

            remove.closest('tr').remove();
            reindex();
        });

        body.addEventListener('input', recalculate);

        // Picking a catalogue product fills the line in.
        body.addEventListener('change', function (event) {
            var picker = event.target.closest('[data-product-picker]');

            if (!picker || !catalogue) {
                return;
            }

            var option = catalogue.querySelector('option[value="' + CSS.escape(picker.value) + '"]');

            if (!option) {
                return;
            }

            var row = picker.closest('tr');
            row.querySelector('[name$="[product_id]"]').value = option.dataset.id || '';
            row.querySelector('[name$="[name]"]').value = option.dataset.name || '';
            row.querySelector('[name$="[sku]"]').value = option.dataset.sku || '';
            row.querySelector('[name$="[hsn_code]"]').value = option.dataset.hsn || '';
            row.querySelector('[data-price]').value = option.dataset.price || '';
            row.querySelector('[data-rate]').value = option.dataset.rate || '';

            recalculate();
        });

        ['[data-discount]', '[data-delivery]'].forEach(function (selector) {
            var field = document.querySelector(selector);
            if (field) {
                field.addEventListener('input', recalculate);
            }
        });

        recalculate();
    });
})();
