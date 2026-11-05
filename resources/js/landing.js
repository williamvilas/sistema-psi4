// Landing page (resources/views/landing). Sem bibliotecas além de jQuery e Bootstrap.
const LANDING_REDUCED_MOTION = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

$(document).ready(function () {
    initLandingFunnel();
    initLandingMenu();
    initLandingMobileCta();
    initLandingReveal();
    initLandingHeroDemo();
    initLandingHeroChecklist();
});

// Registra o clique nos CTAs com o mesmo visitante e o mesmo endpoint do <x-funnel-tag>
function trackFunnelEvent(tag) {
    try {
        const url = document.body.dataset.funnelUrl;
        const sent = JSON.parse(localStorage.getItem('funnel_tags') || '[]');

        if (!url || sent.indexOf(tag) !== -1) return;

        let visitorId = localStorage.getItem('funnel_visitor_id');
        if (!visitorId) {
            visitorId = crypto.randomUUID();
            localStorage.setItem('funnel_visitor_id', visitorId);
        }

        const params = new URLSearchParams(window.location.search);

        fetch(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
            keepalive: true,
            body: JSON.stringify({
                visitor_id: visitorId,
                tag: tag,
                path: window.location.pathname,
                referrer: document.referrer ? document.referrer.substring(0, 255) : null,
                utm_source: params.get('utm_source'),
                utm_medium: params.get('utm_medium'),
                utm_campaign: params.get('utm_campaign')
            })
        }).then(function (response) {
            if (response.ok) {
                sent.push(tag);
                localStorage.setItem('funnel_tags', JSON.stringify(sent));
            }
        }).catch(function () {});
    } catch (e) {
        // Rastreamento nunca deve quebrar a navegação.
    }
}

function initLandingFunnel() {
    $(document).on('click', '[data-funnel]', function () {
        trackFunnelEvent($(this).data('funnel'));
    });
}

function initLandingMenu() {
    if (!elementExists('#mobile-menu')) return;

    const $menu = $('#mobile-menu');
    const $toggle = $('.lp-menu-toggle');

    $menu.on('show.bs.collapse hide.bs.collapse', function (event) {
        const open = event.type === 'show';
        $toggle.attr('aria-label', open ? 'Fechar menu' : 'Abrir menu')
            .find('i').attr('class', open ? 'bi bi-x-lg' : 'bi bi-list');
    });

    $menu.on('click', 'a', function () {
        bootstrap.Collapse.getOrCreateInstance($menu[0]).hide();
    });
}

// CTA fixo no rodapé do celular: só aparece depois que o hero sai da tela
function initLandingMobileCta() {
    const hero = document.getElementById('hero');
    const cta = document.getElementById('mobile-cta');

    if (!hero || !cta || !('IntersectionObserver' in window)) return;

    new IntersectionObserver(function (entries) {
        const hidden = entries[0].isIntersecting;
        cta.classList.toggle('is-hidden', hidden);
        cta.setAttribute('aria-hidden', String(hidden));
        $(cta).find('a').attr('tabindex', hidden ? '-1' : null);
    }).observe(hero);
}

function initLandingReveal() {
    if (LANDING_REDUCED_MOTION || !('IntersectionObserver' in window)) return;

    document.body.classList.add('js-reveal');

    const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, {rootMargin: '0px 0px -8% 0px'});

    document.querySelectorAll('.lp-reveal').forEach(function (element) {
        observer.observe(element);
    });
}

// Hero: o texto é digitado, salvo e chega ao "servidor" embaralhado. Ilustrativo, gerado no navegador.
function initLandingHeroDemo() {
    const demo = document.getElementById('hero-demo');

    if (!demo || LANDING_REDUCED_MOTION || !('IntersectionObserver' in window)) return;

    const textEl = demo.querySelector('[data-demo="text"]');
    const cipherEl = demo.querySelector('[data-demo="cipher"]');
    const saveEl = demo.querySelector('[data-demo="save"]');
    const fullText = textEl.textContent.trim();
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
    let visible = false;

    const wait = function (ms) {
        return new Promise(function (resolve) { setTimeout(resolve, ms); });
    };
    const randomCipher = function (length) {
        let out = '';
        for (let i = 0; i < length; i++) out += alphabet[Math.floor(Math.random() * alphabet.length)];
        return out;
    };
    const untilVisible = async function () {
        while (!visible) await wait(400);
    };

    new IntersectionObserver(function (entries) {
        visible = entries[0].isIntersecting;
    }).observe(demo);

    const cycle = async function () {
        await untilVisible();

        cipherEl.classList.add('is-waiting');
        textEl.innerHTML = '<span class="lp-demo-caret"></span>';
        await wait(500);

        for (let i = 1; i <= fullText.length; i++) {
            textEl.innerHTML = '';
            textEl.append(document.createTextNode(fullText.slice(0, i)));
            textEl.insertAdjacentHTML('beforeend', '<span class="lp-demo-caret"></span>');
            await wait(34);
        }

        await wait(700);
        textEl.textContent = fullText;
        saveEl.classList.add('is-pressed');
        await wait(220);
        saveEl.classList.remove('is-pressed');

        // Embaralha posição por posição em ~600ms
        const target = randomCipher(Math.round(fullText.length * 1.35));
        const source = fullText.padEnd(target.length, ' ').slice(0, target.length).split('');
        cipherEl.classList.remove('is-waiting');

        for (let step = 0; step <= 12; step++) {
            const done = Math.round(target.length * step / 12);
            cipherEl.textContent = target.slice(0, done) + source.slice(done).join('');
            await wait(50);
        }

        await wait(4500);
        cycle();
    };

    cycle();
}

// Hero: os itens do checklist são marcados em sequência, uma única vez. Sem JS/movimento, já aparecem marcados.
function initLandingHeroChecklist() {
    const list = document.querySelector('[data-checklist]');

    if (!list || LANDING_REDUCED_MOTION || !('IntersectionObserver' in window)) return;

    const items = list.querySelectorAll('li');

    list.classList.add('is-animating');

    const observer = new IntersectionObserver(function (entries) {
        if (!entries[0].isIntersecting) return;

        observer.disconnect();
        items.forEach(function (item, index) {
            setTimeout(function () { item.classList.add('is-checked'); }, 500 + index * 650);
        });
    });

    observer.observe(list);
}
