document.addEventListener('DOMContentLoaded', function () {
  const container = document.getElementById('nbt-calendar');
  if (!container || typeof nbtCalendarEvents === 'undefined') {
    return;
  }

  // Ustawienia początkowe
  let currentDate = new Date();

  const months = [
    'Styczeń', 'Luty', 'Marzec', 'Kwiecień', 'Maj', 'Czerwiec',
    'Lipiec', 'Sierpień', 'Wrzesień', 'Październik', 'Listopad', 'Grudzień'
  ];
  const weekdays = ['PN', 'WT', 'ŚR', 'CZW', 'PT', 'SOB', 'NIE'];

  // Budowa struktury
  const header = document.createElement('div');
  header.className = 'nbt-cal-header';

  const navPrevWrapper = document.createElement('div');
  navPrevWrapper.className = 'nbt-cal-nav';

  const btnPrev = document.createElement('button');
  btnPrev.type = 'button';
  btnPrev.innerHTML = '‹ Poprzedni';

  navPrevWrapper.appendChild(btnPrev);

  const monthLabel = document.createElement('div');
  monthLabel.className = 'nbt-cal-month-label';

  const navNextWrapper = document.createElement('div');
  navNextWrapper.className = 'nbt-cal-nav';

  const btnNext = document.createElement('button');
  btnNext.type = 'button';
  btnNext.innerHTML = 'Następny ›';

  navNextWrapper.appendChild(btnNext);

  header.appendChild(navPrevWrapper);
  header.appendChild(monthLabel);
  header.appendChild(navNextWrapper);

  // Dni tygodnia
  const weekdaysRow = document.createElement('div');
  weekdaysRow.className = 'nbt-cal-weekdays';

  weekdays.forEach(function (d) {
    const el = document.createElement('div');
    el.className = 'nbt-cal-weekday';
    el.textContent = d;
    weekdaysRow.appendChild(el);
  });

  const grid = document.createElement('div');
  grid.className = 'nbt-cal-grid';

  container.appendChild(header);
  container.appendChild(weekdaysRow);
  container.appendChild(grid);

  // Render kalendarza
  function renderCalendar(year, month) {
    monthLabel.textContent = (months[month] + ' ' + year).toUpperCase();

    grid.innerHTML = '';

    const firstDay = new Date(year, month, 1);
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    // 0 = niedziela, 1=pon,..., więc przesunięcie na poniedziałek jako pierwszy
    let startWeekday = firstDay.getDay(); // 0-6
    if (startWeekday === 0) {
      startWeekday = 7;
    }
    const offset = startWeekday - 1; // ile pustych przed 1.

    // puste komórki
    for (let i = 0; i < offset; i++) {
      const cell = document.createElement('div');
      cell.className = 'nbt-cal-day nbt-cal-day-empty';
      grid.appendChild(cell);
    }

    const today = new Date();
    const todayY = today.getFullYear();
    const todayM = today.getMonth();
    const todayD = today.getDate();

    // dni miesiąca
    for (let day = 1; day <= daysInMonth; day++) {
      const cellDateStr = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');

      const cell = document.createElement('div');
      cell.className = 'nbt-cal-day';

      if (year === todayY && month === todayM && day === todayD) {
        cell.classList.add('nbt-cal-day-today');
      }

      const num = document.createElement('div');
      num.className = 'nbt-cal-day-number';
      num.textContent = day;
      cell.appendChild(num);

      const eventsForDay = nbtCalendarEvents.filter(function (ev) {
        return ev.date === cellDateStr;
      });

      if (eventsForDay.length > 0) {
        const list = document.createElement('ul');
        list.className = 'nbt-cal-events';

        eventsForDay.forEach(function (ev) {
          const li = document.createElement('li');
          const a = document.createElement('a');
          a.href = ev.link;
          a.textContent = ev.title;
          a.className = 'nbt-cal-event-link';
          li.appendChild(a);
          list.appendChild(li);
        });

        cell.appendChild(list);
      }

      grid.appendChild(cell);
    }
  }

  function changeMonth(delta) {
    const newDate = new Date(currentDate);
    newDate.setMonth(currentDate.getMonth() + delta);
    currentDate = newDate;
    renderCalendar(currentDate.getFullYear(), currentDate.getMonth());
  }

  btnPrev.addEventListener('click', function () {
    changeMonth(-1);
  });

  btnNext.addEventListener('click', function () {
    changeMonth(1);
  });

  // start
  renderCalendar(currentDate.getFullYear(), currentDate.getMonth());
});
