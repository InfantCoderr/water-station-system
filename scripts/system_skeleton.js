(function () {
    var startedAt = Date.now();
    var minimumVisibleMs = 1200;
    var fallbackMs = 2200;
    var finished = false;

    function finishLoading() {
        if (finished || !document.body) {
            return;
        }

        finished = true;
        document.body.classList.add('system-loading-done');

        window.setTimeout(function () {
            document.body.classList.remove('system-loading', 'system-loading-done');
        }, 180);
    }

    function finishAfterMinimumTime() {
        var elapsed = Date.now() - startedAt;
        var remaining = Math.max(minimumVisibleMs - elapsed, 0);
        window.setTimeout(finishLoading, remaining);
    }

    if (document.readyState === 'complete') {
        finishAfterMinimumTime();
        return;
    }

    window.addEventListener('load', finishAfterMinimumTime, { once: true });
    window.setTimeout(finishLoading, fallbackMs);
})();
