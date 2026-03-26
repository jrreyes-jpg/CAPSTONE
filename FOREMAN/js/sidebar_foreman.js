document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');
    const qrBtn = document.getElementById('qrScannerBtn');

    // Sidebar toggle functionality
    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
        overlay.classList.toggle('active');
        toggleBtn.querySelector('#toggleIcon').textContent = sidebar.classList.contains('collapsed') ? '❯' : '❮';
    });

    // QR Scanner functionality placeholder
    qrBtn.addEventListener('click', async () => {
        try {
            // Check for camera support
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                throw new Error('Camera not supported');
            }

            // Request camera permission
            const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });

            // Placeholder: In production, integrate with QR scanner library
            alert('Camera access granted. QR Scanner would initialize here.');

            // Stop the stream after demo
            stream.getTracks().forEach(track => track.stop());
        } catch (error) {
            alert(`QR Scanner error: ${error.message}`);
        }
    });
});