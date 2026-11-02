(function () {
    'use strict';

    const config = window.NetpeakAIOFeedback;
    if (!config) return;

    const row = document.querySelector('tr[data-plugin="' + config.plugin + '"]');
    const link = row && row.querySelector('.deactivate a');
    if (!link) return;

    const t = config.i18n;
    let overlay = null;

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text) node.textContent = text;
        return node;
    }

    function build() {
        overlay = el('div', 'ntp-aio-fb');
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'ntp-aio-fb-title');

        const box = el('div', 'ntp-aio-fb__box');
        const title = el('h2', 'ntp-aio-fb__title', t.title);
        title.id = 'ntp-aio-fb-title';
        box.appendChild(title);
        box.appendChild(el('p', 'ntp-aio-fb__lede', t.lede));

        const list = el('div', 'ntp-aio-fb__reasons');
        const detailsInput = el('textarea', 'ntp-aio-fb__details');
        detailsInput.rows = 3;
        detailsInput.maxLength = 1000;
        detailsInput.hidden = true;

        config.reasons.forEach(function (reason) {
            const label = el('label', 'ntp-aio-fb__reason');
            const radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'ntp-aio-reason';
            radio.value = reason.slug;
            radio.addEventListener('change', function () {
                submit.disabled = false;
                detailsInput.hidden = !reason.details;
                detailsInput.placeholder = reason.details || '';
                if (reason.details) detailsInput.focus();
            });
            label.appendChild(radio);
            label.appendChild(document.createTextNode(' ' + reason.label));
            list.appendChild(label);
        });
        box.appendChild(list);
        box.appendChild(detailsInput);
        box.appendChild(el('p', 'ntp-aio-fb__privacy', t.privacy));

        const deleteRow = el('label', 'ntp-aio-fb__delete');
        const deleteBox = document.createElement('input');
        deleteBox.type = 'checkbox';
        deleteBox.checked = !!config.deleteData;
        deleteRow.appendChild(deleteBox);
        deleteRow.appendChild(document.createTextNode(' ' + t.deleteData));
        box.appendChild(deleteRow);
        box.appendChild(el('p', 'ntp-aio-fb__hint', t.deleteHint));

        const actions = el('div', 'ntp-aio-fb__actions');
        const submit = el('button', 'button button-primary', t.submit);
        submit.type = 'button';
        submit.disabled = true;
        const skip = el('button', 'button', t.skip);
        skip.type = 'button';
        const cancel = el('button', 'button-link ntp-aio-fb__cancel', t.cancel);
        cancel.type = 'button';
        actions.appendChild(submit);
        actions.appendChild(skip);
        actions.appendChild(cancel);
        box.appendChild(actions);
        overlay.appendChild(box);

        function selectedReason() {
            const checked = overlay.querySelector('input[name="ntp-aio-reason"]:checked');
            return checked ? checked.value : '';
        }

        function finish(send) {
            submit.disabled = true;
            skip.disabled = true;
            submit.textContent = t.sending;

            const body = new URLSearchParams();
            body.set('action', config.action);
            body.set('nonce', config.nonce);
            body.set('delete_data', deleteBox.checked ? '1' : '0');
            body.set('send', send ? '1' : '0');
            if (send) {
                body.set('reason', selectedReason());
                body.set('details', detailsInput.value);
            }

            // Deactivation must never depend on the request: go on whatever the answer is.
            const proceed = function () { window.location.href = link.href; };
            fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
                .then(proceed, proceed);
        }

        submit.addEventListener('click', function () { if (selectedReason()) finish(true); });
        skip.addEventListener('click', function () { finish(false); });
        cancel.addEventListener('click', close);
        overlay.addEventListener('click', function (event) { if (event.target === overlay) close(); });
        document.addEventListener('keydown', onKey);

        document.body.appendChild(overlay);
        (list.querySelector('input') || submit).focus();
    }

    function onKey(event) {
        if (event.key === 'Escape') close();
    }

    function close() {
        if (!overlay) return;
        document.removeEventListener('keydown', onKey);
        overlay.remove();
        overlay = null;
        link.focus();
    }

    link.addEventListener('click', function (event) {
        event.preventDefault();
        if (!overlay) build();
    });
})();
