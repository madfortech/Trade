'use strict';

export const _csrf = () =>
    document.querySelector('meta[name="csrf-token"]')?.content || '';

export const _el = id =>
    document.getElementById(id);

export const _show = id => {
    const e = _el(id);
    if (!e) return;

    e.classList.remove('hidden');
    e.style.display = e.dataset.display || 'flex';
};

export const _hide = id => {
    const e = _el(id);
    if (!e) return;

    e.classList.add('hidden');
    e.style.display = 'none';
};

export const _esc = s =>
    String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

export const _tnow = () =>
    new Date().toLocaleTimeString('en-IN', {
        hour: '2-digit',
        minute: '2-digit',
    });