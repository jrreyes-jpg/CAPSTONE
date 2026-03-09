// ===================================================
// LANDING PAGE SCRIPTS - Edge Automation
// Smooth scrolling, mobile menu, form handling
// ===================================================

document.addEventListener('DOMContentLoaded', function() {

    initMobileMenu();
    initSmoothScroll();
    initNavHighlight();
    initFormHandling();
    initScrollAnimations();
    initNavbarScroll();

const consultBtn = document.getElementById("consultBtn");
const consultModal = document.getElementById("consultModal");
const closeConsult = document.getElementById("closeConsult");

if (consultBtn && consultModal) {
    consultBtn.onclick = function() {
        consultModal.style.display = "flex";
    };
}

if (closeConsult && consultModal) {
    closeConsult.onclick = function() {
        consultModal.style.display = "none";
    };
}
window.onclick = function(event) {
    if (event.target === consultModal) {
        consultModal.style.display = "none";
    }
};
});
// ===================================================
// MOBILE MENU TOGGLE
// ===================================================

function initMobileMenu() {
    const hamburger = document.querySelector('.hamburger');
    const navMenu = document.querySelector('.nav-menu');

    if (!hamburger) return;

    hamburger.addEventListener('click', function() {
        navMenu.classList.toggle('active');
        hamburger.classList.toggle('active');
    });

    // Close menu when a link is clicked
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', function() {
            navMenu.classList.remove('active');
            hamburger.classList.remove('active');
        });
    });
}

// ===================================================
// SMOOTH SCROLL NAVIGATION
// ===================================================

function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

// ===================================================
// HIGHLIGHT ACTIVE NAV LINK ON SCROLL
// ===================================================

function initNavHighlight() {
    const sections = document.querySelectorAll('section[id]');
    const navLinks = document.querySelectorAll('.nav-link');

    window.addEventListener('scroll', () => {
        let currentSection = '';

        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.clientHeight;
            
            if (window.scrollY >= sectionTop - 200) {
                currentSection = section.getAttribute('id');
            }
        });

        navLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href').slice(1) === currentSection) {
                link.classList.add('active');
            }
        });
    });
}

// ===================================================
// CONTACT FORM HANDLING
// ===================================================

function initFormHandling() {
    const contactForm = document.getElementById('contactForm');
    
    if (!contactForm) return;

    contactForm.addEventListener('submit', function(e) {
        e.preventDefault();

        // Get form data
        const formData = {
            name: document.getElementById('name').value,
            email: document.getElementById('email').value,
            message: document.getElementById('message').value
        };

        // Validate form
        if (!validateForm(formData)) {
            showNotification('Please fill in all fields correctly', 'error');
            return;
        }

        // Show success message
        showNotification('Thank you for your message! We will contact you soon.', 'success');

        // Reset form
        contactForm.reset();

        // Optional: Send data to backend via fetch API
        // sendFormToBackend(formData);
    });
}

// ===================================================
// FORM VALIDATION
// ===================================================

function validateForm(formData) {
    // Check if fields are not empty
    if (!formData.name.trim() || !formData.email.trim() || !formData.message.trim()) {
        return false;
    }

    // Validate email format
    const emailRegex = /^[^^@\s]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(formData.email)) {
        return false;
    }

    return true;
}

// ===================================================
// NOTIFICATION SYSTEM
// ===================================================

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;

    // Add styles
    const styles = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 8px;
        z-index: 10000;
        animation: slideInRight 0.3s ease-out;
        font-weight: 500;
    `;

    if (type === 'success') {
        notification.style.cssText = styles + `
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        `;
    } else if (type === 'error') {
        notification.style.cssText = styles + `
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        `;
    } else {
        notification.style.cssText = styles + `
            background-color: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        `;
    }

    // Add to page
    document.body.appendChild(notification);

    // Remove after 4 seconds
    setTimeout(() => {
        notification.style.animation = 'fadeOut 0.3s ease-out forwards';
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}

// ===================================================
// NAVBAR SCROLL SHADOW
// ===================================================

function initNavbarScroll() {
    const nav = document.querySelector('.navbar');
    if (!nav) return;
    window.addEventListener('scroll', () => {
        if (window.scrollY > 10) {
            nav.classList.add('scrolled');
        } else {
            nav.classList.remove('scrolled');
        }
    });
}

// ===================================================
// OPTIONAL: SEND FORM DATA TO BACKEND
// ===================================================

function sendFormToBackend(formData) {
    fetch('/api/contact', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        console.log('Success:', data);
    })
    .catch((error) => {
        console.error('Error:', error);
    });
}

// ===================================================
// SCROLL ANIMATIONS - FADE IN ON SCROLL
// ===================================================

function initScrollAnimations() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -100px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Observe service cards and features
    document.querySelectorAll('.service-card, .feature, .project-item').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'all 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
        observer.observe(el);
    });
}

// ===================================================
// KEYBOARD NAVIGATION
// ===================================================

document.addEventListener('keydown', function(e) {
    // Press '/' to focus search (if you add search later)
    // Press 'Escape' to close mobile menu
    if (e.key === 'Escape') {
        document.querySelector('.nav-menu')?.classList.remove('active');
        document.querySelector('.hamburger')?.classList.remove('active');
    }
});

// ===================================================
// NAVBAR STICKY EFFECT ON SCROLL
// ===================================================

let lastScrollY = 0;
const navbar = document.querySelector('.navbar');

window.addEventListener('scroll', () => {
    const currentScrollY = window.scrollY;

    if (currentScrollY > 100) {
        navbar?.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
    } else {
        navbar?.style.boxShadow = '0 2px 4px rgba(0, 0, 0, 0.1)';
    }

    lastScrollY = currentScrollY;
});

// ===================================================
// CONSOLE BRAND MESSAGE
// ===================================================

console.log(`
╔═══════════════════════════════════════════════════╗
║  EDGE AUTOMATION TECHNOLOGY SERVICES CO.         ║
║  Professional Engineering Solutions              ║
║  Phone: [Your Phone]                             ║
║  Email: [Your Email]                             ║
╚═══════════════════════════════════════════════════╝
`);
