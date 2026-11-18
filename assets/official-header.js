(() => {
  const scriptUrl = document.currentScript.src;
  const assetUrl = name => new URL(name, scriptUrl).href;
  const header = document.createElement('div');
  header.className = 'official-brand-header';
  header.setAttribute('role', 'group');
  header.setAttribute('aria-label', 'Municipality of Reina Mercedes, Isabela');
  header.innerHTML = `
    <img class="official-brand-seal" src="${assetUrl('Reina_Mercedes,Isabela.svg')}" alt="Reina Mercedes seal">
    <div class="official-brand-copy">
      <span>Republic of the Philippines</span>
      <strong>PROVINCE OF ISABELA</strong>
      <span>Municipality of Reina Mercedes</span>
    </div>
    <img class="official-brand-seal" src="${assetUrl('Bagong_Pilipinas_Logo.svg.webp')}" alt="Bagong Pilipinas logo">
  `;

  document.querySelectorAll('.main > .topbar, .main > .mobile-topbar, #app .topbar').forEach(topbar => {
    topbar.classList.add('has-official-brand');
    topbar.append(header.cloneNode(true));
  });
})();
