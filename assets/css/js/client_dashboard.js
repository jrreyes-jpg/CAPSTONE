function openAssignModal(projectId) {
    document.getElementById("assignModal").style.display = "flex";
    document.getElementById("project_id").value = projectId;
}

function closeModal() {
    document.getElementById("assignModal").style.display = "none";
}

window.onclick = function(e) {
    const modal = document.getElementById("assignModal");
    if (e.target === modal) {
        modal.style.display = "none";
    }
}
