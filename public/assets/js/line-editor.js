/**
 * The editable line table, shared by quotations and purchase orders.
 *
 * One script rather than one per screen: the two tables differ only in what
 * the money column is called and which list of products fills a row, and two
 * near-identical copies drift apart the first time somebody fixes a bug in
 * one of them.
 *
 * Rows are added and removed in the page and submitted as one form, so a
 * document is always saved whole — half-saved lines are how a customer ends
 * up holding a total that disagrees with its items.
 *
 * Its own file rather than inline script: the Content-Security-Policy allows
 * neither inline handlers nor inline scripts, so an onclick here would
 * silently do nothing.
 *
 * Configured from the table's own attributes:
 *   data-line-editor            marks the table
 *   data-catalogue="#selector"  the hidden <select> holding products
 *   data-template="#selector"   the <template> holding a blank row
 *   data-amount="price|cost"    which data- attribute carries the money
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-line-editor]').forEach(setUp);
    });

    function setUp(table) {
        var body = table.tBodies[0];
        var template = document.querySelector(table.dataset.template);
        var catalogue = document.querySelector(table.dataset.catalogue);
        var amountKey = table.dataset.amount || 'price';
        var addButton = document.querySelector('[data-add-line]');

        if (!body || !template) {
            return;
        }

        /**
         * Renumber every row's field names after an add or a remove.
         *
         * The pattern matches whatever is between the brackets, not just
         * digits: a cloned row arrives as items[__INDEX__], and leaving that
         * in place means two added rows share a key and the second quietly
         * overwrites the first when PHP builds the array.
         */
        function reindex() {
            Array.prototype.forEach.call(body.rows, function (row, index) {
                row.querySelectorAll('[name]').forEach(function (field) {
                    field.name = field.name.replace(/^items\[[^\]]*\]/, 'items[' + index + ']');
                });
            });

            recalculate();
        }

        /**
         * Formatted the way PHP's money() helper does it, so the figure that
         * updates as staff type matches the one that comes back after saving.
         */
        function money(value) {
            return (Math.round(value * 100) / 100).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        }

        function value(row, selector) {
            var field = row.querySelector(selector);

            return field ? parseFloat(field.value) || 0 : 0;
        }

        /**
         * Totals shown as staff type. Advisory only — the server prices the
         * document when it saves, and that is the figure that counts.
         */
        function recalculate() {
            var subtotal = 0;
            var tax = 0;

            Array.prototype.forEach.call(body.rows, function (row) {
                var qty = value(row, '[data-qty]');
                var amount = value(row, '[data-' + amountKey + ']');
                var rate = value(row, '[data-rate]');

                var line = qty * amount;
                subtotal += line;
                tax += rate > 0 ? line - line / (1 + rate / 100) : 0;

                var cell = row.querySelector('[data-line-total]');

                if (cell) {
                    cell.textContent = money(line);
                }
            });

            var discount = fieldValue('[data-discount]');
            var delivery = fieldValue('[data-delivery]');
            var other = fieldValue('[data-other-charges]');

            setText('[data-total-subtotal]', money(subtotal));
            setText('[data-total-tax]', money(tax));
            setText('[data-total-grand]', money(Math.max(0, subtotal - discount + delivery + other)));
        }

        function fieldValue(selector) {
            var field = document.querySelector(selector);

            return field ? parseFloat(field.value) || 0 : 0;
        }

        function setText(selector, text) {
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

            // Never leave the editor with nothing in it: a document with no
            // lines cannot be saved, and an empty table looks broken.
            if (body.rows.length === 1) {
                addRow();
            }

            remove.closest('tr').remove();
            reindex();
        });

        body.addEventListener('input', recalculate);

        // Picking a catalogue product fills the row in.
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

            set(row, '[name$="[product_id]"]', option.dataset.id || '');
            set(row, '[name$="[name]"]', option.dataset.name || '');
            set(row, '[name$="[sku]"]', option.dataset.sku || '');
            set(row, '[name$="[hsn_code]"]', option.dataset.hsn || '');
            set(row, '[data-' + amountKey + ']', option.dataset[amountKey] || '');
            set(row, '[data-rate]', option.dataset.rate || '');

            recalculate();
        });

        ['[data-discount]', '[data-delivery]', '[data-other-charges]'].forEach(function (selector) {
            var field = document.querySelector(selector);

            if (field) {
                field.addEventListener('input', recalculate);
            }
        });

        recalculate();
    }

    function set(row, selector, value) {
        var field = row.querySelector(selector);

        if (field) {
            field.value = value;
        }
    }
})();
