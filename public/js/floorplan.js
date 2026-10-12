/*
 * Floor plan editor (admin) and interactive map (public). Ported from the Nutmeg app.
 * Reads its data from <script type="application/json" id="fp-data">.
 * Units are feet; the SVG draws 16 units per foot.
 */
(function () {
  'use strict';
  var dataEl = document.getElementById('fp-data');
  if (!dataEl) return;
  var D = JSON.parse(dataEl.textContent);
  var ADMIN = D.mode === 'admin';
  var FT = 16, SNAP = 0.5;
  var PALETTE = ['#FF6A2B', '#4A3BB5', '#1F7A5C', '#E8336D', '#1F8FD6', '#C2781A', '#7B2FF7'];
  var POIS = ['Check-in', 'Restrooms', 'Food & drinks', 'Raffle', 'Kids trade table', 'Grading drop-off', 'Stage', 'ATM', 'First aid', 'Info booth', 'Vendor load-in'];
  var KINDS = D.kinds || {};

  var $ = function (id) { return document.getElementById(id); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (m) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]; }); };
  var money = function (c) { return '$' + (c % 100 === 0 ? (c / 100).toLocaleString('en-US') : (c / 100).toFixed(2)); };
  var snap = function (v) { return Math.round(v / SNAP) * SNAP; };
  var lc = function (s) { return String(s).toLowerCase(); };
  var uid = function () { return 'e' + Math.random().toString(36).slice(2, 10); };
  function textOn(hex) {
    var c = hex.replace('#', ''), r = parseInt(c.substr(0, 2), 16), g = parseInt(c.substr(2, 2), 16), b = parseInt(c.substr(4, 2), 16);
    return (0.299 * r + 0.587 * g + 0.114 * b) > 165 ? '#1D1533' : '#FFFFFF';
  }
  function initials(n) {
    return String(n).split(/\s+/).filter(function (w) { return /[A-Za-z0-9]/.test(w.charAt(0)); }).slice(0, 2).map(function (w) { return w.charAt(0).toUpperCase(); }).join('') || '?';
  }
  function overlaps(a, b) { return a.x < b.x + b.w && b.x < a.x + a.w && a.y < b.y + b.h && b.y < a.y + a.h; }

  /* ---------- state ---------- */
  var types = {}, typeOrder = [];
  if (ADMIN) {
    D.types.forEach(function (t, i) { types[t.id] = { id: t.id, name: t.name, price: t.price, quantity: t.quantity, committed: t.committed, color: PALETTE[i % PALETTE.length] }; typeOrder.push(t.id); });
  } else {
    Object.keys(D.types).forEach(function (id, i) { var t = D.types[id]; types[id] = { id: +id, name: t.name, price: t.price, available: t.available, color: PALETTE[i % PALETTE.length] }; typeOrder.push(+id); });
  }
  var S = {
    w: +(D.room.w || 60), d: +(D.room.d || 40), aisle: +(D.room.aisle || 0), pub: !!D.room.public,
    items: (D.elements || []).map(function (e) { var c = Object.assign({}, e); c.app = null; return c; })
  };
  var apps = ADMIN ? D.apps : [];
  var appById = {};
  var conflicts = {};      // appId -> [numbers taken by someone else]
  function indexApps() {
    appById = {}; conflicts = {};
    apps.forEach(function (a) { appById[a.id] = a; });
  }
  function sellable(it) { return ADMIN ? !!(KINDS[it.kind] && KINDS[it.kind][1]) : it.num != null; }
  function byNum(n) { n = lc(n); for (var i = 0; i < S.items.length; i++) if (sellable(S.items[i]) && lc(S.items[i].num) === n) return S.items[i]; return null; }
  function applyAppNumbers() {
    S.items.forEach(function (it) { it.app = null; });
    apps.forEach(function (a) {
      a.extras = [];
      a.numbers.forEach(function (n) {
        var it = byNum(n);
        if (!it) { a.extras.push(n); return; }
        if (it.app && it.app !== a.id) { (conflicts[a.id] = conflicts[a.id] || []).push(n); return; }
        it.app = a.id;
      });
    });
  }
  if (ADMIN) { indexApps(); applyAppNumbers(); }

  var mode = ADMIN ? 'edit' : 'public';
  var zoom = 1, sel = new Set(), drag = null, armed = null, pubSel = null, pubQuery = '', pubCat = null;
  var dirty = false, saving = false, syncCounts = false, history = [], future = [], lastSnap = null;

  /* ---------- helpers ---------- */
  function typeOf(it) { return types[it.type] || null; }
  function colorOf(it) { var t = typeOf(it); return t ? t.color : '#8A84A0'; }
  function placed(a) { return S.items.filter(function (i) { return i.app === a.id; }).map(function (i) { return i.num; }); }
  function nextNum() {
    var m = 0; S.items.forEach(function (i) { var n = parseInt(i.num, 10); if (sellable(i) && !isNaN(n) && String(n) === String(i.num)) m = Math.max(m, n); });
    return String(m + 1);
  }
  function numberTables() {
    var t = S.items.filter(sellable).sort(function (a, b) { return (Math.round(a.y) - Math.round(b.y)) || (a.x - b.x); });
    t.forEach(function (it, i) { it.num = String(i + 1); });
  }
  function label(i) { return sellable(i) ? 'Table ' + i.num : (i.label || (KINDS[i.kind] ? KINDS[i.kind][0] : i.kind)); }
  function defaultType(kind) {
    // 6/8 ft tables use the first type; booths use a type with "booth" in the name if there is one.
    var want = /booth/.test(kind) ? 'booth' : 'table';
    for (var i = 0; i < typeOrder.length; i++) if (lc(types[typeOrder[i]].name).indexOf(want) !== -1) return typeOrder[i];
    return typeOrder[0] || null;
  }

  /** Gap between the long sides of two items that face each other, or null if they don't face. */
  function facingGap(a, b) {
    var MIN_RUN = 3; // feet of facing edge needed before a gap counts as an aisle
    var aH = a.w >= a.h, bH = b.w >= b.h;               // long side runs left-right
    var aV = a.h >= a.w, bV = b.h >= b.w;               // long side runs up-down (squares are both)
    var xRun = Math.min(a.x + a.w, b.x + b.w) - Math.max(a.x, b.x);
    var yRun = Math.min(a.y + a.h, b.y + b.h) - Math.max(a.y, b.y);
    if (aH && bH && xRun >= MIN_RUN) return Math.max(b.y - (a.y + a.h), a.y - (b.y + b.h));
    if (aV && bV && yRun >= MIN_RUN) return Math.max(b.x - (a.x + a.w), a.x - (b.x + b.w));
    return null;
  }

  /* ---------- layout checks ---------- */
  function findIssues() {
    var bad = new Set(), warn = new Set(), msgs = [], it = S.items, A = S.aisle, nums = {};
    it.forEach(function (a) {
      if (a.x < 0 || a.y < 0 || a.x + a.w > S.w + 1e-6 || a.y + a.h > S.d + 1e-6) { bad.add(a.id); msgs.push({ t: 'e', m: label(a) + ' is outside the room' }); }
      if (sellable(a)) {
        var k = lc(a.num || '');
        if (!k) { bad.add(a.id); msgs.push({ t: 'e', m: 'A table has no number' }); }
        else if (nums[k]) { bad.add(a.id); bad.add(nums[k].id); msgs.push({ t: 'e', m: 'Table number ' + a.num + ' is used twice' }); }
        else nums[k] = a;
        if (!typeOf(a)) { warn.add(a.id); msgs.push({ t: 'w', m: label(a) + ' has no price type' }); }
      }
    });
    var soft = function (k) { return k === 'wall' || k === 'door'; };
    for (var i = 0; i < it.length; i++) for (var j = i + 1; j < it.length; j++) {
      var a = it[i], b = it[j];
      if (soft(a.kind) && soft(b.kind)) continue;
      if (overlaps(a, b)) { bad.add(a.id); bad.add(b.id); msgs.push({ t: 'e', m: label(a) + ' overlaps ' + label(b) }); continue; }
      // Aisles are only measured between the fronts/backs of two tables or booths facing each other
      // (two rows across from each other, or back-to-back rows). Gaps between the ends of
      // neighbouring tables in the same row, or at a corner, aren't walkways and are ignored.
      if (A > 0 && sellable(a) && sellable(b)) {
        var aisle = facingGap(a, b);
        if (aisle !== null && aisle > 0.01 && aisle < A) {
          warn.add(a.id); warn.add(b.id);
          msgs.push({ t: 'w', m: 'Aisle between ' + label(a) + ' and ' + label(b) + ' is ' + aisle + ' ft (minimum ' + A + ' ft)' });
        }
      }
    }
    return { bad: bad, warn: warn, msgs: msgs };
  }

  /* ---------- history ---------- */
  function snapshot() { return JSON.stringify({ w: S.w, d: S.d, aisle: S.aisle, pub: S.pub, items: S.items }); }
  function checkpoint() {
    var s = snapshot();
    if (lastSnap === null) { lastSnap = s; return; }
    if (s === lastSnap) return;
    history.push(lastSnap); if (history.length > 80) history.shift();
    future = []; lastSnap = s; setDirty(true);
  }
  function restore(s) { var o = JSON.parse(s); S.w = o.w; S.d = o.d; S.aisle = o.aisle; S.pub = o.pub; S.items = o.items; lastSnap = s; sel.clear(); setDirty(true); syncRoomInputs(); renderAll(true); }
  function undo() { if (!history.length) return; future.push(lastSnap); restore(history.pop()); }
  function redo() { if (!future.length) return; history.push(lastSnap); restore(future.pop()); }

  /* ---------- rendering ---------- */
  function renderAll(fromHistory) {
    renderStats(); renderSVG(); renderSide();
    if (ADMIN) { if (!fromHistory && !drag) checkpoint(); renderPrint(); renderSaveBar(); }
  }

  function renderStats() {
    var el = $('fpStats'); if (!el) return;
    var T = S.items.filter(sellable), taken = T.filter(function (t) { return ADMIN ? t.app : t.v != null; });
    var full = 0, booked = 0;
    T.forEach(function (t) { var ty = typeOf(t); if (ty) { full += ty.price; if (ADMIN ? t.app : t.v != null) booked += ty.price; } });
    var word = T.some(function (t) { return Math.min(t.w, t.h) >= 5; }) ? 'Spaces' : 'Tables';
    var cells;
    if (ADMIN) {
      var toPlace = apps.filter(function (a) { return placed(a).length + a.extras.length < a.qty; }).length;
      var iss = findIssues(), n = iss.msgs.length;
      cells = [[T.length, word + ' on map'], [taken.length, 'Assigned'], [T.length - taken.length, 'Open'], [toPlace, 'Vendors to place'],
        [money(booked), 'Assigned value'], [money(full), 'Sold-out value']];
      el.innerHTML = cells.map(function (c) { return '<div class="fp-stat"><b>' + c[0] + '</b><span>' + c[1] + '</span></div>'; }).join('') +
        (function () {
          var errs = iss.msgs.filter(function (m) { return m.t === 'e'; }).length, tight = iss.msgs.filter(function (m) { return /^Aisle/.test(m.m); }).length, other = n - errs - tight;
          var out = '';
          if (errs) out += '<div class="fp-stat is-issue"><b>' + errs + '</b><span>' + (errs === 1 ? 'Problem' : 'Problems') + ' to fix</span></div>';
          if (tight) out += '<div class="fp-stat is-warn"><b>' + tight + '</b><span>Narrow ' + (tight === 1 ? 'aisle' : 'aisles') + '</span></div>';
          if (other) out += '<div class="fp-stat is-warn"><b>' + other + '</b><span>No price type</span></div>';
          return out || '<div class="fp-stat is-ok"><b>✓</b><span>Layout clear</span></div>';
        })();
    } else {
      el.innerHTML = '<div class="fp-stat"><b>' + taken.length + '</b><span>Vendors on the map</span></div>' +
        (T.length - taken.length ? '<div class="fp-stat"><b>' + (T.length - taken.length) + '</b><span>Open ' + lc(word) + '</span></div>' : '');
    }
    var lg = $('fpLegend');
    if (lg) lg.innerHTML = typeOrder.map(function (id) { var t = types[id]; return '<span><i style="background:' + t.color + ';border-color:' + t.color + '"></i>' + esc(t.name) + ' · ' + money(t.price) + '</span>'; }).join('') +
      (ADMIN ? '<span><i class="lg-open"></i>Light = open</span>' : '<span><i class="lg-open"></i>Open</span>') +
      '<span><i class="lg-poi"></i>Landmark</span>';
  }

  function vendorOf(it) { if (ADMIN) return it.app ? appById[it.app] : null; return it.v != null ? D.vendors[it.v] : null; }
  function vendorName(v) { return v ? v.name : ''; }

  function renderSVG() {
    var svg = $('fpMap'), W = S.w * FT, Dp = S.d * FT, pad = 30;
    svg.setAttribute('viewBox', (-pad) + ' ' + (-pad) + ' ' + (W + pad * 2) + ' ' + (Dp + pad * 2));
    svg.style.width = ((W + pad * 2) * zoom) + 'px';
    var iss = ADMIN ? findIssues() : { bad: new Set(), warn: new Set() };
    var h = '<rect class="fp-room" x="0" y="0" width="' + W + '" height="' + Dp + '" rx="2"/>';
    var x, y;
    for (x = 1; x < S.w; x++) h += '<line class="' + (x % 5 ? 'fp-gl' : 'fp-gl5') + '" x1="' + x * FT + '" y1="0" x2="' + x * FT + '" y2="' + Dp + '"/>';
    for (y = 1; y < S.d; y++) h += '<line class="' + (y % 5 ? 'fp-gl' : 'fp-gl5') + '" x1="0" y1="' + y * FT + '" x2="' + W + '" y2="' + y * FT + '"/>';
    if (ADMIN) {
      var step = S.w > 80 ? 20 : 10;
      for (x = 0; x <= S.w; x += step) h += '<text class="fp-ruler" x="' + x * FT + '" y="-10" text-anchor="middle">' + x + ' ft</text>';
      for (y = step; y <= S.d; y += step) h += '<text class="fp-ruler" x="-8" y="' + (y * FT + 3) + '" text-anchor="end">' + y + '</text>';
    }
    var q = lc(pubQuery.trim()), filtering = !ADMIN && (q || pubCat);
    var matches = function (v) { return v && (!q || lc(v.name).indexOf(q) !== -1 || v.cats.some(function (c) { return lc(c).indexOf(q) !== -1; })) && (!pubCat || v.cats.indexOf(pubCat) !== -1); };
    var order = S.items.slice().sort(function (a, b) { return (a.kind === 'wall' ? 0 : 1) - (b.kind === 'wall' ? 0 : 1); });
    order.forEach(function (it) {
      var x = it.x * FT, y = it.y * FT, w = it.w * FT, hh = it.h * FT, cx = x + w / 2, cy = y + hh / 2;
      var vert = hh > w * 1.3, tr = vert ? ' transform="rotate(-90 ' + cx + ' ' + cy + ')"' : '';
      var g = '';
      if (sellable(it)) {
        var col = colorOf(it), v = vendorOf(it), assigned = !!v;
        var fill = col, fo = assigned ? 1 : 0.16, stroke = col, dash = '', op = 1, txt = assigned ? textOn(col) : '#1D1533';
        if (!ADMIN && !assigned) { fill = '#FFFFFF'; fo = 1; stroke = '#8A84A0'; dash = ' stroke-dasharray="4 3"'; txt = '#6b6480'; }
        if (ADMIN && assigned && appById[it.app] && appById[it.app].status !== 'paid') { fo = 0.55; }
        if (filtering && !matches(v)) op = 0.22;
        g += '<rect x="' + x + '" y="' + y + '" width="' + w + '" height="' + hh + '" rx="3" fill="' + fill + '" fill-opacity="' + fo + '" stroke="' + stroke + '" stroke-width="2"' + dash + '/>';
        var big = Math.min(w, hh) >= 5 * FT;
        if (big && assigned) {
          var nm = vendorName(v); nm = nm.length > 16 ? nm.slice(0, 15) + '…' : nm;
          g += '<text x="' + cx + '" y="' + (cy - 4) + '" text-anchor="middle" font-size="16" font-weight="800" fill="' + txt + '">' + esc(it.num) + '</text>';
          g += '<text x="' + cx + '" y="' + (cy + 14) + '" text-anchor="middle" font-size="11" font-weight="600" fill="' + txt + '">' + esc(nm) + '</text>';
        } else {
          var fs = big ? 15 : 12, lab = assigned ? it.num + ' · ' + initials(vendorName(v)) : it.num;
          g += '<text x="' + cx + '" y="' + (cy + fs * 0.36) + '" text-anchor="middle" font-size="' + fs + '" font-weight="800" fill="' + txt + '"' + tr + '>' + esc(lab) + '</text>';
        }
        var t = typeOf(it);
        var title = 'Table ' + it.num + (t ? ' · ' + t.name : '') + (assigned ? ' · ' + vendorName(v) : ' · open');
        h += '<g class="fp-it is-sell" data-id="' + esc(it.id) + '" opacity="' + op + '"><title>' + esc(title) + '</title>' + g + '</g>';
        if (filtering && matches(v)) h += '<rect class="fp-hit" x="' + (x - 3) + '" y="' + (y - 3) + '" width="' + (w + 6) + '" height="' + (hh + 6) + '" rx="5"/>';
      } else if (it.kind === 'wall') {
        h += '<g class="fp-it" data-id="' + esc(it.id) + '"><rect x="' + x + '" y="' + y + '" width="' + w + '" height="' + hh + '" fill="#1D1533"/></g>';
      } else if (it.kind === 'door') {
        var ly = y - 6 < 0 ? y + hh + 12 : y - 6;
        h += '<g class="fp-it" data-id="' + esc(it.id) + '"><rect x="' + x + '" y="' + y + '" width="' + w + '" height="' + hh + '" fill="#FF6A2B" stroke="#1D1533" stroke-width="1.5"/>' +
          '<text x="' + cx + '" y="' + ly + '" text-anchor="middle" font-size="11" font-weight="700" fill="#1D1533"' + (vert ? tr : '') + '>' + esc(it.label || 'Door') + '</text></g>';
      } else if (it.kind === 'pillar') {
        h += '<g class="fp-it" data-id="' + esc(it.id) + '"><title>Column</title><rect x="' + x + '" y="' + y + '" width="' + w + '" height="' + hh + '" fill="#8A84A0" rx="2"/></g>';
      } else {
        var labl = it.label || '', avail = vert ? hh : w;
        var fs2 = Math.min(13, Math.max(9, avail / Math.max(1, labl.length * 0.62)));
        h += '<g class="fp-it" data-id="' + esc(it.id) + '"><rect x="' + x + '" y="' + y + '" width="' + w + '" height="' + hh + '" rx="6" fill="#FFB938" fill-opacity=".32" stroke="#1D1533" stroke-width="1.5" stroke-dasharray="5 3"/>' +
          '<text x="' + cx + '" y="' + (cy + 4) + '" text-anchor="middle" font-size="' + fs2 + '" font-weight="700" fill="#1D1533"' + tr + '>' + esc(labl) + '</text></g>';
      }
      if (ADMIN) {
        if (iss.bad.has(it.id)) h += '<rect class="fp-bad" x="' + (x - 2) + '" y="' + (y - 2) + '" width="' + (w + 4) + '" height="' + (hh + 4) + '" rx="4"/>';
        else if (iss.warn.has(it.id)) h += '<rect class="fp-warn" x="' + (x - 2) + '" y="' + (y - 2) + '" width="' + (w + 4) + '" height="' + (hh + 4) + '" rx="4"/>';
        if (sel.has(it.id)) h += '<rect class="fp-sel" x="' + (x - 5) + '" y="' + (y - 5) + '" width="' + (w + 10) + '" height="' + (hh + 10) + '" rx="6"/>';
      } else if (pubSel === it.id) {
        h += '<rect class="fp-sel" x="' + (x - 5) + '" y="' + (y - 5) + '" width="' + (w + 10) + '" height="' + (hh + 10) + '" rx="6"/>';
      }
    });
    if (!S.items.length && ADMIN) {
      h += '<text class="fp-empty-hint" x="' + W / 2 + '" y="' + Dp / 2 + '" text-anchor="middle">Set the room size, then add a row of tables from the left.</text>';
    }
    svg.innerHTML = h;
  }

  /* ---------- admin side panel ---------- */
  var STATUS = { paid: ['Paid', 'paid'], approved: ['Awaiting payment', 'approved'], pending: ['To review', 'pending'] };
  function renderSide() {
    var side = $('fpSide');
    if (!ADMIN) return renderPublicSide(side);
    var items = Array.from(sel).map(function (id) { return S.items.find(function (i) { return i.id === id; }); }).filter(Boolean);
    var h = '<div class="fp-group"><h3>' + (items.length > 1 ? items.length + ' items selected' : 'Selected') + '</h3>';
    if (!items.length) {
      h += '<p class="fp-hint">Click something on the floor to edit it. To seat a vendor, click <strong>Place</strong> next to their name, then click an open table.</p>';
    } else if (items.length === 1) {
      var it = items[0];
      if (sellable(it)) {
        h += '<div class="fp-two"><label class="fp-f">Number<input type="text" id="iNum" maxlength="6" value="' + esc(it.num) + '"></label>' +
          '<label class="fp-f">Price type<select id="iType">' + (it.type ? '' : '<option value="">Choose…</option>') + typeOrder.map(function (id) { var t = types[id]; return '<option value="' + id + '"' + (+it.type === id ? ' selected' : '') + '>' + esc(t.name) + ' · ' + money(t.price) + '</option>'; }).join('') + '</select></label></div>' +
          '<label class="fp-f">Vendor<select id="iVendor"><option value="">Open (no vendor)</option>' + apps.map(function (a) { return '<option value="' + a.id + '"' + (it.app === a.id ? ' selected' : '') + '>' + esc(a.name) + ' (' + STATUS[a.status][0].toLowerCase() + ')</option>'; }).join('') + '</select></label>';
        if (it.app) { var a = appById[it.app]; h += '<p class="fp-hint"><a href="' + esc(a.url) + '" target="_blank" rel="noopener">Open ' + esc(a.name) + '’s application ↗</a></p>'; }
      } else if (it.kind === 'poi' || it.kind === 'door') {
        h += '<label class="fp-f">Label<input type="text" id="iLabel" list="fpPoiList" maxlength="60" value="' + esc(it.label || '') + '"></label><datalist id="fpPoiList">' + POIS.map(function (p) { return '<option value="' + esc(p) + '">'; }).join('') + '</datalist>';
      }
      h += '<div class="fp-two"><label class="fp-f">Width (ft)<input type="number" id="iW" step="0.5" min="0.5" value="' + it.w + '"></label>' +
        '<label class="fp-f">Depth (ft)<input type="number" id="iH" step="0.5" min="0.5" value="' + it.h + '"></label></div>' +
        '<p class="fp-hint">' + it.x + ' ft from the left wall, ' + it.y + ' ft from the top.</p>';
    } else {
      var sells = items.filter(sellable);
      if (sells.length) h += '<label class="fp-f">Price type for ' + sells.length + ' ' + (sells.length === 1 ? 'table' : 'tables') + '<select id="mType"><option value="">Choose…</option>' + typeOrder.map(function (id) { return '<option value="' + id + '">' + esc(types[id].name) + '</option>'; }).join('') + '</select></label>';
    }
    if (items.length) {
      h += '<div class="fp-row"><button type="button" class="btn small" id="aRot">Rotate</button><button type="button" class="btn small" id="aDup">Duplicate</button>' +
        (items.some(function (i) { return i.app; }) ? '<button type="button" class="btn small" id="aClr">Remove vendor</button>' : '') +
        '<button type="button" class="btn small danger" id="aDel">Delete</button></div>';
    }
    h += '</div>';

    var iss = findIssues();
    if (iss.msgs.length) {
      h += '<div class="fp-group"><h3>Layout check</h3><ul class="fp-issues">' + iss.msgs.slice(0, 8).map(function (m) { return '<li class="' + (m.t === 'w' ? 'w' : '') + '">' + esc(m.m) + '</li>'; }).join('') +
        (iss.msgs.length > 8 ? '<li>…and ' + (iss.msgs.length - 8) + ' more</li>' : '') + '</ul></div>';
    }

    // vendors
    h += '<div class="fp-group"><h3>Vendors for this event</h3>';
    if (!apps.length) h += '<p class="fp-empty">Confirmed, approved and to-review vendors appear here once they apply.</p>';
    h += '<div class="fp-vlist">' + apps.map(function (a) {
      var p = placed(a), have = p.length + a.extras.length, done = have >= a.qty, t = types[a.type];
      var notes = [];
      p.forEach(function (n) { var it = byNum(n); if (it && it.type && +it.type !== a.type && t) notes.push('Table ' + n + ' is a ' + types[it.type].name + '; they booked ' + t.name); });
      if (a.extras.length) notes.push('Table ' + a.extras.join(', ') + ' isn’t on the map (kept as typed)');
      if (conflicts[a.id]) notes.push('Table ' + conflicts[a.id].join(', ') + ' was also given to another vendor; place them again');
      return '<div class="fp-v' + (armed === a.id ? ' is-armed' : '') + '" draggable="true" data-v="' + a.id + '">' +
        '<div class="fp-v-name">' + esc(a.name) + '</div>' +
        '<div class="fp-v-act">' + (done && armed !== a.id ? '<span class="fp-chip is-placed">#' + esc(p.concat(a.extras).join(', #')) + '</span>' : '<button type="button" class="btn small' + (armed === a.id ? ' ok' : '') + '" data-place="' + a.id + '">' + (armed === a.id ? 'Placing…' : 'Place') + '</button>') + '</div>' +
        '<div class="fp-v-meta"><span class="badge ' + STATUS[a.status][1] + '">' + STATUS[a.status][0] + '</span> ' + a.qty + ' × ' + esc(t ? t.name : 'table') + (a.qty > 1 ? ' · ' + have + ' of ' + a.qty + ' placed' : '') + '</div>' +
        (notes.length ? '<div class="fp-v-note">' + notes.map(esc).join('<br>') + '</div>' : '') +
        '</div>';
    }).join('') + '</div></div>';

    // table counts
    var counts = {};
    S.items.forEach(function (i) { if (sellable(i) && i.type) counts[i.type] = (counts[i.type] || 0) + 1; });
    h += '<div class="fp-group"><h3>Table counts</h3>';
    if (!typeOrder.length) h += '<p class="fp-empty">This event has no space types yet. <a href="' + esc(D.editTypesUrl) + '">Add them on the event</a> first; they set the price for each table on the map.</p>';
    var mismatch = false;
    h += typeOrder.map(function (id) {
      var t = types[id], n = counts[id] || 0, off = n !== t.quantity; if (off) mismatch = true;
      return '<div class="fp-count' + (off ? ' is-off' : '') + '"><span class="sw" style="background:' + t.color + '"></span><span class="nm">' + esc(t.name) + '</span><span class="nums">' + n + ' on map · ' + t.quantity + ' for sale' + (t.committed ? ' · ' + t.committed + ' taken' : '') + '</span></div>';
    }).join('');
    if (mismatch) h += '<label class="fp-check"><input type="checkbox" id="syncCounts"' + (syncCounts ? ' checked' : '') + '> When I save, set the number for sale to match the map</label>';
    h += '<p class="fp-hint">Prices and names are edited on the <a href="' + esc(D.editTypesUrl) + '">event page</a>.</p></div>';

    side.innerHTML = h;
    bindSide();
  }

  function one() { return S.items.find(function (i) { return i.id === Array.from(sel)[0]; }); }
  function bindSide() {
    var on = function (id, ev, fn) { var n = $(id); if (n) n.addEventListener(ev, fn); };
    on('iNum', 'change', function (e) { var v = e.target.value.replace(/[^A-Za-z0-9-]/g, '').slice(0, 6); if (v) one().num = v; renderAll(); });
    on('iType', 'change', function (e) { one().type = e.target.value ? +e.target.value : null; renderAll(); });
    on('iVendor', 'change', function (e) { var it = one(); if (e.target.value) assign(it, +e.target.value, true); else it.app = null; renderAll(); });
    on('iLabel', 'change', function (e) { one().label = e.target.value.slice(0, 60); renderAll(); });
    on('iW', 'change', function (e) { var it = one(); it.w = Math.max(0.5, Math.min(S.w, snap(+e.target.value || it.w))); clampItem(it); renderAll(); });
    on('iH', 'change', function (e) { var it = one(); it.h = Math.max(0.5, Math.min(S.d, snap(+e.target.value || it.h))); clampItem(it); renderAll(); });
    on('mType', 'change', function (e) { if (!e.target.value) return; sel.forEach(function (id) { var it = S.items.find(function (i) { return i.id === id; }); if (it && sellable(it)) it.type = +e.target.value; }); renderAll(); });
    on('aRot', 'click', rotateSel); on('aDup', 'click', dupSel); on('aDel', 'click', delSel);
    on('aClr', 'click', function () { sel.forEach(function (id) { var it = S.items.find(function (i) { return i.id === id; }); if (it) it.app = null; }); renderAll(); });
    on('syncCounts', 'change', function (e) { syncCounts = e.target.checked; if (syncCounts) setDirty(true); renderSaveBar(); });
    $('fpSide').querySelectorAll('[data-place]').forEach(function (b) { b.addEventListener('click', function () { arm(armed === +b.dataset.place ? null : +b.dataset.place); }); });
    $('fpSide').querySelectorAll('.fp-v[draggable]').forEach(function (n) {
      n.addEventListener('dragstart', function (e) { e.dataTransfer.setData('text/plain', n.dataset.v); e.dataTransfer.effectAllowed = 'move'; });
    });
  }

  function arm(id) {
    armed = id;
    var a = id ? appById[id] : null;
    $('fpArm').hidden = !a;
    if (a) $('fpArmText').textContent = 'Click an open table for ' + a.name + (a.qty > 1 ? ' (' + (placed(a).length + 1) + ' of ' + a.qty + ')' : '');
    renderSide();
  }
  /** Seats a vendor at a table. With replace, a single-table vendor moves instead of being refused. */
  function assign(it, appId, replace) {
    var a = appById[appId];
    if (!it || !sellable(it) || !a) return false;
    if (it.app === appId) return true;
    var mine = S.items.filter(function (i) { return i.app === appId; });
    if (mine.length + a.extras.length >= a.qty) {
      if (!(replace || a.qty === 1) || !mine.length) { flashMsg(a.name + ' already has ' + a.qty + ' ' + (a.qty === 1 ? 'table' : 'tables') + '. Remove one first.'); return false; }
      mine[mine.length - 1].app = null;
    }
    it.app = appId;
    return true;
  }
  var flashTimer;
  function flashMsg(m) { var n = $('fpToast'); if (!n) return; n.textContent = m; n.hidden = false; clearTimeout(flashTimer); flashTimer = setTimeout(function () { n.hidden = true; }, 4000); }

  /* ---------- admin actions ---------- */
  function clampItem(it) { it.x = Math.max(0, Math.min(S.w - it.w, it.x)); it.y = Math.max(0, Math.min(S.d - it.h, it.y)); }
  function freeSpot(w, h) {
    for (var y = 1; y + h <= S.d; y += 0.5) for (var x = 1; x + w <= S.w; x += 0.5) {
      var box = { x: x - 1, y: y - 1, w: w + 2, h: h + 2 };
      if (!S.items.some(function (o) { return overlaps(box, o); })) return { x: x, y: y };
    }
    return { x: Math.max(0, snap((S.w - w) / 2)), y: Math.max(0, snap((S.d - h) / 2)) };
  }
  function newEl(kind, x, y, extra) {
    var k = KINDS[kind], e = { id: uid(), kind: kind, x: x, y: y, w: k[2], h: k[3], rot: 0, app: null };
    if (k[1]) { e.num = nextNum(); e.type = defaultType(kind); } else e.label = kind === 'poi' ? 'Check-in' : kind === 'door' ? 'Entrance' : '';
    return Object.assign(e, extra || {});
  }
  function addItem(kind) {
    var k = KINDS[kind], p = freeSpot(k[2], k[3]), it = newEl(kind, p.x, p.y);
    S.items.push(it); sel = new Set([it.id]); renderAll();
  }
  function addRow() {
    var kind = $('rowKind').value, n = Math.max(1, Math.min(60, parseInt($('rowN').value, 10) || 1)), gap = Math.max(0, +$('rowGap').value || 0), dir = $('rowDir').value;
    var k = KINDS[kind], w = dir === 'h' ? k[2] : k[3], h = dir === 'h' ? k[3] : k[2];
    var tw = dir === 'h' ? n * w + (n - 1) * gap : w, th = dir === 'h' ? h : n * h + (n - 1) * gap;
    if (tw > S.w || th > S.d) { flashMsg('That row is ' + (dir === 'h' ? tw : th) + ' ft long, which doesn’t fit in the room. Use fewer tables or a bigger room.'); return; }
    var p = freeSpot(tw, th), ids = [];
    for (var i = 0; i < n; i++) {
      var it = newEl(kind, dir === 'h' ? p.x + i * (w + gap) : p.x, dir === 'h' ? p.y : p.y + i * (h + gap), { w: w, h: h, rot: dir === 'h' ? 0 : 90 });
      S.items.push(it); ids.push(it.id);
    }
    sel = new Set(ids); renderAll();
  }
  function rotateSel() {
    sel.forEach(function (id) {
      var it = S.items.find(function (i) { return i.id === id; }); if (!it) return;
      var cx = it.x + it.w / 2, cy = it.y + it.h / 2, t = it.w; it.w = it.h; it.h = t; it.rot = it.rot ? 0 : 90;
      it.x = snap(cx - it.w / 2); it.y = snap(cy - it.h / 2); clampItem(it);
    });
    renderAll();
  }
  function dupSel() {
    var src = Array.from(sel).map(function (id) { return S.items.find(function (i) { return i.id === id; }); }).filter(Boolean);
    if (!src.length) return;
    var minX = Math.min.apply(null, src.map(function (i) { return i.x; })), maxX = Math.max.apply(null, src.map(function (i) { return i.x + i.w; }));
    var minY = Math.min.apply(null, src.map(function (i) { return i.y; })), maxY = Math.max.apply(null, src.map(function (i) { return i.y + i.h; }));
    var dx = 0, dy = maxY - minY + 3;
    if (maxY + dy > S.d) { dy = 0; dx = maxX - minX + 3; if (maxX + dx > S.w) { dx = 1; dy = 1; } }
    var ids = [];
    src.slice().sort(function (a, b) { return (a.y - b.y) || (a.x - b.x); }).forEach(function (s) {
      var c = Object.assign({}, s, { id: uid(), x: snap(s.x + dx), y: snap(s.y + dy), app: null });
      if (sellable(s)) c.num = nextNum();
      clampItem(c); S.items.push(c); ids.push(c.id);
    });
    sel = new Set(ids); renderAll();
  }
  function delSel() { S.items = S.items.filter(function (i) { return !sel.has(i.id); }); sel.clear(); renderAll(); }

  /* ---------- saving ---------- */
  function setDirty(v) { dirty = v; renderSaveBar(); }
  function renderSaveBar() {
    var st = $('fpSaveState'), b = $('fpSave'); if (!st) return;
    b.disabled = saving || (!dirty && !syncCounts);
    b.textContent = saving ? 'Saving…' : 'Save';
    st.textContent = saving ? '' : dirty ? 'Unsaved changes' : (st.dataset.saved ? 'Saved ' + st.dataset.saved : 'No changes');
    st.className = 'fp-save-state' + (dirty ? ' is-dirty' : '');
    $('fpUndo').disabled = !history.length; $('fpRedo').disabled = !future.length;
  }
  function save() {
    if (saving) return;
    var iss = findIssues();
    if (iss.msgs.some(function (m) { return /used twice|no number/.test(m.m); })) { showMessages(false, ['Fix duplicate or missing table numbers before saving. Try “Renumber tables”.']); return; }
    saving = true; renderSaveBar();
    var assignments = {};
    S.items.forEach(function (i) { if (i.app) (assignments[i.app] = assignments[i.app] || []).push(i.num); });
    var payload = {
      width: S.w, depth: S.d, aisle: S.aisle, public: S.pub, sync_counts: syncCounts,
      elements: S.items.map(function (i) { var c = Object.assign({}, i); delete c.app; return c; }),
      assignments: assignments
    };
    var fd = new FormData(); fd.append('_token', D.csrf); fd.append('payload', JSON.stringify(payload));
    fetch(D.saveUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json().catch(function () { return { ok: false, messages: ['The server sent back an unexpected reply (' + r.status + '). Your changes are still here; try saving again.'] }; }); })
      .then(function (res) {
        saving = false;
        if (!res.ok) { showMessages(false, res.messages); renderSaveBar(); return; }
        // refresh vendors and counts from the server's view
        res.types.forEach(function (t) { if (types[t.id]) { types[t.id].quantity = t.quantity; types[t.id].committed = t.committed; } });
        apps = res.apps; indexApps();
        applyAppNumbers();
        syncCounts = false;
        $('fpSaveState').dataset.saved = res.savedAt;
        lastSnap = snapshot(); setDirty(false);
        var nb = $('fpNotifyCount'); if (nb) { nb.textContent = res.unsent; $('fpNotify').hidden = !res.unsent; }
        showMessages(true, res.messages.length ? res.messages : ['Floor plan saved.']);
        renderAll(true);
      })
      .catch(function () { saving = false; renderSaveBar(); showMessages(false, ['Couldn’t reach the server. Check your connection; your changes are still here.']); });
  }
  function showMessages(ok, list) {
    var n = $('fpMsg'); if (!n) return;
    n.className = 'alert ' + (ok ? 'ok' : 'bad');
    n.innerHTML = list.map(esc).join('<br>'); n.hidden = false;
    if (ok) { clearTimeout(n._t); n._t = setTimeout(function () { n.hidden = true; }, 6000); }
  }

  /* ---------- print list ---------- */
  function renderPrint() {
    var p = $('fpPrint'); if (!p) return;
    var rows = S.items.filter(sellable).slice().sort(function (a, b) { var x = parseInt(a.num, 10), y = parseInt(b.num, 10); return (isNaN(x) || isNaN(y)) ? String(a.num).localeCompare(b.num) : x - y; });
    p.innerHTML = '<h2>Table assignments</h2><table><thead><tr><th>Table</th><th>Type</th><th>Vendor</th><th>Status</th></tr></thead><tbody>' +
      rows.map(function (r) { var a = r.app ? appById[r.app] : null, t = typeOf(r); return '<tr><td>' + esc(r.num) + '</td><td>' + esc(t ? t.name : '') + '</td><td>' + esc(a ? a.name : 'Open') + '</td><td>' + esc(a ? STATUS[a.status][0] : '') + '</td></tr>'; }).join('') +
      '</tbody></table>';
  }

  /* ---------- public side panel ---------- */
  function renderPublicSide(side) {
    var cats = {};
    S.items.forEach(function (i) { var v = vendorOf(i); if (v) v.cats.forEach(function (c) { cats[c] = 1; }); });
    cats = Object.keys(cats).sort();
    var it = pubSel ? S.items.find(function (i) { return i.id === pubSel; }) : null;
    var h = '<div class="fp-group"><h3>Find a vendor</h3><input type="search" id="pq" placeholder="Search names or what they sell" aria-label="Search vendors" value="' + esc(pubQuery) + '">' +
      (cats.length ? '<div class="fp-cats">' + cats.map(function (c) { return '<button type="button" class="fp-cat" data-cat="' + esc(c) + '" aria-pressed="' + (pubCat === c) + '">' + esc(c) + '</button>'; }).join('') + '</div>' : '') + '</div>';
    if (it) {
      var v = vendorOf(it), t = typeOf(it);
      var nums = v ? S.items.filter(function (i) { return i.v === it.v; }).map(function (i) { return i.num; }) : [it.num];
      h += v
        ? '<div class="fp-card"><div class="fp-card-top">' + (v.logo ? '<img src="' + esc(v.logo) + '" alt="">' : '<span class="fp-ini">' + esc(initials(v.name)) + '</span>') +
          '<div><div class="fp-tn">' + (nums.length > 1 ? 'Tables ' : 'Table ') + esc(nums.join(' & ')) + '</div><h3>' + esc(v.name) + '</h3></div></div>' +
          (v.cats.length ? '<div class="fp-cats">' + v.cats.map(function (c) { return '<span class="chip">' + esc(c) + '</span>'; }).join('') + '</div>' : '') +
          (v.url ? '<a class="btn small" href="' + esc(v.url) + '">See their profile</a>' : '') + '</div>'
        : '<div class="fp-card"><div class="fp-tn">Table ' + esc(it.num) + '</div><h3>Open' + (t ? ' · ' + esc(t.name) : '') + '</h3>' +
          (t ? '<p>' + money(t.price) + '</p>' : '') + (D.applyUrl ? '<a class="btn small primary" href="' + esc(D.applyUrl) + '">Book a ' + esc(D.space || 'table') + '</a>' : '') + '</div>';
    }
    var q = lc(pubQuery.trim()), seen = {}, list = [];
    S.items.forEach(function (i) {
      var v = vendorOf(i); if (!v) return;
      if (!seen[i.v]) { seen[i.v] = { v: v, idx: i.v, nums: [], first: i.id }; list.push(seen[i.v]); }
      seen[i.v].nums.push(i.num);
    });
    list = list.filter(function (e) { var v = e.v; return (!q || lc(v.name).indexOf(q) !== -1 || v.cats.some(function (c) { return lc(c).indexOf(q) !== -1; })) && (!pubCat || v.cats.indexOf(pubCat) !== -1); })
      .sort(function (a, b) { return a.v.name.localeCompare(b.v.name); });
    h += '<div class="fp-group"><h3>Who’s here</h3><div class="fp-vlist">' +
      (list.length ? list.map(function (e) { return '<button type="button" class="fp-v is-btn" data-goto="' + esc(e.first) + '"><div class="fp-v-name">' + esc(e.v.name) + '</div><div class="fp-v-act"><span class="fp-chip is-placed">#' + esc(e.nums.join(', #')) + '</span></div><div class="fp-v-meta">' + esc(e.v.cats.join(' · ')) + '</div></button>'; }).join('')
        : '<p class="fp-empty">' + (q || pubCat ? 'No vendors match.' : 'Vendors will appear here as they’re confirmed.') + '</p>') + '</div></div>';
    side.innerHTML = h;
    var pq = $('pq');
    pq.addEventListener('input', function (e) { pubQuery = e.target.value; var pos = e.target.selectionStart; renderSVG(); renderPublicSide(side); var n = $('pq'); n.focus(); n.setSelectionRange(pos, pos); });
    side.querySelectorAll('[data-cat]').forEach(function (b) { b.onclick = function () { pubCat = pubCat === b.dataset.cat ? null : b.dataset.cat; renderSVG(); renderPublicSide(side); }; });
    side.querySelectorAll('[data-goto]').forEach(function (b) { b.onclick = function () { selectPublic(b.dataset.goto, true); }; });
  }
  function renderPick(it) {
    var p = $('fpPick'); if (!p) return;
    if (!it) { p.textContent = 'Tap a table to see who’s there.'; return; }
    var v = vendorOf(it), t = typeOf(it);
    if (v) {
      var nums = S.items.filter(function (i) { return i.v === it.v; }).map(function (i) { return i.num; });
      p.innerHTML = '<span><strong>' + (nums.length > 1 ? 'Tables ' : 'Table ') + esc(nums.join(' & ')) + '</strong> · ' + esc(v.name) + '</span>' + (v.url ? '<a class="btn small" href="' + esc(v.url) + '">Profile</a>' : '');
    } else {
      p.innerHTML = '<span><strong>Table ' + esc(it.num) + '</strong> · Open' + (t ? ' · ' + esc(t.name) + ' ' + money(t.price) : '') + '</span>' + (D.applyUrl ? '<a class="btn small primary" href="' + esc(D.applyUrl) + '">Book</a>' : '');
    }
  }
  function selectPublic(id, scroll) {
    pubSel = id; renderSVG(); renderPublicSide($('fpSide'));
    var it = S.items.find(function (i) { return i.id === id; });
    renderPick(it);
    if (it && scroll) scrollToItem(it);
    if (it) { try { history_replace('#t' + it.num); } catch (e) {} }
  }
  function history_replace(h) { if (window.history && window.history.replaceState) window.history.replaceState(null, '', h); }
  function scrollToItem(it) {
    var c = $('fpCanvas'), svg = $('fpMap'), vb = svg.viewBox.baseVal, k = svg.getBoundingClientRect().width / vb.width;
    c.scrollTo({ left: (it.x * FT - vb.x) * k - c.clientWidth / 2, top: (it.y * FT - vb.y) * k - c.clientHeight / 2, behavior: 'smooth' });
  }

  /* ---------- pointer + keys ---------- */
  var svgEl = $('fpMap');
  function ptFt(e) { var p = svgEl.createSVGPoint(); p.x = e.clientX; p.y = e.clientY; var r = p.matrixTransform(svgEl.getScreenCTM().inverse()); return { x: r.x / FT, y: r.y / FT }; }
  function itemAt(target) { var g = target.closest && target.closest('[data-id]'); return g ? S.items.find(function (i) { return i.id === g.getAttribute('data-id'); }) : null; }

  svgEl.addEventListener('pointerdown', function (e) {
    var it = itemAt(e.target);
    if (!ADMIN) { if (it && sellable(it)) selectPublic(it.id, false); return; }
    if (armed) {
      if (it && sellable(it)) {
        if (assign(it, armed, false)) { var a = appById[armed]; renderAll(); arm(placed(a).length + a.extras.length < a.qty ? armed : null); }
      } else if (!it) arm(null);
      return;
    }
    if (!it) { if (!e.shiftKey) { sel.clear(); renderAll(); } return; }
    if (e.shiftKey) { if (sel.has(it.id)) sel.delete(it.id); else sel.add(it.id); renderAll(); return; }
    if (!sel.has(it.id)) sel = new Set([it.id]);
    var orig = new Map();
    sel.forEach(function (id) { var x = S.items.find(function (i) { return i.id === id; }); orig.set(id, { x: x.x, y: x.y, w: x.w, h: x.h }); });
    drag = { start: ptFt(e), orig: orig };
    svgEl.setPointerCapture(e.pointerId);
    renderSVG(); renderSide();
  });
  svgEl.addEventListener('pointermove', function (e) {
    if (!drag) return;
    var p = ptFt(e), dx = snap(p.x - drag.start.x), dy = snap(p.y - drag.start.y);
    var minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
    drag.orig.forEach(function (o) { minX = Math.min(minX, o.x); minY = Math.min(minY, o.y); maxX = Math.max(maxX, o.x + o.w); maxY = Math.max(maxY, o.y + o.h); });
    dx = Math.max(-minX, Math.min(dx, S.w - maxX)); dy = Math.max(-minY, Math.min(dy, S.d - maxY));
    drag.orig.forEach(function (o, id) { var it = S.items.find(function (i) { return i.id === id; }); if (it) { it.x = o.x + dx; it.y = o.y + dy; } });
    renderSVG(); renderStats();
  });
  function endDrag() { if (drag) { drag = null; renderAll(); } }
  svgEl.addEventListener('pointerup', endDrag);
  svgEl.addEventListener('pointercancel', endDrag);
  if (ADMIN) {
    svgEl.addEventListener('dragover', function (e) { e.preventDefault(); });
    svgEl.addEventListener('drop', function (e) {
      e.preventDefault();
      var it = itemAt(e.target), id = +e.dataTransfer.getData('text/plain');
      if (it && id && assign(it, id, true)) renderAll();
    });
    document.addEventListener('keydown', function (e) {
      var tag = (document.activeElement && document.activeElement.tagName) || '';
      var typing = /INPUT|SELECT|TEXTAREA/.test(tag);
      var mod = e.metaKey || e.ctrlKey;
      if (mod && lc(e.key) === 's') { e.preventDefault(); save(); return; }
      if (typing) return;
      if (mod && lc(e.key) === 'z') { e.preventDefault(); if (e.shiftKey) redo(); else undo(); return; }
      if (mod && lc(e.key) === 'y') { e.preventDefault(); redo(); return; }
      if (e.key === 'Escape') { if (armed) arm(null); else { sel.clear(); renderAll(); } return; }
      if (!sel.size || mod) return;
      var k = lc(e.key);
      if (k === 'delete' || k === 'backspace') { e.preventDefault(); delSel(); }
      else if (k === 'r') rotateSel();
      else if (k === 'd') { e.preventDefault(); dupSel(); }
      else if (k.indexOf('arrow') === 0) {
        e.preventDefault(); var st = e.shiftKey ? 5 : SNAP;
        var dx = k === 'arrowleft' ? -st : k === 'arrowright' ? st : 0, dy = k === 'arrowup' ? -st : k === 'arrowdown' ? st : 0;
        sel.forEach(function (id) { var it = S.items.find(function (i) { return i.id === id; }); it.x += dx; it.y += dy; clampItem(it); });
        renderAll();
      }
    });
    window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  }

  /* ---------- static controls ---------- */
  function syncRoomInputs() { if (!ADMIN) return; $('vW').value = S.w; $('vD').value = S.d; $('vA').value = S.aisle; $('vPub').checked = S.pub; }
  function fit() {
    var c = $('fpCanvas'), avail = (c.clientWidth || 700) - 20, total = S.w * FT + 60;
    zoom = Math.max(0.2, Math.min(2, avail / total));
    // On phones, keep tables big enough to tap; the map scrolls sideways inside its box instead.
    if (!ADMIN && zoom < 0.5) zoom = 0.5;
  }
  function bindStatic() {
    $('zIn').onclick = function () { zoom = Math.min(4, zoom * 1.25); renderSVG(); };
    $('zOut').onclick = function () { zoom = Math.max(0.2, zoom / 1.25); renderSVG(); };
    $('zFit').onclick = function () { fit(); renderSVG(); };
    window.addEventListener('resize', function () { fit(); renderSVG(); });
    if (!ADMIN) return;
    var pal = ['table6', 'table8', 'booth10', 'booth1020', 'poi', 'door', 'wall', 'pillar'];
    $('fpPalette').innerHTML = pal.map(function (k) { return '<button type="button" class="fp-add" data-add="' + k + '">' + esc(KINDS[k][0]) + '<small>' + KINDS[k][2] + ' × ' + KINDS[k][3] + ' ft</small></button>'; }).join('');
    $('fpPalette').querySelectorAll('[data-add]').forEach(function (b) { b.onclick = function () { addItem(b.dataset.add); }; });
    $('rowKind').innerHTML = ['table6', 'table8', 'booth10', 'booth1020'].map(function (k) { return '<option value="' + k + '">' + esc(KINDS[k][0]) + '</option>'; }).join('');
    $('rowAdd').onclick = addRow;
    $('renum').onclick = function () { numberTables(); renderAll(); flashMsg('Tables renumbered front to back, left to right. Vendors stay at the same tables.'); };
    syncRoomInputs();
    $('vW').onchange = function (e) { S.w = Math.max(10, Math.min(400, snap(+e.target.value || S.w))); e.target.value = S.w; fit(); renderAll(); };
    $('vD').onchange = function (e) { S.d = Math.max(10, Math.min(400, snap(+e.target.value || S.d))); e.target.value = S.d; renderAll(); };
    $('vA').onchange = function (e) { S.aisle = Math.max(0, Math.min(30, snap(+e.target.value || 0))); e.target.value = S.aisle; renderAll(); };
    $('vPub').onchange = function (e) { S.pub = e.target.checked; renderAll(); };
    var clr = $('fpClear'), armedClear = false;
    clr.onclick = function () {
      if (!armedClear) { armedClear = true; clr.textContent = 'Click again to clear'; setTimeout(function () { armedClear = false; clr.textContent = 'Clear the floor'; }, 3500); return; }
      armedClear = false; clr.textContent = 'Clear the floor'; S.items = []; sel.clear(); renderAll(); flashMsg('Floor cleared. Undo brings it back; nothing is saved until you click Save.');
    };
    $('fpSave').onclick = save;
    $('fpUndo').onclick = undo; $('fpRedo').onclick = redo;
    $('fpPrintBtn').onclick = function () { renderPrint(); window.print(); };
    $('fpArmCancel').onclick = function () { arm(null); };
  }

  bindStatic();
  fit();
  renderAll(true);
  lastSnap = snapshot();
  renderSaveBar();
  if (!ADMIN && /^#t/.test(location.hash)) {
    var it = byNum(decodeURIComponent(location.hash.slice(2)));
    if (it) setTimeout(function () { selectPublic(it.id, true); }, 50);
  }
})();
