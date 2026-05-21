
<script>
// Global helpers
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Copied to clipboard!', 'success');
    });
}

function showToast(msg, type = 'success') {
    const colors = { success: 'bg-green-500', error: 'bg-red-500', warning: 'bg-yellow-500', info: 'bg-blue-500' };
    const t = document.createElement('div');
    t.className = `fixed bottom-4 right-4 z-50 ${colors[type] || 'bg-gray-800'} text-white px-4 py-3 rounded-lg shadow-xl flex items-center gap-2 text-sm font-medium transition-all`;
    t.innerHTML = `<i class="fas fa-check-circle"></i> ${msg}`;
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 3000);
}

// Flash messages auto-dismiss
document.querySelectorAll('[data-flash]').forEach(el => {
    setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 4000);
});
</script>
</body>
</html>
