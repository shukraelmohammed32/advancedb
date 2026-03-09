(function () {
    const roleProfiles = window.loginRoleProfiles || {};
    const roleSelect = document.getElementById('role');
    const roleBadge = document.getElementById('roleBadge');
    const roleHeadline = document.getElementById('roleHeadline');
    const roleDescription = document.getElementById('roleDescription');
    const roleLabel = document.getElementById('roleLabel');
    const roleSupport = document.getElementById('roleSupport');
    const roleIcon = document.getElementById('roleIcon');
    const roleNoteIcon = document.getElementById('roleNoteIcon');
    const passwordInput = document.getElementById('password');
    const passwordToggle = document.getElementById('passwordToggle');
    const loginForm = document.getElementById('loginForm');
    const loginButton = document.getElementById('loginButton');
    const loginError = document.getElementById('loginError');

    function setIcon(element, iconClass) {
        if (!element) {
            return;
        }

        element.className = iconClass;
    }

    function renderRole(role) {
        const profile = roleProfiles[role];
        if (!profile) {
            return;
        }

        if (roleBadge) {
            roleBadge.textContent = profile.badge || '';
        }

        if (roleHeadline) {
            roleHeadline.textContent = profile.headline || '';
        }

        if (roleDescription) {
            roleDescription.textContent = profile.description || '';
        }

        if (roleLabel) {
            roleLabel.textContent = profile.label || '';
        }

        if (roleSupport) {
            roleSupport.textContent = profile.support || '';
        }

        setIcon(roleIcon, profile.icon || 'bi bi-person-circle');
        setIcon(roleNoteIcon, profile.icon || 'bi bi-person-circle');
    }

    if (roleSelect) {
        renderRole(roleSelect.value);
        roleSelect.addEventListener('change', function () {
            renderRole(roleSelect.value);
        });
    }

    if (passwordToggle && passwordInput) {
        passwordToggle.addEventListener('click', function () {
            const isHidden = passwordInput.type === 'password';
            passwordInput.type = isHidden ? 'text' : 'password';
            passwordToggle.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            passwordToggle.innerHTML = '<i class="' + (isHidden ? 'bi bi-eye-slash' : 'bi bi-eye') + '"></i>';
        });
    }

    if (loginForm && loginButton) {
        loginForm.addEventListener('submit', function () {
            const username = document.getElementById('username');
            if (!username || !passwordInput || username.value.trim() === '' || passwordInput.value.trim() === '') {
                return;
            }

            loginButton.classList.add('is-submitting');
            const label = loginButton.querySelector('.btn-copy');
            if (label) {
                label.textContent = 'Signing in...';
            }
        });
    }

    if (loginError) {
        window.requestAnimationFrame(function () {
            loginError.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        });
    }
}());
