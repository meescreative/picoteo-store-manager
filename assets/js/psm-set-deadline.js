const formatDate = (date) => {
  const jaar = date.getFullYear();
  const maand = String(date.getMonth() + 1).padStart(2, '0');
  const dag = String(date.getDate()).padStart(2, '0');
  return `${jaar}-${maand}-${dag}`;
};

// Functie berekent min en max op basis van de week-type ('active' of 'next')
const generateMinMaxDate = (dateInput, weekType, existingDateValue) => {
  const today = new Date();

  // Hulpfunctie om de maandag van een bepaalde datum te vinden
  const getMonday = (d) => {
    const date = new Date(d);
    const day = date.getDay();
    const diff = date.getDate() - day + (day === 0 ? -6 : 1); // Pas aan voor zondag
    return new Date(date.setDate(diff));
  };

  // Hulpfunctie om de zondag te vinden
  const getSunday = (mondayDate) => {
    const sunday = new Date(mondayDate);
    sunday.setDate(mondayDate.getDate() + 6);
    return sunday;
  };

  const currentMonday = getMonday(today);
  const currentSunday = getSunday(currentMonday);

  let minDate, maxDate;

  if (weekType === 'active') {
    // Min is vandaag (tenzij er al een eerdere datum in de DB stond)
    minDate = today;
    // Max is zondag van deze week
    maxDate = currentSunday;
  } else if (weekType === 'next') {
    // Min is maandag van volgende week
    const nextMonday = new Date(currentMonday);
    nextMonday.setDate(currentMonday.getDate() + 7);
    minDate = nextMonday;

    // Max is zondag van volgende week
    const nextSunday = new Date(currentSunday);
    nextSunday.setDate(currentSunday.getDate() + 7);
    maxDate = nextSunday;
  }

  // Toepassen op het input element
  if (dateInput) {
    // Als er al een datum uit de DB komt en deze ligt buiten de range, respecteer die of zet de berekende min/max
    dateInput.min = formatDate(minDate);
    dateInput.max = formatDate(maxDate);

    // Fallback voor min-date als de database geen waarde heeft maar vandaag al voorbij de min is
    if (!existingDateValue || existingDateValue === '') {
      dateInput.value = formatDate(minDate); // Optioneel: zet direct de startdatum als default
    }
  }
};

const initDeadlineForm = (formSelector, weekElementSelector, weekType) => {
  const form = document.querySelector(formSelector);
  const weekElement = document.querySelector(weekElementSelector);

  if (!form || !weekElement) return;

  const dateInput = form.querySelector('#deadline_date');
  const timeInput = form.querySelector('#deadline_hour');
  const locationId = form.querySelector('#pickup_location_id');
  const deadlineId = form.querySelector('#psm_order_deadline_id');
  const forWeek = weekElement.innerHTML.trim();

  let dateValue = '';

  fetch(
    `/wp-admin/admin-ajax.php?action=psm_get_deadline&location_id=${locationId.value}&week=${forWeek}`,
  )
    .then((response) => response.json())
    .then((result) => {
      if (result.success && result.data.exists) {
        const deadlineData = result.data.data;

        if (deadlineId && deadlineData.post_id) {
          deadlineId.value = deadlineData.post_id;
        }
        if (dateInput && deadlineData.date) {
          dateValue = deadlineData.date;
          dateInput.value = dateValue;
        }
        if (timeInput && deadlineData.hour) {
          timeInput.value = deadlineData.hour;
        }
      }

      // Roep de min/max generator aan nadat de data binnen is (of direct)
      generateMinMaxDate(dateInput, weekType, dateValue);
    })
    .catch((error) => {
      console.error(`Fout bij ophalen deadline voor ${formSelector}:`, error);
      generateMinMaxDate(dateInput, weekType, '');
    });
};

initDeadlineForm('#active_week_deadline', '.active-for-week', 'active');
initDeadlineForm('#next_week_deadline', '.next-for-week', 'next');
