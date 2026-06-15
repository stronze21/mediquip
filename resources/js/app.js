import './bootstrap';
import interact from 'interactjs';

window.initPrintLogoDrag = function initPrintLogoDrag() {
    document.querySelectorAll('[data-print-logo-canvas]').forEach((canvas) => {
        if (canvas.dataset.printLogoReady !== '1') {
            canvas.dataset.printLogoReady = '1';
            canvas.printLogoSelected = null;
            canvas.printLogoDragged = false;
        }

        const component = () => {
            const root = canvas.closest('[wire\\:id]');
            const id = root?.getAttribute('wire:id');

            return id && window.Livewire ? window.Livewire.find(id) : null;
        };

        const placeLogo = (logo, clientX, clientY, persist = true) => {
            const canvasRect = canvas.getBoundingClientRect();
            const logoRect = logo.getBoundingClientRect();
            const maxLeft = Math.max(0, canvasRect.width - logoRect.width);
            const maxTop = Math.max(0, canvasRect.height - logoRect.height);
            const left = Math.max(0, Math.min(maxLeft, clientX - canvasRect.left - (logoRect.width / 2)));
            const top = Math.max(0, Math.min(maxTop, clientY - canvasRect.top - (logoRect.height / 2)));

            logo.style.left = `${left}px`;
            logo.style.top = `${top}px`;
            logo.style.transform = 'none';

            const x = (left / canvasRect.width) * 100;
            const y = (top / canvasRect.height) * 100;
            logo.dataset.x = x;
            logo.dataset.y = y;

            if (persist) {
                component()?.call('updateLogoPosition', Number(logo.dataset.logoIndex), x, y);
            }
        };

        if (canvas.dataset.printLogoClickReady !== '1') {
            canvas.dataset.printLogoClickReady = '1';
            canvas.addEventListener('click', (event) => {
                if (!canvas.printLogoSelected || canvas.printLogoDragged || event.target.closest('[data-print-logo]')) {
                    canvas.printLogoDragged = false;
                    return;
                }

                placeLogo(canvas.printLogoSelected, event.clientX, event.clientY);
            });
        }

        canvas.querySelectorAll('[data-print-logo]').forEach((logo) => {
            if (logo.dataset.printLogoReady === '1') {
                return;
            }

            logo.dataset.printLogoReady = '1';

            logo.addEventListener('click', (event) => {
                event.stopPropagation();
                canvas.printLogoSelected = logo;
            });

            interact(logo).unset();
            interact(logo).draggable({
                listeners: {
                    start() {
                        canvas.printLogoSelected = logo;
                        canvas.printLogoDragged = false;
                        logo.dataset.leftPx = logo.offsetLeft;
                        logo.dataset.topPx = logo.offsetTop;
                        logo.style.left = `${logo.offsetLeft}px`;
                        logo.style.top = `${logo.offsetTop}px`;
                        logo.style.transform = 'none';
                    },
                    move(event) {
                        canvas.printLogoDragged = true;
                        const canvasRect = canvas.getBoundingClientRect();
                        const maxLeft = Math.max(0, canvasRect.width - logo.offsetWidth);
                        const maxTop = Math.max(0, canvasRect.height - logo.offsetHeight);
                        const left = Math.max(0, Math.min(maxLeft, Number(logo.dataset.leftPx || 0) + event.dx));
                        const top = Math.max(0, Math.min(maxTop, Number(logo.dataset.topPx || 0) + event.dy));

                        logo.dataset.leftPx = left;
                        logo.dataset.topPx = top;
                        logo.style.left = `${left}px`;
                        logo.style.top = `${top}px`;
                    },
                    end() {
                        const canvasRect = canvas.getBoundingClientRect();
                        const x = (Number(logo.dataset.leftPx || 0) / canvasRect.width) * 100;
                        const y = (Number(logo.dataset.topPx || 0) / canvasRect.height) * 100;

                        logo.dataset.x = x;
                        logo.dataset.y = y;
                        component()?.call('updateLogoPosition', Number(logo.dataset.logoIndex), x, y);

                        setTimeout(() => {
                            canvas.printLogoDragged = false;
                        }, 0);
                    },
                },
            });
        });
    });
};

document.addEventListener('DOMContentLoaded', () => window.initPrintLogoDrag());
document.addEventListener('livewire:navigated', () => window.initPrintLogoDrag());
document.addEventListener('livewire:init', () => {
    window.Livewire?.hook('morph.updated', () => {
        queueMicrotask(() => window.initPrintLogoDrag());
    });
});
