(function () {
    'use strict';

    function drawBarChart(canvas, labels, series) {
        const ctx = canvas.getContext('2d');
        const dpr = window.devicePixelRatio || 1;
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width * dpr;
        canvas.height = rect.height * dpr;
        ctx.scale(dpr, dpr);

        const w = rect.width, h = rect.height;
        const padding = { top: 16, right: 8, bottom: 26, left: 8 };
        const chartW = w - padding.left - padding.right;
        const chartH = h - padding.top - padding.bottom;

        const max = Math.max(1, ...series.map(function (s) { return s.value; }));
        const barWidth = chartW / series.length * 0.5;
        const gap = chartW / series.length;

        ctx.clearRect(0, 0, w, h);

        const styles = getComputedStyle(document.documentElement);
        const brand = styles.getPropertyValue('--color-brand').trim() || '#3d8bfd';
        const dim = styles.getPropertyValue('--color-text-faint').trim() || '#6b7789';

        series.forEach(function (s, i) {
            const barH = (s.value / max) * chartH;
            const x = padding.left + gap * i + (gap - barWidth) / 2;
            const y = padding.top + chartH - barH;

            ctx.fillStyle = s.color || brand;
            const r = 4;
            ctx.beginPath();
            ctx.moveTo(x, y + barH);
            ctx.lineTo(x, y + r);
            ctx.arcTo(x, y, x + r, y, r);
            ctx.lineTo(x + barWidth - r, y);
            ctx.arcTo(x + barWidth, y, x + barWidth, y + r, r);
            ctx.lineTo(x + barWidth, y + barH);
            ctx.closePath();
            ctx.fill();

            ctx.fillStyle = dim;
            ctx.font = '11px Inter, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(labels[i] || '', x + barWidth / 2, h - 8);
        });
    }

    window.NovaBankCharts = { drawBarChart: drawBarChart };
})();
