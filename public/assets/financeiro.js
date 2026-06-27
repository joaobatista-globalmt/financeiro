/**
 * Sistema Financeiro - JavaScript utilitário
 *
 * Funções auxiliares globais usadas em várias telas.
 */

// Auto-submit em dropdowns dentro de .filters-bar
document.addEventListener('change', function(e) {
    if (e.target.matches('.filters-bar select, .filters-bar input[type="date"]')) {
        // Pequeno debounce para não submeter ao mudar o primeiro campo
        clearTimeout(window._filterTimer);
        window._filterTimer = setTimeout(() => {
            e.target.closest('form').submit();
        }, 300);
    }
});

// Confirm em form de exclusão
document.addEventListener('submit', function(e) {
    const btn = e.submitter;
    if (btn && btn.dataset.confirm && !confirm(btn.dataset.confirm)) {
        e.preventDefault();
    }
});

// Foco no primeiro input de formulário
document.addEventListener('DOMContentLoaded', function() {
    const firstInput = document.querySelector('.form input:not([type=hidden]):not([readonly]):not([disabled]), .form select:not([disabled]), .form textarea:not([readonly])');
    if (firstInput && !firstInput.matches('[type="checkbox"]')) {
        firstInput.focus();
    }
});

// Máscaras simples
function maskCnpj(v) {
    return v.replace(/\D/g, '').replace(/^(\d{2})(\d)/, '$1.$2')
            .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
            .replace(/\.(\d{3})(\d)/, '.$1/$2')
            .replace(/(\d{4})(\d)/, '$1-$2')
            .slice(0, 18);
}

function maskCpf(v) {
    return v.replace(/\D/g, '').replace(/^(\d{3})(\d)/, '$1.$2')
            .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
            .replace(/\.(\d{3})(\d)/, '.$1-$2')
            .slice(0, 14);
}

function maskCep(v) {
    return v.replace(/\D/g, '').replace(/^(\d{5})(\d)/, '$1-$2').slice(0, 9);
}

function maskTelefone(v) {
    v = v.replace(/\D/g, '');
    if (v.length <= 10) {
        return v.replace(/^(\d{2})(\d{4})(\d)/, '($1) $2-$3').slice(0, 14);
    }
    return v.replace(/^(\d{2})(\d{5})(\d)/, '($1) $2-$3').slice(0, 15);
}

// Aplica máscaras automaticamente
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[name="cnpj"]').forEach(el => {
        el.addEventListener('input', e => e.target.value = maskCnpj(e.target.value));
    });
    document.querySelectorAll('input[name="cpf_cnpj"], input[name="cpf_cnpj_titular"]').forEach(el => {
        el.addEventListener('input', e => e.target.value = maskCnpj(e.target.value));
    });
    document.querySelectorAll('input[name="cep"]').forEach(el => {
        el.addEventListener('input', e => e.target.value = maskCep(e.target.value));
    });
    document.querySelectorAll('input[name="telefone"]').forEach(el => {
        el.addEventListener('input', e => e.target.value = maskTelefone(e.target.value));
    });
});