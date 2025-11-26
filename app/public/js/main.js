/**
 * Lógica Global del Sistema COREGEDOC
 * Incluye: Timer de inactividad, Clima, Modales de confirmación
 */

document.addEventListener('DOMContentLoaded', function() {

    // --- 1. Temporizador de Inactividad (60 min) ---
    (function() {
        let inactivityTimer;
        const timeoutDuration = 60 * 60 * 1000; // 60 minutos

        function logoutUser() {
            // Ajuste de ruta para logout
            window.location.href = 'index.php?action=logout';
        }

        function resetTimer() {
            clearTimeout(inactivityTimer);
            inactivityTimer = setTimeout(logoutUser, timeoutDuration);
        }

        // Eventos que resetean el timer
        ['mousemove', 'mousedown', 'keypress', 'touchmove', 'scroll'].forEach(evt => 
            document.addEventListener(evt, resetTimer, false)
        );

        resetTimer();
    })();

    // --- 2. API del Clima (OpenWeatherMap) ---
    const tempElement = document.getElementById('temperatura-actual');
    if (tempElement) {
        const apiKey = '71852032dae024a5eb1702b278bd88fa'; 
        const ciudad = 'La Calera';
        const pais = 'CL';
        const url = `https://api.openweathermap.org/data/2.5/weather?q=${ciudad},${pais}&appid=${apiKey}&units=metric&lang=es`;

        fetch(url)
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (data.main && data.main.temp && data.weather && data.weather[0]) {
                    const temperatura = Math.round(data.main.temp);
                    const descripcion = data.weather[0].description;
                    const iconCode = data.weather[0].icon;

                    let iconoFa = 'fas fa-cloud-sun';
                    // Mapeo simple de iconos
                    if (iconCode.includes('01')) iconoFa = 'fas fa-sun';
                    else if (iconCode.includes('02')) iconoFa = 'fas fa-cloud-sun';
                    else if (iconCode.includes('03') || iconCode.includes('04')) iconoFa = 'fas fa-cloud';
                    else if (iconCode.includes('09') || iconCode.includes('10')) iconoFa = 'fas fa-cloud-showers-heavy';
                    else if (iconCode.includes('11')) iconoFa = 'fas fa-bolt';
                    else if (iconCode.includes('13')) iconoFa = 'fas fa-snowflake';
                    else if (iconCode.includes('50')) iconoFa = 'fas fa-smog';

                    tempElement.innerHTML = `<i class="${iconoFa} me-2"></i> ${temperatura}°C, ${descripcion}`;
                } else {
                    tempElement.textContent = 'Clima no disponible';
                }
            })
            .catch(error => {
                console.error('Error clima:', error);
                tempElement.textContent = 'Sin datos clima';
            });
    }

    // --- 3. Modal de Confirmación (Status en URL) ---
    (function() {
        const params = new URLSearchParams(window.location.search);
        if (!params.has('status') || !params.has('msg')) return;

        const status = params.get('status');
        const msg = decodeURIComponent((params.get('msg') || '').replace(/\+/g, ' '));
        const modalEl = document.getElementById('confirmacionModal');

        if (modalEl && typeof bootstrap !== 'undefined') {
            const headerEl = modalEl.querySelector('.modal-header');
            const titleEl = modalEl.querySelector('#confirmacionModalLabel');
            const bodyEl = modalEl.querySelector('#confirmacionModalMessage');
            const btnFooter = modalEl.querySelector('.modal-footer .btn');

            headerEl.classList.remove('bg-success', 'bg-danger', 'text-white');
            btnFooter.classList.remove('btn-primary', 'btn-danger');

            if (status === 'success') {
                headerEl.classList.add('bg-success', 'text-white');
                titleEl.innerHTML = '<i class="fas fa-check-circle me-2"></i>Operación Exitosa';
                btnFooter.classList.add('btn-primary');
            } else {
                headerEl.classList.add('bg-danger', 'text-white');
                titleEl.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Error';
                btnFooter.classList.add('btn-danger');
            }

            bodyEl.textContent = msg;
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
            
            // Limpiar URL
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    })();
});