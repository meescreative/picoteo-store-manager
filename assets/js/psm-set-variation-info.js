const initVariationInfoForms = () => {
  const productId =
    window.psmProductId ||
    document.querySelector('[data-product-id]')?.dataset.productId;

  const restBase = (window.psmRestUrl || '/wp-json/psm/v1/').replace(
    /\/?$/,
    '/',
  );
  const restNonce = window.psmRestNonce || '';

  const varForms = Array.from(
    document.querySelectorAll('form[data-options]'),
  ).filter((form) => {
    try {
      const raw = form.getAttribute('data-options');
      if (!raw) return false;

      const options = JSON.parse(raw);
      return String(options?.name ?? '').toLowerCase() === 'variation form';
    } catch (e) {
      return false;
    }
  });

  varForms.forEach((f) => {
    const variationId = f.querySelector('#variation_id').value;
    console.log('variationId: ', variationId);
    fetch(
      `${restBase}product/${encodeURIComponent(productId)}/variations/${encodeURIComponent(variationId)}`,
      {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          'X-WP-Nonce': restNonce,
        },
      },
    )
      .then((res) => {
        if (!res.ok) {
          throw new Error(`HTTP ${res.status}`);
        }
        return res.json();
      })
      .then((data) => {
        console.log('data: ', data);

        const skuField = f.querySelector('#sku');
        skuField.value = data.sku;

        const priceField = f.querySelector('#regular_price');
        priceField.value = data.regular_price;

        const saleField = f.querySelector('#sale_price');
        saleField.value = data.sale_price;

        const descField = f.querySelector('#description');
        descField.value = data.description;

        const promoStartField = f.querySelector('#promo_start');
        promoStartField.value = data.promo_start;

        const promoEndField = f.querySelector('#promo_end');
        promoEndField.value = data.promo_end;

        const statusField = f.querySelector('#status');

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
  });

  if (!productId) return;
};

initVariationInfoForms();
