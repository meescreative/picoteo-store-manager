const initProductInfoForm = () => {
  const descField = document.querySelector('#description');
  const taxClassField = document.querySelector('#tax_class');
  const categoryField = document.querySelector('#product_cat');
  const titleField = document.querySelector('#title');
  const statusField = document.querySelector('#status');
  const productId =
    window.psmProductId ||
    document.querySelector('[data-product-id]')?.dataset.productId;
  const restBase = (window.psmRestUrl || '/wp-json/psm/v1/').replace(
    /\/?$/,
    '/',
  );
  const restNonce = window.psmRestNonce || '';

  if (!productId || (!descField && !taxClassField && !categoryField)) return;

  fetch(`${restBase}product/${encodeURIComponent(productId)}`, {
    method: 'GET',
    credentials: 'same-origin',
    headers: {
      'X-WP-Nonce': restNonce,
    },
  })
    .then((res) => {
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }
      return res.json();
    })
    .then((data) => {
      console.log(data);

      if (descField && typeof data.description === 'string') {
        descField.value = data.description;
        descField.dispatchEvent(new Event('input', { bubbles: true }));
      }

      if (taxClassField && data.tax_class_value) {
        const optionExists = Array.from(taxClassField.options).some(
          (opt) => opt.value === data.tax_class_value,
        );

        if (optionExists) {
          taxClassField.value = data.tax_class_value;
          taxClassField.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
          console.warn(
            'Geen overeenkomende <option> voor:',
            data.tax_class_value,
          );
        }
      }

      if (categoryField && data.category?.slug) {
        const categorySlug = String(data.category.slug);
        const categoryOptionExists = Array.from(categoryField.options).some(
          (opt) => opt.value === categorySlug,
        );

        if (categoryOptionExists) {
          categoryField.value = categorySlug;
          categoryField.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
          console.warn(
            'Geen overeenkomende <option> voor categorie-slug:',
            categorySlug,
          );
        }
      }

      if (titleField && data.title) {
        titleField.value = data.title;
      }

      if (statusField && data.status) {
        const statusSlug = String(data.status);
        const statusOptionExists = Array.from(statusField.options).some(
          (opt) => opt.value === statusSlug,
        );

        if (statusOptionExists) {
          statusField.value = statusSlug;
          statusField.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
          console.warn(
            'Geen overeenkomende <option> voor categorie-slug:',
            statusSlug,
          );
        }
      }
    })
    .catch((err) => {
      console.error('Fout bij ophalen productdata:', err);
    });
};

initProductInfoForm();
