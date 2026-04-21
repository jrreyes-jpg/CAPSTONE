document.addEventListener('DOMContentLoaded', () => {
    const scanTriggers = Array.from(document.querySelectorAll('#qrScannerBtn, [data-open-qr-scanner]'));
    const modal = document.querySelector('#qrScannerModal');

    if (scanTriggers.length === 0 || !modal) {
        return;
    }
    const closeButtons = Array.from(document.querySelectorAll('.qr-close'));
    const closeSecondary = document.querySelector('#qrScannerCloseSecondary');
    const readerContainer = document.querySelector('#qr-reader');
    const statusMessage = document.querySelector('#qrStatus');
    const assetInfo = document.querySelector('#qrAssetInfo');
    const workerInput = document.querySelector('#qrWorkerName');
    const notesInput = document.querySelector('#qrNotes');
    const logBtn = document.querySelector('#qrLogUsage');
    const scannerError = document.querySelector('#qrScannerError');

    let html5QrCode = null;
    let lastScannedAssetId = null;

    const parseAssetId = (decodedText) => {
        if (!decodedText) return null;

        const trimmed = decodedText.trim();

        if (trimmed.startsWith('asset_id=')) {
            return parseInt(trimmed.split('=')[1], 10) || null;
        }

        const assetMatch = trimmed.match(/\/(?:asset|assets)\/(\d+)/i);
        if (assetMatch) {
            return parseInt(assetMatch[1], 10);
        }

        const numeric = parseInt(trimmed, 10);
        return Number.isNaN(numeric) ? null : numeric;
    };

    const setStatus = (message, isError = false) => {
        statusMessage.textContent = message;
        statusMessage.style.color = isError ? '#e74c3c' : '#2c3e50';
    };

    const stopScanner = async () => {
        if (html5QrCode) {
            try {
                await html5QrCode.stop();
            } catch (err) {
            }
            html5QrCode.clear();
            html5QrCode = null;
        }
    };

    const openModal = () => {
        modal.classList.add('active');
        modal.setAttribute('aria-hidden', 'false');
        setStatus('Starting scanner...');
        workerInput.value = '';
        notesInput.value = '';
        assetInfo.textContent = '';
        scannerError.textContent = '';
        lastScannedAssetId = null;

        if (!window.Html5Qrcode) {
            setStatus('QR scanner library not loaded.', true);
            return;
        }

        html5QrCode = new Html5Qrcode('qr-reader');
        html5QrCode.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: 250 },
            async (decodedText) => {
                const assetId = parseAssetId(decodedText);
                if (!assetId) {
                    setStatus('Unrecognized QR format. Please scan a valid asset code.', true);
                    return;
                }

                if (assetId === lastScannedAssetId) {
                    return;
                }

                lastScannedAssetId = assetId;
                setStatus('QR scanned. Fetching asset...');

                try {
                    const res = await fetch(`/codesamplecaps/get_asset.php?asset_id=${assetId}`);
                    const json = await res.json();
                    if (json.status !== 'success') {
                        throw new Error(json.message || 'Failed to load asset');
                    }

                    const asset = json.asset;
                    assetInfo.innerHTML = `
                        <strong>${asset.asset_name}</strong> (ID: ${asset.id})<br>
                        Type: ${asset.asset_type || '<em>n/a</em>'}<br>
                        Status: ${asset.asset_status}<br>
                        Serial: ${asset.serial_number || '<em>n/a</em>'}`;
                    setStatus('Asset loaded. Enter worker name and press Log.');
                } catch (err) {
                    setStatus(err.message || 'Failed to load asset.', true);
                }
            },
            (errorMessage) => {
                if (!lastScannedAssetId) {
                    scannerError.textContent = errorMessage;
                }
            }
        ).catch((err) => {
            setStatus('Camera access denied or unavailable.', true);
        });
    };

    const closeModal = async () => {
        modal.classList.remove('active');
        modal.setAttribute('aria-hidden', 'true');
        await stopScanner();
    };

    scanTriggers.forEach((trigger) => {
        trigger.addEventListener('click', openModal);
    });
    closeButtons.forEach((btn) => btn.addEventListener('click', closeModal));
    if (closeSecondary) {
        closeSecondary.addEventListener('click', closeModal);
    }

    logBtn.addEventListener('click', async () => {
        if (!lastScannedAssetId) {
            setStatus('Scan an asset QR code first.', true);
            return;
        }

        const workerName = workerInput.value.trim();
        const notes = notesInput.value.trim();

        if (!workerName) {
            setStatus('Worker name is required.', true);
            return;
        }

        setStatus('Logging usage...');

        try {
            const res = await fetch('/codesamplecaps/log_asset_usage.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    asset_id: lastScannedAssetId,
                    worker_name: workerName,
                    notes,
                    device: navigator.userAgent
                })
            });

            const json = await res.json();
            if (json.status !== 'success') {
                throw new Error(json.message || 'Failed to log usage');
            }

            setStatus('Usage logged successfully.', false);
            window.setTimeout(() => {
                window.location.reload();
            }, 700);
        } catch (err) {
            setStatus(err.message || 'Could not log usage.', true);
        }
    });
});
