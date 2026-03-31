// ================================
// Canvas particle animation
// ================================
const canvas = document.getElementById('particles');
if (canvas) {
    const ctx = canvas.getContext('2d');
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;

    const particles = [];
    const particleCount = 30;

    class Particle {
        constructor() {
            this.x = Math.random() * canvas.width;
            this.y = Math.random() * canvas.height;
            this.size = Math.random() * 2 + 1;
            this.speedX = Math.random() * 1 - 0.5;
            this.speedY = Math.random() * 1 - 0.5;
            this.opacity = Math.random() * 0.5 + 0.3;
        }

        update() {
            this.x += this.speedX;
            this.y += this.speedY;
            if (this.x > canvas.width) this.x = 0;
            if (this.x < 0) this.x = canvas.width;
            if (this.y > canvas.height) this.y = 0;
            if (this.y < 0) this.y = canvas.height;
        }

        draw() {
            ctx.fillStyle = `rgba(100, 200, 255, ${this.opacity})`;
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
            ctx.fill();
        }
    }

    for (let i = 0; i < particleCount; i++) particles.push(new Particle());

    function animate() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        particles.forEach((particle) => {
            particle.update();
            particle.draw();
        });
        requestAnimationFrame(animate);
    }

    animate();

    window.addEventListener('resize', () => {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    });
}

document.addEventListener('DOMContentLoaded', function () {
    // Password toggle
    document.querySelectorAll('.togglePassword').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const targetId = btn.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (!input) return;
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            btn.textContent = type === 'text' ? 'Hide' : 'Show';
            btn.setAttribute('aria-pressed', type === 'text' ? 'true' : 'false');
        });
    });

    // Forgot password redirect
    document.querySelectorAll('.links a').forEach(function (link) {
        if (link.textContent.includes('Forgot Password')) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                window.location.href = '/codesamplecaps/views/auth/forgot.php';
            });
        }
    });

    // Sidebar behavior (safe-guard if elements are missing)
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');

    if (sidebar && toggleBtn && overlay) {


        const mainContent = document.querySelector('.main-content');
        if (window.innerWidth > 768 && localStorage.getItem('sidebarShrink') === 'true') {
    sidebar.classList.add('shrink');

    if (mainContent) {
        mainContent.classList.add('sidebar-shrink');
    }

    const icon = document.getElementById('toggleIcon');
    if (icon) {
        icon.textContent = '❯';
    }
}

toggleBtn.addEventListener('click', function () {
    if (window.innerWidth <= 768) {
        sidebar.classList.toggle('mobile-open');
        overlay.classList.toggle('active');
    } else {
        sidebar.classList.toggle('shrink');

        if (mainContent) {
          mainContent.classList.toggle('sidebar-shrink');
        }

        // 🔥 ADD THIS
        const icon = document.getElementById('toggleIcon');
        if (icon) {
            if (sidebar.classList.contains('shrink')) {
                icon.textContent = '❯';
            } else {
                icon.textContent = '❮';
            }
        }

        localStorage.setItem('sidebarShrink', sidebar.classList.contains('shrink'));
    }
});
        overlay.addEventListener('click', function () {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
        });
    }

    // Smooth load
    document.body.classList.add('page-loaded');

    // Counter animation
    const counters = document.querySelectorAll('.counter');
    counters.forEach((counter) => {
        const updateCount = () => {
            const target = +counter.getAttribute('data-target');
            const currentValue = +counter.innerText;
            const increment = target / 40;

            if (currentValue < target) {
                counter.innerText = Math.ceil(currentValue + increment);
                setTimeout(updateCount, 30);
            } else {
                counter.innerText = target;
            }
        };

        updateCount();
    });
});

const form = document.querySelector("form");

if(form){
    form.addEventListener("submit", function () {
        const btn = document.getElementById("resetBtn");
        if(btn){
            btn.disabled = true;
            btn.innerText = "Sending...";
        }
    });
}

function showQR(src){
    document.getElementById('qrModal').style.display = 'flex';
    document.getElementById('qrModalImg').src = src;
}

const modal = document.getElementById('qrModal');
if(modal){
    modal.onclick = function(){
        this.style.display = 'none';
    }
}
document.addEventListener('DOMContentLoaded', function () {

    function scorePassword(value) {
        let score = 0;
        if (value.length >= 12) score++;
        if (/[A-Z]/.test(value)) score++;
        if (/[a-z]/.test(value)) score++;
        if (/\d/.test(value)) score++;
        if (/[^A-Za-z0-9]/.test(value)) score++;
        return score;
    }

    function applyStrengthUI(input, indicator) {
        const score = scorePassword(input.value);
        let text = 'Weak';
        let cls = 'weak';
        if (score >= 5) { text = 'Super Strong'; cls = 'super-strong'; }
        else if (score === 4) { text = 'Strong'; cls = 'strong'; }
        else if (score === 3) { text = 'Medium'; cls = 'medium'; }

        indicator.textContent = 'Strength: ' + text;
        indicator.className = 'pass-indicator ' + cls;
        input.classList.remove('weak-border', 'medium-border', 'strong-border');
        if (cls === 'weak') input.classList.add('weak-border');
        else if (cls === 'medium') input.classList.add('medium-border');
        else input.classList.add('strong-border');
    }

    const tempPass = document.getElementById('password');
    const tempIndicator = document.getElementById('tempPassStrength');
    if (tempPass && tempIndicator) {
        tempPass.addEventListener('input', function(){
            applyStrengthUI(tempPass, tempIndicator);
        });
    }

    document.querySelectorAll('.user-row').forEach(function(row){
        const editBtn = row.querySelector('[data-edit-btn]');
        const saveBtn = row.querySelector('[data-save-btn]');
        const cancelBtn = row.querySelector('[data-cancel-btn]');
        const inputs = row.querySelectorAll('.table-input');
        const rowId = row.getAttribute('data-row-id');
        const saveForm = document.getElementById('save-form-' + rowId);
        const originals = Array.from(inputs).map((input) => input.value);

        editBtn.addEventListener('click', function(){
            inputs.forEach((input) => input.removeAttribute('readonly'));
            editBtn.hidden = true;
            saveBtn.hidden = false;
            cancelBtn.hidden = false;
        });

        cancelBtn.addEventListener('click', function(){
            inputs.forEach((input, index) => {
                input.value = originals[index];
                input.setAttribute('readonly', 'readonly');
            });
            editBtn.hidden = false;
            saveBtn.hidden = true;
            cancelBtn.hidden = true;
        });

        saveBtn.addEventListener('click', function(){
            const byField = {};
            inputs.forEach((input) => { byField[input.getAttribute('data-field')] = input.value; });
            saveForm.querySelector('[data-save-field="full_name"]').value = byField.full_name || '';
            saveForm.querySelector('[data-save-field="email"]').value = byField.email || '';
            saveForm.querySelector('[data-save-field="phone"]').value = byField.phone || '';
            saveForm.submit();
        });
    });

});

document.addEventListener('DOMContentLoaded', function () {
    const statusField = document.getElementById('status');
    const startDateField = document.getElementById('start_date');
    const endDateField = document.getElementById('end_date');
    const startDateHelp = document.getElementById('start-date-help');
    const initialStatusHelp = document.getElementById('initial-status-help');
    const today = new Date().toISOString().split('T')[0];

    if (statusField && startDateField) {
        const syncCreateProjectFields = function () {
            const isDraft = statusField.value === 'draft';
            const isOngoing = statusField.value === 'ongoing';
            startDateField.required = isOngoing;
            startDateField.min = today;

            if (startDateHelp) {
                if (isDraft) {
                    startDateHelp.textContent = 'Optional while draft. If you set one, it cannot be earlier than today.';
                } else if (isOngoing) {
                    startDateHelp.textContent = 'Required now. Ongoing projects must use today as the Start Date.';
                } else {
                    startDateHelp.textContent = 'Optional while pending. Start Date cannot be earlier than today.';
                }
            }

            if (initialStatusHelp) {
                initialStatusHelp.textContent = isDraft
                    ? 'Draft is safe for incomplete or mistaken entries. Finalize it later before adding tasks.'
                    : isOngoing
                        ? 'Ongoing means work starts today, so today will be enforced as the Start Date.'
                        : 'Pending is the safe default for approved projects that have not started yet.';
            }

            if (isOngoing) {
                startDateField.max = today;
                if (!startDateField.value || startDateField.value !== today) {
                    startDateField.value = today;
                }
            } else {
                startDateField.removeAttribute('max');
            }

            if (startDateField.value && startDateField.value < today) {
                startDateField.value = today;
            }

            startDateField.setCustomValidity('');
        };

        syncCreateProjectFields();
        statusField.addEventListener('change', syncCreateProjectFields);
        startDateField.addEventListener('input', syncCreateProjectFields);
    }

    if (startDateField && endDateField) {
        const syncEndDateMinimum = function () {
            const minimumEndDate = startDateField.value || today;
            endDateField.min = minimumEndDate;

            if (endDateField.value && endDateField.value < minimumEndDate) {
                endDateField.value = minimumEndDate;
            }
        };

        syncEndDateMinimum();
        startDateField.addEventListener('change', syncEndDateMinimum);
        startDateField.addEventListener('input', syncEndDateMinimum);
        endDateField.addEventListener('input', syncEndDateMinimum);
    }
});
