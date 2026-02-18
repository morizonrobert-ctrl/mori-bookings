// Uses html5-qrcode library (loaded via CDN in the page)
window.addEventListener('DOMContentLoaded', function () {
    const resultEl = document.getElementById('result');
    const html5QrcodeScanner = new Html5Qrcode("reader");

    function onScanSuccess(decodedText, decodedResult) {
        // stop scanning after a successful scan
        html5QrcodeScanner.stop().then(() => {
            resultEl.textContent = 'Scanned: ' + decodedText;
            // Optionally POST to API to send SMS
            fetch('/api/at_send_sms.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ to: decodedText, message: 'Your QR code was scanned.' })
            }).then(r => r.json()).then(data => {
                console.log('SMS result', data);
                const p = document.createElement('p');
                p.textContent = 'SMS send result: ' + JSON.stringify(data);
                resultEl.appendChild(p);
            }).catch(err => {
                console.error(err);
            });
        }).catch(err => console.error('Stop failed', err));
    }

    function onScanFailure(error) {
        // ignore for now
    }

    Html5Qrcode.getCameras().then(cameras => {
        if (cameras && cameras.length) {
            const cameraId = cameras[0].id;
            html5QrcodeScanner.start(cameraId, { fps: 10, qrbox: 250 }, onScanSuccess, onScanFailure)
                .catch(err => console.error('Start failed', err));
        } else {
            resultEl.textContent = 'No cameras found.';
        }
    }).catch(err => {
        resultEl.textContent = 'Camera access error: ' + err;
    });
});
