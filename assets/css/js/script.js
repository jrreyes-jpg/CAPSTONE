/* ======================
   PARTICLE BACKGROUND
====================== */
const canvas = document.getElementById("particles");
const ctx = canvas.getContext("2d");

canvas.width = window.innerWidth;
canvas.height = window.innerHeight;

let particlesArray = [];

class Particle {
    constructor(){
        this.x = Math.random() * canvas.width;
        this.y = Math.random() * canvas.height;
        this.size = Math.random() * 2 + 1;
        this.speedX = Math.random() * 0.5 - 0.25;
        this.speedY = Math.random() * 0.5 - 0.25;
    }
    update(){
        this.x += this.speedX;
        this.y += this.speedY;

        if(this.x > canvas.width) this.x = 0;
        if(this.x < 0) this.x = canvas.width;
        if(this.y > canvas.height) this.y = 0;
        if(this.y < 0) this.y = canvas.height;
    }
    draw(){
        ctx.fillStyle = "rgba(0,255,150,0.7)";
        ctx.beginPath();
        ctx.arc(this.x,this.y,this.size,0,Math.PI*2);
        ctx.fill();
    }
}

function init(){
    particlesArray = [];
    for(let i=0; i<120; i++){
        particlesArray.push(new Particle());
    }
}

function animate(){
    ctx.clearRect(0,0,canvas.width,canvas.height);
    particlesArray.forEach(p=>{
        p.update();
        p.draw();
    });
    requestAnimationFrame(animate);
}

init();
animate();

/* ======================
   PREMIUM 3D LOGO INTERACTION
====================== */
const logo = document.getElementById("logo3d");

let currentX = 0;
let currentY = 0;
let targetX = 0;
let targetY = 0;

logo.addEventListener("mousemove", (e) => {
    const rect = logo.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;

    const centerX = rect.width / 2;
    const centerY = rect.height / 2;

    targetX = (y - centerY) / 10;
    targetY = (centerX - x) / 10;
});

logo.addEventListener("mouseleave", () => {
    targetX = 0;
    targetY = 0;
});

/* Smooth animation loop */
function animateLogo() {
    currentX += (targetX - currentX) * 0.08;
    currentY += (targetY - currentY) * 0.08;

    logo.style.transform = `
        rotateX(${currentX}deg) 
        rotateY(${currentY}deg)
        scale(1.05)
    `;

    requestAnimationFrame(animateLogo);
}

animateLogo();// Fade out error box after 4 seconds
document.addEventListener('DOMContentLoaded', function(){
    const errorBox = document.querySelector('.error-box');
    if(errorBox){
        setTimeout(() => {
            errorBox.classList.add('fade-out');
        }, 4000);
    }
});
document.addEventListener('DOMContentLoaded', function () {
    const toggles = document.querySelectorAll('.togglePassword');

    toggles.forEach(toggle => {
        toggle.addEventListener('click', function () {
            const targetId = this.getAttribute('data-target');
            const pwdInput = document.getElementById(targetId);

            if (pwdInput) {
                if (pwdInput.type === 'password') {
                    pwdInput.type = 'text';
                    this.textContent = 'Hide';
                    this.setAttribute('aria-pressed', 'true');
                } else {
                    pwdInput.type = 'password';
                    this.textContent = 'Show';
                    this.setAttribute('aria-pressed', 'false');
                }
            }
        });
    });
});
