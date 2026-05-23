function togglePw(fieldId, btn) {
    const field = document.getElementById(fieldId);
    const icon  = btn.querySelector('i');
    if (field.type === 'password') {
        field.type = 'text';
        icon.className = 'bx bx-show';
    } else {
        field.type = 'password';
        icon.className = 'bx bx-hide';
    }
}

const pwInput = document.getElementById('reg_password');
if (pwInput) {
    pwInput.addEventListener('input', function () {
        const bar   = document.getElementById('pwStrengthBar');
        const fill  = document.getElementById('pwStrengthFill');
        const label = document.getElementById('pwStrengthLabel');
        const val   = this.value;

        bar.style.display = val.length > 0 ? 'block' : 'none';

        let score = 0;
        if (val.length >= 6)  score++;
        if (val.length >= 10) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        const levels = [
            { pct: '20%', color: '#ff4d4d', text: 'Very Weak',  textColor: '#ff4d4d' },
            { pct: '40%', color: '#ff944d', text: 'Weak',       textColor: '#ff944d' },
            { pct: '60%', color: '#ffd700', text: 'Fair',       textColor: '#ffd700' },
            { pct: '80%', color: '#00e5a0', text: 'Strong',     textColor: '#00e5a0' },
            { pct: '100%',color: '#00f3ff', text: 'Very Strong',textColor: '#00f3ff' },
        ];
        const lvl = levels[Math.min(score, 4)];
        fill.style.width      = lvl.pct;
        fill.style.background = lvl.color;
        label.textContent     = lvl.text;
        label.style.color     = lvl.textColor;
    });
}

const registerForm = document.getElementById('registerForm');
if (registerForm) {
    registerForm.addEventListener('submit', function (e) {
        const pw  = document.getElementById('reg_password').value;
        const cpw = document.getElementById('confirm_password').value;
        if (pw !== cpw) {
            e.preventDefault();
            alert('Passwords do not match!');
        }
    });
}

const loginForm = document.getElementById('loginForm');
if (loginForm) {
    loginForm.addEventListener('submit', function () {
        const btn = document.getElementById('loginBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i> Logging in...";
        }
    });
}

function setCookie(name, value, days) {
    const expires = new Date(Date.now() + days * 864e5).toUTCString();
    document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + expires + '; path=/';
}
function getCookie(name) {
    return document.cookie.split('; ').reduce((r, v) => {
        const parts = v.split('=');
        return parts[0] === name ? decodeURIComponent(parts[1]) : r;
    }, null);
}

if (!getCookie('hack_visited')) {
    setCookie('hack_visited', 'true', 365);
}
