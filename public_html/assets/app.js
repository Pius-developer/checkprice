// CheckPrice: small enhancements. Every page still works without JavaScript except My basket.

const $ = (s, el = document) => el.querySelector(s);
const naira = n => '₦' + Math.round(n).toLocaleString('en-NG');

// State pickers reload the page when changed
document.querySelectorAll('[data-autosubmit] select').forEach(sel =>
    sel.addEventListener('change', () => sel.form.submit()));

// Copy link buttons
document.querySelectorAll('[data-copy]').forEach(btn => btn.addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(btn.dataset.copy); btn.textContent = 'Copied ✓'; }
    catch { prompt('Copy this link:', btn.dataset.copy); }
}));

// Money inputs: show 72,500 while typing (the server ignores the commas)
document.querySelectorAll('[data-money]').forEach(input => input.addEventListener('input', () => {
    const digits = input.value.replace(/\D/g, '').slice(0, 9);
    input.value = digits ? Number(digits).toLocaleString('en-NG') : '';
}));

// Post form: market suggestions for the chosen state, and a photo preview
const market = $('#market');
if (market) {
    const list = $('#market-list'), state = $('#state');
    let timer;
    const load = () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            const res = await fetch(`/api/markets?state=${encodeURIComponent(state.value)}&q=${encodeURIComponent(market.value)}`);
            const names = res.ok ? await res.json() : [];
            list.replaceChildren(...names.map(n => Object.assign(document.createElement('option'), { value: n })));
        }, 200);
    };
    market.addEventListener('input', load);
    state.addEventListener('change', load);
    load();

    const photo = $('#photo'), preview = $('#photo-preview'), hint = $('#photo-hint');
    photo.addEventListener('change', () => {
        const file = photo.files[0];
        if (!file) return;
        preview.src = URL.createObjectURL(file);
        preview.hidden = false;
        hint.textContent = 'Tap to change photo';
    });
}

// My basket: kept in this browser (localStorage), priced by the server
const basket = $('[data-basket]');
if (basket) {
    const KEY = 'checkprice.basket';
    const read = () => { try { return JSON.parse(localStorage.getItem(KEY)) || {}; } catch { return {}; } };
    const write = items => { try { localStorage.setItem(KEY, JSON.stringify(items)); } catch {} };
    let items = read();
    const stateSel = $('#b-state'), tbody = $('#b-table tbody');

    async function refresh() {
        const slugs = Object.keys(items);
        $('#b-empty').hidden = slugs.length > 0;
        $('#b-table').hidden = slugs.length === 0;
        if (!slugs.length) { $('#b-total').textContent = naira(0); $('#b-change').textContent = ''; $('#b-note').textContent = ''; return; }
        const q = slugs.map(s => `${s}:${items[s]}`).join(',');
        const res = await fetch(`/api/basket?state=${stateSel.value}&items=${encodeURIComponent(q)}`);
        if (!res.ok) return;
        const data = await res.json();
        tbody.replaceChildren(...data.lines.map(line => {
            const tr = document.createElement('tr');
            const change = line.change === null ? '<span class="muted">—</span>'
                : `<span class="chg ${line.change > 0 ? 'up' : line.change < 0 ? 'down' : ''}">${line.change > 0 ? '▲' : line.change < 0 ? '▼' : ''} ${Math.abs(line.change)}%</span>`;
            tr.innerHTML = `<td></td>
                <td><input type="number" min="1" max="99" value="${line.qty}" aria-label="Quantity" class="qty"></td>
                <td class="num">${line.now ? naira(line.now) + (line.scope === 'national' ? ' <span class="muted small">(NG)</span>' : '') : '<span class="muted">no data</span>'}</td>
                <td class="num">${change}</td>
                <td><button class="link-btn" type="button" aria-label="Remove">✕</button></td>`;
            tr.firstElementChild.textContent = line.label;      // text, not HTML
            tr.querySelector('.qty').addEventListener('change', e => { items[line.item] = Math.max(1, Math.min(99, +e.target.value || 1)); write(items); refresh(); });
            tr.querySelector('button').addEventListener('click', () => { delete items[line.item]; write(items); refresh(); });
            return tr;
        }));
        $('#b-total').textContent = naira(data.total);
        const ch = $('#b-change');
        ch.className = 'chg ' + (data.change > 0 ? 'up' : data.change < 0 ? 'down' : '');
        ch.textContent = data.change === null ? '' : `${data.change > 0 ? '▲' : '▼'} ${Math.abs(data.change)}% vs 3 months ago`;
        $('#b-note').textContent = data.complete ? '' : 'Some items have no prices yet, so the total leaves them out.';
    }

    $('#b-add').addEventListener('click', () => {
        const slug = $('#b-item').value;
        items[slug] = (items[slug] || 0) + 1;
        write(items);
        refresh();
    });
    stateSel.addEventListener('change', refresh);
    refresh();
}
