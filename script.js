// Menu Mobile Toggle
const menuToggle = document.getElementById('menuToggle');
const navLinks = document.getElementById('navLinks');

menuToggle.addEventListener('click', function() {
    navLinks.classList.toggle('active');
    this.classList.toggle('active');
});

// Fechar menu ao clicar em um link
document.querySelectorAll('.nav-links a').forEach(link => {
    link.addEventListener('click', () => {
        navLinks.classList.remove('active');
        menuToggle.classList.remove('active');
    });
});

// Fechar menu ao clicar fora
document.addEventListener('click', (e) => {
    if (!e.target.closest('.navbar')) {
        navLinks.classList.remove('active');
        menuToggle.classList.remove('active');
    }
});

// Form Submission
const contactForm = document.getElementById('contactForm');
if (contactForm) {
    contactForm.addEventListener('submit', function(e) {
        e.preventDefault();

        // Get form values
        const name = this.querySelector('input[type="text"]').value;
        const email = this.querySelector('input[type="email"]').value;
        const message = this.querySelector('textarea').value;

        // Validate form
        if (!name || !email || !message) {
            alert('Por favor, preencha todos os campos!');
            return;
        }

        // Simple email validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            alert('Por favor, insira um email válido!');
            return;
        }

        // Simular envio
        const button = this.querySelector('button');
        const originalText = button.textContent;
        button.textContent = 'Enviando...';
        button.disabled = true;

        // Simular delay de envio
        setTimeout(() => {
            // Criar mailto link com os dados
            const subject = encodeURIComponent(`Contato de ${name}`);
            const body = encodeURIComponent(`Nome: ${name}\nEmail: ${email}\n\nMensagem:\n${message}`);

            // Mostrar sucesso
            alert('Obrigado! Sua mensagem foi enviada com sucesso! Em breve entraremos em contato.');

            // Limpar formulário
            this.reset();

            // Restaurar botão
            button.textContent = originalText;
            button.disabled = false;
        }, 1500);
    });
}

// Smooth Scroll Animation
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        const href = this.getAttribute('href');
        if (href !== '#' && document.querySelector(href)) {
            e.preventDefault();
            const target = document.querySelector(href);
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        }
    });
});

// Navbar Background Change on Scroll
const navbar = document.querySelector('.navbar');
window.addEventListener('scroll', function() {
    if (window.scrollY > 50) {
        navbar.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.4)';
    } else {
        navbar.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.3)';
    }
});

// Intersection Observer for Fade-in Animations
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver(function(entries) {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, observerOptions);

// Observar cards e seções
document.querySelectorAll('.diferencial-card, .menu-item, .promo-card, .contato-card, .stat').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(20px)';
    el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
    observer.observe(el);
});

// Telephone Link Handler
document.querySelectorAll('a[href^="tel:"]').forEach(link => {
    link.addEventListener('click', function(e) {
        // Permite o comportamento padrão em dispositivos móveis
        // Em desktop, alguns navegadores podem não suportar
    });
});

// Email Link Handler
document.querySelectorAll('a[href^="mailto:"]').forEach(link => {
    link.addEventListener('click', function(e) {
        // Permite o comportamento padrão
    });
});

// Validação em tempo real do formulário
const formInputs = document.querySelectorAll('.contato-form input, .contato-form textarea');
formInputs.forEach(input => {
    input.addEventListener('focus', function() {
        this.style.borderColor = 'var(--primary-color)';
    });

    input.addEventListener('blur', function() {
        if (!this.value) {
            this.style.borderColor = '#ddd';
        }
    });
});

// Efeito de hover nos botões com tracking
document.querySelectorAll('.btn').forEach(btn => {
    btn.addEventListener('mouseenter', function(e) {
        const ripple = document.createElement('span');
        const rect = this.getBoundingClientRect();

        ripple.style.position = 'absolute';
        ripple.style.borderRadius = '50%';
        ripple.style.backgroundColor = 'rgba(255, 255, 255, 0.5)';
        ripple.style.width = '20px';
        ripple.style.height = '20px';
        ripple.style.pointerEvents = 'none';

        this.style.position = 'relative';
        this.style.overflow = 'hidden';
        this.appendChild(ripple);

        setTimeout(() => ripple.remove(), 600);
    });
});

// Carousel automático para cards (opcional)
let cardIndex = 0;

function rotateCards() {
    const cards = document.querySelectorAll('.diferencial-card');
    if (cards.length > 0) {
        cards.forEach(card => card.style.opacity = '0.7');
        cards[cardIndex % cards.length].style.opacity = '1';
        cardIndex++;
    }
}

// Chamar rotateCards a cada 5 segundos
setInterval(rotateCards, 5000);

// Inicializar
window.addEventListener('load', function() {
    // Adicionar classe loaded ao body
    document.body.classList.add('loaded');

    // Animar números estatísticas
    animateStats();
});

// Animar números das estatísticas
function animateStats() {
    const stats = document.querySelectorAll('.stat h4');

    stats.forEach(stat => {
        const target = stat.textContent;
        const number = parseInt(target) || 0;

        if (number > 0) {
            let current = 0;
            const increment = number / 30;

            const interval = setInterval(() => {
                current += increment;
                if (current >= number) {
                    stat.textContent = target;
                    clearInterval(interval);
                } else {
                    stat.textContent = Math.floor(current) + '+';
                }
            }, 30);
        }
    });
}

// Ativar scroll spy para navegação
window.addEventListener('scroll', function() {
    let current = '';
    const sections = document.querySelectorAll('section');

    sections.forEach(section => {
        const sectionTop = section.offsetTop;
        const sectionHeight = section.clientHeight;
        if (pageYOffset >= sectionTop - 200) {
            current = section.getAttribute('id');
        }
    });

    document.querySelectorAll('.nav-links a').forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href').slice(1) === current) {
            link.classList.add('active');
        }
    });
});

// Adicionar ano atual ao footer dinamicamente
const footerYear = document.getElementById('footerYear');
if (footerYear) {
    footerYear.textContent = new Date().getFullYear();
}

// Carrossel de fotos
(function initCarousel() {
    const carousel = document.querySelector('[data-carousel]');
    if (!carousel) return;

    const track = carousel.querySelector('.carousel-track');
    const slides = Array.from(carousel.querySelectorAll('.carousel-slide'));
    const prevBtn = carousel.querySelector('.carousel-prev');
    const nextBtn = carousel.querySelector('.carousel-next');
    const dotsWrap = carousel.querySelector('.carousel-dots');
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    let index = 0;
    let autoplayId = null;

    slides.forEach((slide, i) => {
        const dot = document.createElement('button');
        dot.type = 'button';
        dot.setAttribute('role', 'tab');
        dot.setAttribute('aria-label', `Ir para foto ${i + 1}`);
        dot.addEventListener('click', () => goTo(i));
        dotsWrap.appendChild(dot);
    });
    const dots = Array.from(dotsWrap.children);

    function render() {
        track.style.transform = `translateX(-${index * 100}%)`;
        dots.forEach((dot, i) => dot.classList.toggle('is-active', i === index));
    }

    function goTo(i) {
        index = (i + slides.length) % slides.length;
        render();
    }

    function next() {
        goTo(index + 1);
    }

    function prev() {
        goTo(index - 1);
    }

    function startAutoplay() {
        if (prefersReducedMotion) return;
        stopAutoplay();
        autoplayId = setInterval(next, 5000);
    }

    function stopAutoplay() {
        if (autoplayId) {
            clearInterval(autoplayId);
            autoplayId = null;
        }
    }

    nextBtn.addEventListener('click', () => { next(); startAutoplay(); });
    prevBtn.addEventListener('click', () => { prev(); startAutoplay(); });

    carousel.addEventListener('mouseenter', stopAutoplay);
    carousel.addEventListener('mouseleave', startAutoplay);
    carousel.addEventListener('focusin', stopAutoplay);
    carousel.addEventListener('focusout', startAutoplay);

    carousel.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowRight') { next(); startAutoplay(); }
        if (e.key === 'ArrowLeft') { prev(); startAutoplay(); }
    });

    // Swipe (touch e mouse)
    let startX = 0;
    let isDragging = false;

    track.addEventListener('pointerdown', (e) => {
        startX = e.clientX;
        isDragging = true;
        stopAutoplay();
    });

    track.addEventListener('pointerup', (e) => {
        if (!isDragging) return;
        isDragging = false;
        const diff = e.clientX - startX;
        if (diff > 50) prev();
        else if (diff < -50) next();
        startAutoplay();
    });

    render();
    startAutoplay();
})();

// Melhorar UX com feedback visual nos botões
document.querySelectorAll('.btn').forEach(btn => {
    btn.addEventListener('click', function() {
        this.style.transform = 'scale(0.98)';
        setTimeout(() => {
            this.style.transform = '';
        }, 100);
    });
});

// Scroll to top quando página carrega com hash
if (window.location.hash) {
    setTimeout(() => {
        const target = document.querySelector(window.location.hash);
        if (target) {
            target.scrollIntoView({ behavior: 'smooth' });
        }
    }, 500);
}

console.log('🔥 Churrascaria Pampulha - Site carregado com sucesso!');
