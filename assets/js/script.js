document.getElementById('incidentForm')?.addEventListener('submit', function(event) {
    const dateInput = this.querySelector('input[name="incident_date"]');
    if (dateInput && new Date(dateInput.value) > new Date()) {
        event.preventDefault();
        alert('Incident date cannot be in the future.');
    }
});
