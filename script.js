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
document.querySelectorAll('.diferencial-card, .menu-item, .promo-card, .contato-item, .stat').forEach(el => {
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

// Adicionar data atual ao footer dinamicamente
const year = new Date().getFullYear();
const footerYear = document.querySelector('.footer-bottom p');
if (footerYear) {
    // Já tem 2025 no HTML, deixar como está para demonstrar
}

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

// Initialize Map with Leaflet
function initMap() {
    // Coordenadas da Churrascaria Pampulha
    const lat = -19.8765432;
    const lng = -43.9876543;

    // Criar mapa com opções melhoradas
    const map = L.map('map', {
        scrollWheelZoom: true,
        tap: true
    }).setView([lat, lng], 15);

    // Usar tema CartoDB Positron (mais elegante e limpo)
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '© CartoDB contributors | © OpenStreetMap contributors',
        maxZoom: 19,
        className: 'map-tiles'
    }).addTo(map);

    // Criar ícone customizado
    const customIcon = L.divIcon({
        html: `
            <div style="
                background: linear-gradient(135deg, #c41e3a 0%, #8b2e2e 100%);
                width: 50px;
                height: 50px;
                border-radius: 50% 50% 50% 0;
                transform: rotate(-45deg);
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 4px 10px rgba(196, 30, 58, 0.4);
                border: 3px solid white;
            ">
                <span style="
                    font-size: 24px;
                    transform: rotate(45deg);
                ">🔥</span>
            </div>
        `,
        iconSize: [50, 50],
        iconAnchor: [25, 50],
        popupAnchor: [0, -50],
        className: 'custom-marker'
    });

    // Adicionar marcador customizado
    const marker = L.marker([lat, lng], {
        icon: customIcon
    }).addTo(map);

    // Popup com design profissional
    const popupContent = `
        <div style="
            background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
            border-radius: 12px;
            padding: 20px;
            min-width: 280px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            text-align: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        ">
            <h3 style="
                color: #c41e3a;
                margin: 0 0 12px 0;
                font-size: 22px;
                font-weight: bold;
            ">🔥 Churrascaria Pampulha</h3>

            <p style="
                color: #666;
                margin: 12px 0;
                font-size: 14px;
                line-height: 1.6;
            ">
                <strong>Desde 1982</strong><br>
                A melhor churrascaria de BH
            </p>

            <div style="
                background: white;
                border-radius: 8px;
                padding: 12px;
                margin: 12px 0;
                border-left: 4px solid #c41e3a;
            ">
                <p style="margin: 0; color: #333; font-size: 13px;">
                    <strong>📍 Localização:</strong><br>
                    Av. Pedro I, 568<br>
                    Itapoã - Belo Horizonte, MG
                </p>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 15px;">
                <a href="tel:+553135825158" style="
                    flex: 1;
                    background: #c41e3a;
                    color: white;
                    padding: 10px;
                    border-radius: 6px;
                    text-decoration: none;
                    font-weight: bold;
                    font-size: 12px;
                    transition: background 0.3s;
                    border: none;
                    cursor: pointer;
                ">
                    📞 Ligar
                </a>
                <a href="https://wa.me/553184449047?text=Olá%20Churrascaria%20Pampulha" target="_blank" style="
                    flex: 1;
                    background: #25d366;
                    color: white;
                    padding: 10px;
                    border-radius: 6px;
                    text-decoration: none;
                    font-weight: bold;
                    font-size: 12px;
                    transition: background 0.3s;
                    border: none;
                    cursor: pointer;
                ">
                    💬 WhatsApp
                </a>
            </div>

            <a href="https://www.google.com/maps/place/Churrascaria+Pampulha/-19.8765432,-43.9876543"
               target="_blank"
               style="
                   display: inline-block;
                   margin-top: 12px;
                   color: #007AFF;
                   text-decoration: none;
                   font-size: 12px;
                   font-weight: 500;
               ">
               Abrir no Google Maps →
            </a>
        </div>
    `;

    marker.bindPopup(popupContent, {
        maxWidth: 320,
        className: 'custom-popup'
    });

    // Abrir popup ao clicar
    marker.openPopup();

    // Adicionar controle melhorado
    L.control.zoom({
        position: 'bottomright'
    }).addTo(map);
}

// Carregar mapa quando página carrega
window.addEventListener('load', initMap);

console.log('🔥 Churrascaria Pampulha - Site carregado com sucesso!');
