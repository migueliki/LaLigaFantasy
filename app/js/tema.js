(function () {
    if (typeof window.__llSetTema === 'function') {
        return;
    }

    window.__llSetTema = function (tema) {
        var expiresAt = new Date();
        expiresAt.setTime(expiresAt.getTime() + (30 * 24 * 60 * 60 * 1000));

        var secure = location.protocol === 'https:' ? ';Secure' : '';
        document.cookie = 'preferencia_tema=' + tema + ';expires=' + expiresAt.toUTCString() + ';path=/;SameSite=Lax' + secure;
        location.reload();
    };
}());