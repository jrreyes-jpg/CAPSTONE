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
        if (window.innerWidth > 768 && localStorage.getItem('sidebarShrink') === 'true') {
            sidebar.classList.add('shrink');
        }

        const mainContent = document.querySelector('.main-content');

toggleBtn.addEventListener('click', function () {
    if (window.innerWidth <= 768) {
        sidebar.classList.toggle('mobile-open');
        overlay.classList.toggle('active');
    } else {
        sidebar.classList.toggle('shrink');

        if (mainContent) {
            mainContent.classList.toggle('sidebar-collapsed');
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