/*
 * Prints jewellery tags on a local Zebra printer through QZ Tray.
 * The server builds the ZPL; this file only fetches it and hands it to QZ Tray as raw data.
 * Needs public/vendor/qz-tray/qz-tray.js loaded first.
 */
(function (global) {
    'use strict';

    const state = {
        printerName: '',
        certificateUrl: '',
        signUrl: '',
        csrf: '',
        securityReady: false,
        signed: false,
        busy: false,
    };

    class LabelPrintError extends Error {
        constructor(code, message) {
            super(message);
            this.name = 'LabelPrintError';
            this.code = code;
        }
    }

    function qzLibrary() {
        if (!global.qz || !global.qz.websocket) {
            throw new LabelPrintError('qz_script_missing', 'The printing helper did not load. Refresh the page and try again.');
        }

        return global.qz;
    }

    function setupSecurity() {
        if (state.securityReady) {
            return;
        }

        const qz = qzLibrary();

        qz.security.setCertificatePromise(function (resolve) {
            if (!state.certificateUrl) {
                resolve();
                return;
            }

            fetch(state.certificateUrl, { credentials: 'same-origin', headers: { Accept: 'text/plain' } })
                .then(function (response) { return response.status === 200 ? response.text() : ''; })
                .then(function (certificate) {
                    state.signed = certificate.trim() !== '';
                    resolve(state.signed ? certificate : undefined);
                })
                .catch(function () {
                    state.signed = false;
                    resolve();
                });
        });

        qz.security.setSignatureAlgorithm('SHA512');
        qz.security.setSignaturePromise(function (toSign) {
            return function (resolve, reject) {
                if (!state.signed || !state.signUrl) {
                    resolve();
                    return;
                }

                fetch(state.signUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'text/plain', 'X-CSRF-TOKEN': state.csrf },
                    body: JSON.stringify({ request: toSign }),
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new LabelPrintError('signing_failed', 'The shop server could not sign the print request.');
                        }

                        return response.text();
                    })
                    .then(resolve, reject);
            };
        });

        state.securityReady = true;
    }

    async function connect() {
        const qz = qzLibrary();
        setupSecurity();

        if (qz.websocket.isActive()) {
            return;
        }

        try {
            await qz.websocket.connect({ retries: 1, delay: 1 });
        } catch (error) {
            if (qz.websocket.isActive()) {
                return;
            }

            throw new LabelPrintError(
                'qz_unreachable',
                'QZ Tray is not running on this computer. Install QZ Tray, start it, allow this website when it asks, then try again.'
            );
        }
    }

    async function findPrinter() {
        await connect();

        if (!state.printerName) {
            throw new LabelPrintError('printer_not_set', 'No tag printer name is set. Add it on the tag settings page.');
        }

        let found;

        try {
            found = await qzLibrary().printers.find(state.printerName);
        } catch (error) {
            throw new LabelPrintError('printer_not_found', 'Printer "' + state.printerName + '" was not found on this computer. Check that it is installed and the name matches exactly.');
        }

        const name = Array.isArray(found) ? found[0] : found;

        if (!name || String(name).toLowerCase() !== state.printerName.toLowerCase()) {
            throw new LabelPrintError('printer_not_found', 'Printer "' + state.printerName + '" was not found on this computer. Check that it is installed and the name matches exactly.');
        }

        return String(name);
    }

    async function readError(response) {
        try {
            const body = await response.json();

            if (body && body.errors) {
                const first = Object.values(body.errors)[0];
                return Array.isArray(first) ? first[0] : String(first);
            }

            return body && body.message ? body.message : '';
        } catch (error) {
            return '';
        }
    }

    async function request(url, options) {
        let response;

        try {
            response = await fetch(url, Object.assign({ credentials: 'same-origin' }, options || {}));
        } catch (error) {
            throw new LabelPrintError('network', 'Could not reach the shop server. Check the internet connection and try again.');
        }

        if (response.status === 401 || response.status === 419) {
            throw new LabelPrintError('session_expired', 'You have been signed out. Sign in again and retry.');
        }

        if (response.status === 403) {
            throw new LabelPrintError('forbidden', 'You are not allowed to print tags.');
        }

        if (response.status === 404) {
            throw new LabelPrintError('not_found', 'This piece was not found in this shop.');
        }

        if (response.status === 429) {
            throw new LabelPrintError('too_many', 'Too many print requests. Wait a minute and try again.');
        }

        if (response.status === 422) {
            throw new LabelPrintError('invalid', (await readError(response)) || 'The tag settings are not valid.');
        }

        if (!response.ok) {
            throw new LabelPrintError('server', 'The shop server could not make the tag. Try again shortly.');
        }

        return response;
    }

    async function fetchZpl(url) {
        const response = await request(url, { headers: { Accept: 'application/json' } });
        const zpl = await response.text();

        if (!zpl.startsWith('~SD') && !zpl.startsWith('^XA')) {
            throw new LabelPrintError('server', 'The shop server sent something that is not a tag.');
        }

        return zpl;
    }

    async function sendRaw(printer, zpl) {
        const qz = qzLibrary();
        const config = qz.configs.create(printer, { copies: 1 });

        try {
            await qz.print(config, [{ type: 'raw', format: 'command', flavor: 'plain', data: zpl }]);
        } catch (error) {
            const detail = error && error.message ? error.message : String(error || '');

            if (/cancel|reject|denied|block/i.test(detail)) {
                throw new LabelPrintError('rejected', 'The print was not allowed on this computer. Click Allow when QZ Tray asks.');
            }

            throw new LabelPrintError('print_failed', 'The printer did not accept the tag. Check it is on, has tags loaded and is not paused.');
        }
    }

    async function guarded(task) {
        if (state.busy) {
            throw new LabelPrintError('busy', 'A print is already running. Wait for it to finish.');
        }

        state.busy = true;

        try {
            return await task();
        } finally {
            state.busy = false;
        }
    }

    const api = {
        LabelPrintError: LabelPrintError,

        configure: function (options) {
            state.printerName = String(options.printerName || '');
            state.certificateUrl = String(options.certificateUrl || '');
            state.signUrl = String(options.signUrl || '');
            state.csrf = String(options.csrf || '');
        },

        isBusy: function () {
            return state.busy;
        },

        checkPrinter: function () {
            return findPrinter();
        },

        printUrl: function (zplUrl) {
            return guarded(async function () {
                const printer = await findPrinter();
                const zpl = await fetchZpl(zplUrl);
                await sendRaw(printer, zpl);

                return printer;
            });
        },

        printBatch: function (batchUrl, items, copies, onProgress) {
            return guarded(async function () {
                const printer = await findPrinter();
                const response = await request(batchUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': state.csrf },
                    body: JSON.stringify({ items: items, copies: copies }),
                });
                const body = await response.json();
                const printed = [];
                const failed = (body.errors || []).map(function (row) {
                    return { code: row.code || row.uuid, message: row.message };
                });

                for (const tag of body.tags || []) {
                    try {
                        await sendRaw(printer, tag.zpl);
                        printed.push(tag.code);
                    } catch (error) {
                        failed.push({ code: tag.code, message: error.message });
                    }

                    if (onProgress) {
                        onProgress(printed.length + failed.length, items.length);
                    }
                }

                return { printer: printer, printed: printed, failed: failed };
            });
        },
    };

    global.addEventListener('beforeunload', function () {
        if (global.qz && global.qz.websocket && global.qz.websocket.isActive()) {
            global.qz.websocket.disconnect();
        }
    });

    global.JewelleryLabelPrinter = api;
})(window);
