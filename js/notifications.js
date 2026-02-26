document.addEventListener('DOMContentLoaded', function() {
    const notifBadge = document.getElementById('notif-badge');
    const notifList = document.getElementById('notif-list');
    const notifDropdown = document.getElementById('notifDropdown');
    const viewAllBtn = document.getElementById('notif-view-all');

    function fetchNotifications() {
        fetch('/ILSHD/includes/api_notifications.php')
            .then(response => response.json())
            .then(data => {
                if (notifList) notifList.innerHTML = data.html;
                if (viewAllBtn) viewAllBtn.href = data.viewAll;
                
                if (notifBadge) {
                    if (data.unread > 0) {
                        notifBadge.textContent = data.unread;
                        notifBadge.style.display = 'flex';
                    } else {
                        notifBadge.style.display = 'none';
                    }
                }
            })
            .catch(err => console.error('Error fetching notifications:', err));
    }

    // Initial fetch
    fetchNotifications();

    // Poll every 10 seconds
    setInterval(fetchNotifications, 10000);

    // Mark as read when dropdown opens
    if (notifDropdown) {
        notifDropdown.addEventListener('show.bs.dropdown', function () {
            if (notifBadge) notifBadge.style.display = 'none';
            fetch('/ILSHD/includes/api_notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=mark_read'
            });
        });
    }
});