import QrScanner from 'qr-scanner';

const root = document.getElementById('checkin-app');

if (root) {
    const video = document.getElementById('checkin-video');
    const status = document.getElementById('scan-status');
    const startButton = document.getElementById('start-camera');
    const stopButton = document.getElementById('stop-camera');
    const fileInput = document.getElementById('qr-file');
    const textForm = document.getElementById('qr-text-form');
    const textInput = document.getElementById('qr-text');
    const review = document.getElementById('reservation-review');
    const placeholder = document.getElementById('review-placeholder');
    const fields = document.getElementById('reservation-fields');
    const balanceMessage = document.getElementById('balance-message');
    const manageLink = document.getElementById('manage-booking');
    const confirmButton = document.getElementById('confirm-checkin');
    const resetButton = document.getElementById('reset-checkin');
    const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    let token = null;
    let busy = false;

    function message(text, state = 'info') {
        status.textContent = text;
        status.dataset.state = state;
    }

    function clearReview() {
        token = null;
        review.hidden = true;
        placeholder.hidden = false;
        manageLink.hidden = true;
        fields.replaceChildren();
        confirmButton.disabled = true;
    }

    function setBusy(value) {
        busy = value;
        startButton.disabled = value;
        fileInput.disabled = value;
        textForm.querySelector('button').disabled = value;
        resetButton.disabled = value;
    }

    async function post(url, value) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ token: value }),
        });
        const data = await response.json().catch(() => null);

        if (!response.ok) {
            if (response.status === 401 || response.status === 419) {
                throw new Error('Your session has ended. Refresh the page and log in again.');
            }
            throw new Error(data?.errors?.token?.[0] ?? data?.message ?? 'Could not verify this reservation. Please try again.');
        }

        return data;
    }

    function showReservation(data) {
        const details = [
            ['Guest', data.guest], ['Reference', data.reference], ['Guests', data.guests],
            ['Type', data.type === 'event' ? 'Event' : 'Public pool'],
            ['Accommodation', data.accommodation || 'Not specified'],
            ['Arrival', data.arrival], ['Departure', data.departure], ['Remaining balance', currency.format(data.balance)],
        ];
        fields.replaceChildren();
        for (const [label, value] of details) {
            const term = document.createElement('dt');
            const description = document.createElement('dd');
            term.textContent = label;
            description.textContent = value;
            fields.append(term, description);
        }
        balanceMessage.textContent = data.can_check_in
            ? 'Verify the guest details, then confirm their arrival.'
            : 'Ask an administrator or cashier to record the remaining balance payment, then scan again.';
        manageLink.hidden = !data.manage_url;
        if (data.manage_url) manageLink.href = data.manage_url;
        review.hidden = false;
        placeholder.hidden = true;
        confirmButton.disabled = !data.can_check_in;
    }

    const scanner = new QrScanner(video, (result) => {
        if (!token) reviewToken(result.data);
    }, {
        preferredCamera: 'environment', maxScansPerSecond: 5,
        highlightScanRegion: true, highlightCodeOutline: true,
        returnDetailedScanResult: true,
    });

    function stopCamera() {
        scanner.stop();
        stopButton.disabled = true;
    }

    async function reviewToken(value) {
        if (busy) return;
        stopCamera();
        clearReview();
        setBusy(true);
        message('Verifying reservation…');
        try {
            const data = await post(root.dataset.previewUrl, value.trim());
            token = value.trim();
            showReservation(data);
            message('Reservation found. Review the details before check-in.');
        } catch (error) {
            message(error.message, 'error');
        } finally {
            setBusy(false);
        }
    }

    startButton.addEventListener('click', async () => {
        if (busy) return;
        clearReview();
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            message('Camera access requires HTTPS or localhost. Use the image or pasted-code option instead.', 'error');
            return;
        }
        setBusy(true);
        try {
            await scanner.start();
            stopButton.disabled = false;
            message('Point the camera at the guest’s check-in QR code.');
        } catch {
            message('Could not start the camera. Check camera permissions or scan a saved image.', 'error');
        } finally {
            setBusy(false);
        }
    });

    stopButton.addEventListener('click', () => {
        stopCamera();
        message('Camera stopped.');
    });

    fileInput.addEventListener('change', async () => {
        const file = fileInput.files[0];
        if (!file || busy) return;
        stopCamera();
        clearReview();
        if (!file.type.startsWith('image/') || file.size > 10 * 1024 * 1024) {
            message('Choose a QR image smaller than 10 MB.', 'error');
            fileInput.value = '';
            return;
        }
        setBusy(true);
        message('Reading QR image…');
        try {
            const result = await QrScanner.scanImage(file, { returnDetailedScanResult: true, alsoTryWithoutScanRegion: true });
            setBusy(false);
            await reviewToken(result.data);
        } catch {
            message('No readable QR code found. Try a clearer image.', 'error');
        } finally {
            setBusy(false);
            fileInput.value = '';
        }
    });

    textForm.addEventListener('submit', (event) => {
        event.preventDefault();
        reviewToken(textInput.value);
    });

    confirmButton.addEventListener('click', async () => {
        if (!token || busy) return;
        const confirmedToken = token;
        confirmButton.disabled = true;
        setBusy(true);
        message('Recording check-in…');
        try {
            const data = await post(root.dataset.confirmUrl, confirmedToken);
            token = null;
            textInput.value = '';
            balanceMessage.textContent = 'Arrival recorded. You can now scan the next guest.';
            message(`${data.message} Reservation ${data.reference}.`, 'success');
        } catch (error) {
            clearReview();
            message(error.message, 'error');
        } finally {
            setBusy(false);
        }
    });

    resetButton.addEventListener('click', () => {
        clearReview();
        textInput.value = '';
        message('Ready to scan the next reservation.');
    });
    window.addEventListener('pagehide', () => scanner.destroy());
}
