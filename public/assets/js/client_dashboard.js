function showTab(tabId, btn) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    btn.classList.add('active');
}

function hireEngineer(engineerId, engineerName) {
    if(confirm('Hire ' + engineerName + '?')) {
        alert('Hire request submitted! This feature requires backend implementation.');
    }
}
