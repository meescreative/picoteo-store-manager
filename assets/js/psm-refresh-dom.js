/**
 * Haalt de huidige pagina vers op via een fetch en vervangt de inhoud van een specifieke container.
 *
 * @param {string} selector - De class of id van de container (bijv. 'psm-pickup-location-container')
 */
window.refreshDom = function (selector) {
  const selectorClass =
    selector.startsWith('.') || selector.startsWith('#')
      ? selector
      : '.' + selector;

  console.log('refresh element: ', selector);

  fetch(window.location.href)
    .then((response) => response.text())
    .then((htmlText) => {
      const parser = new DOMParser();
      const doc = parser.parseFromString(htmlText, 'text/html');

      const freshContainer = doc.querySelector(selectorClass);
      const currentContainer = document.querySelector(selectorClass);

      if (freshContainer && currentContainer) {
        currentContainer.innerHTML = freshContainer.innerHTML;
      }
    })
    .catch((err) => {
      console.error('DOM refresh mislukt:', err);
      // Fallback als er iets misgaat
      window.location.reload();
    });
};
