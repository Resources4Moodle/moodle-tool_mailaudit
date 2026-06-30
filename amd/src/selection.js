// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Row selection, live count, sticky action bar and "select all matching" for the mail audit browser.
 *
 * @module     tool_mailaudit/selection
 * @copyright  2026 VIT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    form: '#tool-mailaudit-bulkform',
    rows: '.tool-mailaudit-rowselect',
    master: '#tool-mailaudit-select-page',
    matchingInput: '#tool-mailaudit-matching',
    matchingBanner: '#tool-mailaudit-matchingbanner',
    sticky: '#tool-mailaudit-sticky',
    count: '[data-mailaudit-count]',
    apply: '[data-mailaudit-apply]',
    action: '[name="bulkaction"]',
};

/**
 * Initialise selection behaviour.
 *
 * @param {Number} total Total number of records matching the current filters.
 */
export const init = (total) => {
    const form = document.querySelector(SELECTORS.form);
    if (!form) {
        return;
    }
    const matchingInput = form.querySelector(SELECTORS.matchingInput);
    const master = document.querySelector(SELECTORS.master);

    const rows = () => Array.from(form.querySelectorAll(SELECTORS.rows));
    const isMatching = () => matchingInput && matchingInput.value === '1';

    const setMatching = (on) => {
        if (matchingInput) {
            matchingInput.value = on ? '1' : '0';
        }
        const banner = document.querySelector(SELECTORS.matchingBanner);
        if (banner) {
            banner.classList.toggle('d-none', !on);
        }
    };

    const update = () => {
        const boxes = rows();
        const selected = boxes.filter((b) => b.checked).length;
        const effective = isMatching() ? total : selected;

        document.querySelectorAll(SELECTORS.count).forEach((el) => {
            el.textContent = effective;
        });

        document.querySelectorAll(SELECTORS.apply).forEach((apply) => {
            const bar = apply.closest('.tool-mailaudit-bulk') || form;
            const action = bar.querySelector(SELECTORS.action) || form.querySelector(SELECTORS.action);
            apply.disabled = effective === 0 || !action || action.value === '';
        });

        if (master) {
            master.indeterminate = selected > 0 && selected < boxes.length;
            master.checked = boxes.length > 0 && selected === boxes.length;
        }

        const sticky = document.querySelector(SELECTORS.sticky);
        if (sticky) {
            sticky.classList.toggle('d-none', effective === 0);
        }
    };

    document.addEventListener('click', (e) => {
        const toggle = e.target.closest('[data-mailaudit-select]');
        if (toggle) {
            e.preventDefault();
            const check = toggle.getAttribute('data-mailaudit-select') === 'all';
            rows().forEach((b) => {
                b.checked = check;
            });
            setMatching(false);
            update();
            return;
        }
        if (e.target.closest('[data-mailaudit-selectmatching]')) {
            e.preventDefault();
            rows().forEach((b) => {
                b.checked = true;
            });
            setMatching(true);
            update();
            return;
        }
        if (e.target.closest('[data-mailaudit-clearmatching]')) {
            e.preventDefault();
            rows().forEach((b) => {
                b.checked = false;
            });
            setMatching(false);
            update();
        }
    });

    form.addEventListener('change', (e) => {
        if (master && e.target === master) {
            rows().forEach((b) => {
                b.checked = master.checked;
            });
            setMatching(false);
        } else if (e.target.classList && e.target.classList.contains('tool-mailaudit-rowselect')) {
            setMatching(false);
        }
        update();
    });

    form.addEventListener('submit', (e) => {
        const action = form.querySelector(SELECTORS.action);
        if (!action || action.value === '') {
            e.preventDefault();
        }
    });

    update();
};
