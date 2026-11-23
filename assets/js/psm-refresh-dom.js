window.refreshDom = function (target) {
  let selectorClass = '';
  let targetElement = null;

  // Check of de meegegeven parameter een DOM-element (Node) is of een String
  if (target instanceof Element) {
    targetElement = target;
    console.log('refresh element (Node):', targetElement);
  } else if (typeof target === 'string') {
    selectorClass =
      target.startsWith('.') || target.startsWith('#') ? target : '.' + target;
    targetElement = document.querySelector(selectorClass);
    console.log('refresh element (Selector):', selectorClass);
  }

  if (!targetElement) {
    console.warn('RefreshDom: Geen geldig element of selector gevonden.');
    return;
  }

  fetch(window.location.href)
    .then((response) => response.text())
    .then((htmlText) => {
      const parser = new DOMParser();
      const doc = parser.parseFromString(htmlText, 'text/html');

      let freshContainer = null;

      // Als we via een selector werkten, zoeken we op dezelfde selector in het nieuwe document
      if (selectorClass) {
        freshContainer = doc.querySelector(selectorClass);
      } else if (targetElement) {
        // Als we een node hadden, kunnen we bijvoorbeeld bepalen welke index dit element heeft
        // of we zoeken op een unieke ID/attribuut indien aanwezig.
        // Handigste fallback: als de node een class heeft, zoeken we die op.
        const firstClass = targetElement.classList[0];
        if (firstClass) {
          const matches = doc.querySelectorAll('.' + firstClass);
          // Probeer dezelfde positie (index) te vinden in de DOM
          const allCurrent = Array.from(
            document.querySelectorAll('.' + firstClass),
          );
          const index = allCurrent.indexOf(targetElement);
          if (index !== -1 && matches[index]) {
            freshContainer = matches[index];
          }
        }
      }

      if (freshContainer && targetElement) {
        targetElement.innerHTML = freshContainer.innerHTML;

        // BELANGRIJK: Herinitialiseer je formulier logica nadat de DOM is vervangen!
        if (typeof window.initDeadlineForm === 'function') {
          window.initDeadlineForm();
        }
      }
    })
    .catch((err) => {
      console.error('DOM refresh mislukt:', err);
      window.location.reload();
    });
};
