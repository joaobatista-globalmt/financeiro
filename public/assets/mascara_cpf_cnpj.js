// mascara_cpf_cnpj.js
// Mascara dinamica CPF/CNPJ baseada no select tipo_pessoa.
// Arquivo separado em /assets/ pra evitar problemas de escape inline
// e cache agressivo do navegador. Bypass com ?v=timestamp no src.

(function () {
  'use strict';

  var inputDoc = document.getElementById('cpf_cnpj');
  var selTipo  = document.querySelector('select[name="tipo_pessoa"]');
  if (!inputDoc) return;

  function getTipo() {
    return selTipo ? selTipo.value : 'J';
  }

  // === MASCARAS ===
  function mascaraCpf(el) {
    var v = el.value.replace(/\D/g, '').slice(0, 11);
    v = v.replace(/^(\d{3})(\d)/, '$1.$2')
         .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
         .replace(/\.(\d{3})(\d)/, '.$1-$2');
    el.value = v;
  }

  function mascaraCnpj(el) {
    var v = el.value.replace(/\D/g, '').slice(0, 14);
    v = v.replace(/^(\d{2})(\d)/, '$1.$2')
         .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
         .replace(/\.(\d{3})(\d)/, '.$1/$2')
         .replace(/(\d{4})(\d)/, '$1-$2');
    el.value = v;
  }

  function aplicarMascara(el) {
    if (getTipo() === 'F') mascaraCpf(el);
    else mascaraCnpj(el);
  }

  // === HANDLERS ===
  function onInput() {
    aplicarMascara(this);
  }

  function onTipoChange() {
    // Limpa o campo para a mascara nova nao misturar com valor antigo.
    inputDoc.value = '';
    // Ajusta placeholder e maxLength do tipo escolhido.
    if (getTipo() === 'F') {
      inputDoc.placeholder = '000.000.000-00';
      inputDoc.maxLength = 14;
    } else {
      inputDoc.placeholder = '00.000.000/0000-00';
      inputDoc.maxLength = 18;
    }
  }

  // === REGISTRA EVENTOS ===
  inputDoc.addEventListener('input', onInput);
  if (selTipo) selTipo.addEventListener('change', onTipoChange);

  // Na carga inicial, ajusta placeholder/maxLength sem limpar valor
  // (importante no modo EDITAR, onde o CNPJ ja vem do banco).
  if (getTipo() === 'F') {
    inputDoc.placeholder = '000.000.000-00';
    inputDoc.maxLength = 14;
  } else {
    inputDoc.placeholder = '00.000.000/0000-00';
    inputDoc.maxLength = 18;
  }
})();
