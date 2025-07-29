// --- Navigation and Page Transitions ---
document.addEventListener('DOMContentLoaded', function() {
  // Animated nav highlighting and hover
  document.querySelectorAll('nav a').forEach(link => {
    const href = link.getAttribute('href');
    if (window.location.pathname.endsWith(href)) {
      link.classList.add('active');
    }
    link.addEventListener('mouseenter', () => {
      link.style.boxShadow = '0 8px 32px #2e8cff44';
      link.style.transform = 'scale(1.13) translateY(-3px) rotate(-2deg)';
      link.style.zIndex = 2;
    });
    link.addEventListener('mouseleave', () => {
      link.style.boxShadow = '';
      link.style.transform = '';
      link.style.zIndex = '';
    });
  });

  // Page fade-in effect (using #page-fade, not body)
  document.body.classList.add('loaded');

  // Animate tables on scroll
  const tables = document.querySelectorAll('table');
  if ('IntersectionObserver' in window) {
    const tableObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.style.animation = 'tablePopIn 0.8s cubic-bezier(.4,2,.6,1)';
          entry.target.style.opacity = 1;
        }
      });
    }, { threshold: 0.2 });
    tables.forEach(t => {
      t.style.opacity = 0;
      tableObserver.observe(t);
    });
  } else {
    tables.forEach(t => {
      t.style.opacity = 1;
    });
  }

  // Floating animated background accent (only add once)
  if (!document.getElementById('floating-accent')) {
    const accent = document.createElement('div');
    accent.id = 'floating-accent';
    accent.style.position = 'fixed';
    accent.style.top = '-120px';
    accent.style.right = '-120px';
    accent.style.width = '320px';
    accent.style.height = '320px';
    accent.style.background = 'radial-gradient(circle at 70% 30%, #2e8cff99 0%, transparent 80%)';
    accent.style.zIndex = 0;
    accent.style.pointerEvents = 'none';
    accent.style.filter = 'blur(18px)';
    accent.style.animation = 'floatAccent 13s ease-in-out infinite alternate';
    document.body.appendChild(accent);
  }

  // --- GSAP SplitText Animation for all H1s ---
  if (window.gsap && window.SplitText && window.ScrollTrigger) {
    gsap.registerPlugin(SplitText, ScrollTrigger);

    document.querySelectorAll('h1').forEach(h1 => {
      // Split the text into chars
      const split = new SplitText(h1, { type: "chars" });
      // Set initial state
      gsap.set(split.chars, { opacity: 0, y: 40 });

      // Animate on scroll into view
      gsap.to(split.chars, {
        scrollTrigger: {
          trigger: h1,
          start: "top 80%",
          once: true,
        },
        opacity: 1,
        y: 0,
        duration: 0.6,
        ease: "power3.out",
        stagger: 0.08,
        onComplete: () => {
          // Clean up will-change for performance
          gsap.set(split.chars, { clearProps: "willChange" });
          // Optional: callback
          if (typeof window.onH1SplitTextComplete === 'function') {
            window.onH1SplitTextComplete(h1);
          }
        }
      });
    });
  }
});

// --- Account System (Front-End Demo) ---
function registerAccount() {
  const username = safeGetValue('reg-username');
  const email = safeGetValue('reg-email');
  const password = safeGetValue('reg-password');
  if (!username || !email || !password) {
    showToast('Please fill all fields.', 'error');
    return;
  }
  if (!validateEmail(email)) {
    showToast('Please enter a valid email address.', 'error');
    return;
  }
  // Save user as {username, email, password}
  localStorage.setItem('doba_user', JSON.stringify({username, email, password}));
  showToast('Registration successful! Please log in.', 'success');
  safeSetValue('reg-username', '');
  safeSetValue('reg-email', '');
  safeSetValue('reg-password', '');
}

function loginAccount() {
  const userOrEmail = safeGetValue('login-useroremail');
  const password = safeGetValue('login-password');
  const user = JSON.parse(localStorage.getItem('doba_user'));
  if (
    user &&
    password === user.password &&
    (userOrEmail === user.username || userOrEmail === user.email)
  ) {
    localStorage.setItem('doba_loggedin', 'true');
    setAccountStatus(`Logged in as ${user.username} (${user.email})`);
    toggleLoginForm(false);
    showToast('Login successful!', 'success');
    confettiBurst();
  } else {
    showToast('Invalid credentials.', 'error');
  }
}

function checkLogin() {
  const loggedIn = localStorage.getItem('doba_loggedin') === 'true';
  const user = JSON.parse(localStorage.getItem('doba_user'));
  if (loggedIn && user) {
    setAccountStatus(`Logged in as ${user.username} (${user.email})`);
    toggleLoginForm(false);
  }
}

function logoutAccount() {
  localStorage.setItem('doba_loggedin', 'false');
  setAccountStatus('');
  toggleLoginForm(true);
  showToast('Logged out.', 'info');
}

function setAccountStatus(msg) {
  const status = document.getElementById('account-status');
  if (status) status.innerText = msg;
}

function toggleLoginForm(show) {
  const loginForm = document.getElementById('login-form');
  const logoutBtn = document.getElementById('logout-btn');
  if (loginForm) loginForm.style.display = show ? 'block' : 'none';
  if (logoutBtn) logoutBtn.style.display = show ? 'none' : 'inline-block';
}

function safeGetValue(id) {
  const el = document.getElementById(id);
  return el ? el.value.trim() : '';
}
function safeSetValue(id, val) {
  const el = document.getElementById(id);
  if (el) el.value = val;
}

// --- Contact Form ---
function submitContactForm() {
  const name = safeGetValue('contact-name');
  const email = safeGetValue('contact-email');
  const subject = safeGetValue('contact-subject');
  const message = safeGetValue('contact-message');
  const successDiv = document.getElementById('contact-success');

  if (!name || !email || !subject || !message) {
    showToast('Please fill in all fields.', 'error');
    return false;
  }
  if (!validateEmail(email)) {
    showToast('Please enter a valid email address.', 'error');
    return false;
  }

  document.getElementById('contact-form').reset();
  successDiv.textContent = "Thank you for contacting us! Your message has been sent. We’ll reply soon.";
  successDiv.style.display = "block";
  successDiv.style.opacity = 0;
  setTimeout(() => { successDiv.style.opacity = 1; }, 100);

  setTimeout(() => {
    successDiv.style.opacity = 0;
    setTimeout(() => { successDiv.style.display = "none"; }, 800);
  }, 5000);

  showToast('Message sent!', 'success');
  return false;
}

function validateEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

// --- Toast Notification ---
function showToast(msg, type) {
  let color = '#2e8cff';
  if (type === 'success') color = '#43e97b';
  if (type === 'info') color = '#f7971e';
  if (type === 'error') color = '#e73827';
  const toast = document.createElement('div');
  toast.textContent = msg;
  Object.assign(toast.style, {
    position: 'fixed',
    bottom: '30px',
    left: '50%',
    transform: 'translateX(-50%)',
    background: color,
    color: '#fff',
    padding: '13px 28px',
    borderRadius: '30px',
    boxShadow: '0 4px 18px #0004',
    fontWeight: '700',
    fontSize: '1.1em',
    zIndex: 9999,
    opacity: 0,
    transition: 'opacity 0.5s'
  });
  document.body.appendChild(toast);
  setTimeout(() => toast.style.opacity = 1, 60);
  setTimeout(() => {
    toast.style.opacity = 0;
    setTimeout(() => document.body.removeChild(toast), 600);
  }, 2100);
}

// --- Utility ---
function escapeHTML(str) {
  return str.replace(/[&<>"']/g, function(m) {
    return ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[m];
  });
}

// --- Confetti Animation for Login ---
function confettiBurst() {
  for (let i = 0; i < 22; i++) {
    const conf = document.createElement('div');
    conf.className = 'confetti';
    Object.assign(conf.style, {
      position: 'fixed',
      left: (50 + Math.random() * 40 - 20) + '%',
      top: '55%',
      width: '14px',
      height: '14px',
      background: `linear-gradient(120deg, hsl(${Math.random()*360},80%,60%) 0%, #fff 100%)`,
      borderRadius: '50%',
      opacity: 0.82,
      zIndex: 99999,
      pointerEvents: 'none'
    });
    document.body.appendChild(conf);
    const x = (Math.random() - 0.5) * 330;
    const y = -Math.random() * 220 - 80;
    conf.animate([
      { transform: 'translate(0,0)', opacity: 0.9 },
      { transform: `translate(${x}px,${y}px)`, opacity: 0 }
    ], {
      duration: 1300 + Math.random()*600,
      easing: 'cubic-bezier(.4,2,.6,1)'
    });
    setTimeout(() => conf.remove(), 1500);
  }
}
