/* PINTU - Cost Calculator */
(function(){
  'use strict';

  const card = document.getElementById('calculatorCard');
  const dataEl = document.getElementById('costsData');
  const out = document.getElementById('costBreakdown');
  const totalEl = document.getElementById('totalCost');
  if (!card || !dataEl || !out || !totalEl) return;

  const costType = card.dataset.costType;
  let costs = [];
  try { costs = JSON.parse(dataEl.textContent || '[]'); } catch(e) { costs = []; }
  if (!Array.isArray(costs)) costs = [];

  const qtyP = document.getElementById('qtyPenggugat');
  const qtyT = document.getElementById('qtyTergugat');
  const qtyM = document.getElementById('qtyPemohon');

  [qtyP, qtyT, qtyM].forEach(el => { if (el) el.addEventListener('input', recalc); });

  function rupiah(n){ return 'Rp ' + Math.round(n).toLocaleString('id-ID'); }

  function recalc(){
    const nP = qtyP ? Math.max(1, parseInt(qtyP.value||'1',10)) : 1;
    const nT = qtyT ? Math.max(1, parseInt(qtyT.value||'1',10)) : 1;
    const nM = qtyM ? Math.max(1, parseInt(qtyM.value||'1',10)) : 1;

    let total = 0;
    const rows = costs.map(c => {
      const amt = parseFloat(c.amount||0);
      let qty = 1;
      switch (c.calculation_type) {
        case 'PER_PENGGUGAT': qty = nP; break;
        case 'PER_TERGUGAT':  qty = nT; break;
        case 'PER_PEMOHON':   qty = nM; break;
      }
      const sub = amt * qty;
      total += sub;
      return { desc: c.description, qty, amt, sub, type: c.calculation_type };
    });

    out.innerHTML = rows.length
      ? rows.map(r => `
        <div class="d-flex justify-content-between border-bottom py-1">
          <div>
            <div>${escapeHtml(r.desc)}</div>
            <small class="text-muted">${r.qty} × ${rupiah(r.amt)}</small>
          </div>
          <div class="fw-semibold">${rupiah(r.sub)}</div>
        </div>`).join('')
      : '<p class="text-muted small mb-0">Tidak ada item biaya aktif.</p>';

    totalEl.textContent = rupiah(total);
  }

  function escapeHtml(s){ return String(s==null?'':s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]); }

  recalc();
})();