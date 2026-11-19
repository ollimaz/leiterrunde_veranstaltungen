/* SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const { mountApp, h, badge, toast, formatDate, formatMoney, put, lrPageHeader, field, formActions, emptyState, checkField, personPicker, lrLocale, listView, persistForm, menuButton, BADGE_LABELS } = window.LeiterrundeUI;
const { t: _t, n: _n } = window.LeiterrundeUI.l10n('leiterrunde_veranstaltungen');

const APP = 'leiterrunde_veranstaltungen';

const NAV = [
	{ route: '#/liste', label: _t('Events'), cap: 'events.read', icon: 'calendar' },
	{ route: '#/termine', label: _t('Termine'), cap: 'events.read', icon: 'clock' },
];

async function viewListe(v, ME, { api }) {
	const events = await api('/events');
	const canWrite = ME.caps['events.write'];
	const lv = listView({
		scope: 'events',
		search: { placeholder: _t('Search events …'), text: e => [e.title, e.location].filter(Boolean).join(' ') },
		filters: [
			{ key: 'status', label: _t('Status'), options: [{ value: '', label: _t('All') }, ...['draft', 'published', 'cancelled'].map(s => ({ value: s, label: BADGE_LABELS[s] || s }))],
				apply: (e, val) => e.status === val },
		],
		sorts: [
			{ key: 'date', label: _t('Date'), value: e => e.start_date || '' },
			{ key: 'title', label: _t('Title'), value: e => e.title || '' },
		],
		headers: [_t('Title'), _t('Date'), _t('Location'), _t('Status'), _t('Sync')],
		row: (e) => h('tr', {},
			h('td', {}, h('a', { href: '#/detail/' + e.id }, e.title)),
			h('td', {}, formatDate(e.start_date)), h('td', {}, e.location || ''),
			h('td', {}, badge(e.status)),
			h('td', {}, e.sync_pending ? badge('pending') : (e.last_sync_status || '–'))),
		empty: _t('No events.'),
	});
	lv.setRows(events);
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Events'), title: _t('Events'),
			actions: canWrite ? [h('button', { class: 'lr-btn', onclick: () => location.hash = '#/neu' }, _t('New event'))] : [] }),
		lv.el);
}

function eventForm(v, ME, { api }) {
	const titleI = h('input', {}), start = h('input', { type: 'date' }), end = h('input', { type: 'date' }),
		loc = h('input', {}), fee = h('input', { type: 'number', step: '0.01' }), cap = h('input', { type: 'number' }),
		dl = h('input', { type: 'date' }), desc = h('textarea', { rows: 3 });
	const fields = h('textarea', { rows: 5 });
	api('/settings').then(s => { fields.value = s.default_reg_fields || '[]'; });
	const form = h('div', { class: 'lr-form lr-formgrid' },
		field(_t('Title'), titleI, 8), field(_t('Location'), loc, 4),
		field(_t('Start'), start, 3), field(_t('End'), end, 3),
		field(_t('Registration deadline'), dl, 3), field(_t('Capacity'), cap, 3),
		field(_t('Fee (EUR)'), fee, 3),
		field(_t('Registration fields (JSON)'), fields, 12),
		field(_t('Description'), desc, 12),
		formActions(h('button', { class: 'lr-btn', onclick: async () => {
			try {
				const e = await api('/events', { method: 'POST', json: { title: titleI.value, start_date: start.value, end_date: end.value || null, location: loc.value || null, fee: fee.value ? +fee.value : null, capacity: cap.value ? +cap.value : null, reg_deadline: dl.value || null, reg_fields: fields.value, description: desc.value || null } });
				draft.clear(); toast(_t('Created'), false); location.hash = '#/detail/' + e.id;
			} catch (e) { toast(e.message); }
		} }, _t('Create')),
		h('button', { class: 'lr-btn secondary', onclick: () => location.hash = '#/liste' }, _t('Cancel'))));
	const draft = persistForm(form, APP + '.event-new');
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Events'), title: _t('New event') }), form);
}

async function viewDetail(v, ME, { api, coreApi }) {
	const id = location.hash.split('/')[2];
	const e = await api('/events/' + id);
	const canWrite = ME.caps['events.write'];
	const tabs = h('div', { class: 'lr-tabs' });
	const content = h('div', {});
	const show = (name, fn) => {
		content.textContent = '';
		fn(content);
		for (const b of tabs.children) b.className = (b.textContent === name ? 'active' : '');
	};
	const defs = [
		[_t('Details'), (c) => dataTab(c, e, canWrite, api)],
		[_t('Team'), (c) => teamTab(c, e, canWrite, api, coreApi)],
		[_t('Budget'), (c) => calcTab(c, e, canWrite, api)],
		[_t('Registrations'), (c) => regsTab(c, e, api, canWrite)],
	];
	for (const [n, fn] of defs) tabs.append(h('button', { onclick: () => show(n, fn) }, n));
	const del = async () => {
		if (!confirm(_t('Delete event "{title}"? This cannot be undone.', { title: e.title }))) return;
		try { await api('/events/' + id, { method: 'DELETE' }); toast(_t('Deleted'), false); location.hash = '#/liste'; }
		catch (er) { toast(er.message); }
	};
	const publish = async () => {
		try {
			const r = await api('/events/' + id + '/publish', { method: 'POST', json: {} });
			toast(r.ok ? _t('Published') : (_t('Sync error: ') + r.error), !r.ok);
			viewDetail(v, ME, { api, coreApi });
		} catch (er) { toast(er.message); }
	};
	const regs = await api('/registrations?event_id=' + id).catch(() => []);
	const regN = regs.filter(r => r.status !== 'cancelled').length;
	const syncSt = e.last_sync_status ? ((String(e.last_sync_status) === 'ok' || String(e.last_sync_status).startsWith('OK')) ? ['ok',_t('Sent')] : ['fail',_t('Error')]) : ['missing',_t('Not published')];
	put(v, lrPageHeader({
			kicker: _t('Leiterrunde · Events'),
			title: e.title,
			lede: [formatDate(e.start_date) + ' – ' + formatDate(e.end_date || e.start_date), e.location || '', e.capacity ? _t('{reg} of {cap} places taken', { reg: regN, cap: e.capacity }) : ''].filter(Boolean).join(' · ') + ' · ' + syncSt[1] + (e.last_pushed_at ? ', ' + _t('last') + ' ' + formatDate(new Date(e.last_pushed_at * 1000).toISOString().slice(0,10)) + ' ' + new Date(e.last_pushed_at * 1000).toTimeString().slice(0,5) : ''),
			actions: canWrite ? [
				e.sync_pending ? h('button', { class: 'lr-btn secondary', onclick: publish }, _t('Retry')) : null,
				h('button', { class: 'lr-btn', onclick: publish }, _t('Publish to website')),
				h('button', { class: 'lr-btn quiet', onclick: del }, _t('Delete')),
			].filter(Boolean) : [],
		}),
		tabs, content);
	show(_t('Details'), (c) => dataTab(c, e, canWrite, api));
}

async function dataTab(c, e, canWrite, api) {
	const inputs = {};
	const form = h('div', { class: 'lr-form' });
	const wrap = (k, l, span, type) => {
		inputs[k] = h('input', { value: e[k] ?? '', type: type || 'text', readonly: !canWrite });
		return h('div', { class: 'lr-f col-' + span }, h('label', { class: 'lr-flabel' }, l), inputs[k]);
	};
	form.className = 'lr-form lr-formgrid';
	const feeWrap = wrap('fee', _t('Fee'), 3);
	const suggested = h('span', { class: 'lr-muted lr-small' });
	feeWrap.append(suggested);
	form.append(wrap('title', _t('Title'), 8), wrap('location', _t('Location'), 4),
		wrap('start_date', _t('Start'), 3, 'date'), wrap('end_date', _t('End'), 3, 'date'),
		wrap('reg_deadline', _t('Registration deadline'), 3, 'date'), wrap('capacity', _t('Capacity'), 3),
		feeWrap);
	api('/events/' + e.id + '/calculation').then(cal => {
		suggested.textContent = cal.suggested_fee !== null ? _t('Suggestion from budget: ') + formatMoney(cal.suggested_fee) : '';
	}).catch(() => {});
	const desc = h('textarea', { rows: 3, readonly: !canWrite }, e.description || '');
	const rf = h('textarea', { rows: 5, readonly: !canWrite, class: 'lr-mono' }, e.reg_fields || '[]');
	form.append(h('div', { class: 'lr-f col-12' }, h('label', { class: 'lr-flabel' }, _t('Description')), desc),
		h('div', { class: 'lr-f col-12' }, h('label', { class: 'lr-flabel' }, _t('Registration fields (JSON)')), rf));
	if (canWrite) {
		form.append(formActions(h('button', { class: 'lr-btn', onclick: async () => {
			try {
				const r = await api('/events/' + e.id, { method: 'PUT', json: {
					title: inputs.title.value, start_date: inputs.start_date.value,
					end_date: inputs.end_date.value || null, location: inputs.location.value || null,
					fee: inputs.fee.value ? +inputs.fee.value : null, capacity: inputs.capacity.value ? +inputs.capacity.value : null,
					reg_deadline: inputs.reg_deadline.value || null, reg_fields: rf.value, description: desc.value || null,
				}});
				if (r.sync_result && !r.sync_result.ok) toast(_t('Saved, sync error: ') + r.sync_result.error);
				else toast(_t('Saved'), false);
				c.textContent = ''; dataTab(c, await api('/events/' + e.id), canWrite, api);
			} catch (err) { toast(err.message); }
		} }, _t('Save'))));
	}
	put(c, form);
}

async function teamTab(c, e, canWrite, api, coreApi) {
	const [team, persons, roles] = await Promise.all([
		api('/events/' + e.id + '/team'),
		coreApi('/persons/options').catch(() => []),
		api('/events/team-roles').catch(() => []),
	]);
	const name = (t) => t.person_name || (() => { const p = persons.find(x => x.id === t.person_id); return p ? p.last_name + ', ' + p.first_name : '#' + t.person_id; })();
	const reload = () => teamTab(c, e, canWrite, api, coreApi);
	let pSel = null;
	const roleI = h('input', { list: 'lr-roles' });
	const add = h('button', { class: 'lr-btn', onclick: async () => {
		if (!pSel) { toast(_t('Please select a person')); return; }
		try { await api('/events/' + e.id + '/team', { method: 'POST', json: { person_id: +pSel, role: roleI.value || null } }); reload(); }
		catch (er) { toast(er.message); }
	} }, _t('Add'));
	const remove = async (t) => {
		if (!confirm(_t('Remove {name} from the team?', { name: name(t) }))) return;
		try { await api('/events/' + e.id + '/team/' + t.id, { method: 'DELETE' }); reload(); }
		catch (er) { toast(er.message); }
	};
	put(c, h('datalist', { id: 'lr-roles' }, ...roles.map(r => h('option', { value: r }))),
		canWrite ? h('div', { class: 'lr-form lr-formgrid' },
			field(_t('Person'), window.LeiterrundeUI.personPicker({ persons, onChange: (id) => { pSel = id; } }), 6),
			field(_t('Role'), roleI, 4),
			formActions(add)) : null,
		h('table', { class: 'lr-table' },
			h('thead', {}, h('tr', {}, h('th', {}, _t('Person')), h('th', {}, _t('Role')), h('th', {}, _t('Supervisor check')), canWrite ? h('th', {}, '') : null)),
			h('tbody', {}, ...team.map(t => h('tr', {},
				h('td', {}, name(t)), h('td', {}, t.role || ''),
				h('td', {}, t.check ? badge(t.check.overall, 'overall') : '–'),
				canWrite ? h('td', { class: 'lr-actions' }, h('button', { class: 'lr-btn quiet', onclick: () => remove(t) }, _t('Remove'))) : null)))));
}

async function calcTab(c, e, canWrite, api) {
	const [calc, costs] = await Promise.all([api('/events/' + e.id + '/calculation'), api('/events/' + e.id + '/costs')]);
	const reload = () => calcTab(c, e, canWrite, api);
	const label = h('input', {}), amount = h('input', { type: 'number', step: '0.01', min: '0', inputmode: 'decimal' }), cat = h('input', {});
	const add = h('button', { class: 'lr-btn', onclick: async () => {
		if (!label.value.trim() || amount.value === '') { toast(_t('Please enter a label and an amount')); return; }
		try { await api('/events/' + e.id + '/costs', { method: 'POST', json: { label: label.value, amount: +amount.value, category: cat.value || null } }); reload(); }
		catch (er) { toast(er.message); }
	} }, _t('Add cost item'));
	const remove = async (k) => {
		if (!confirm(_t('Delete cost item "{label}"?', { label: k.label }))) return;
		try { await api('/events/' + e.id + '/costs/' + k.id, { method: 'DELETE' }); reload(); }
		catch (er) { toast(er.message); }
	};
	put(c,
		h('p', {}, _t('Total: {total} · Capacity: {cap} · Suggested: {suggested} · Fee: {fee}', { total: formatMoney(calc.total), cap: calc.capacity ?? '–', suggested: calc.suggested_fee !== null ? formatMoney(calc.suggested_fee) : '–', fee: calc.fee !== null ? formatMoney(calc.fee) : '–' })),
		calc.actual ? h('p', {}, _t('Booked: income {income} · outgoing {outgoing} · committed {committed}', { income: formatMoney(calc.actual.income), outgoing: formatMoney(calc.actual.outgoing), committed: formatMoney(calc.actual.committed) })) : null,
		calc.actual && calc.actual.books.length ? h('table', { class: 'lr-table' },
			h('thead', {}, h('tr', {}, h('th', {}, _t('Book')), h('th', { class: 'num' }, _t('Income')), h('th', { class: 'num' }, _t('Outgoing')), h('th', { class: 'num' }, _t('Committed')), h('th', { class: 'num' }, _t('Balance')))),
			h('tbody', {}, ...calc.actual.books.map(b => h('tr', {},
				h('td', {}, b.name), h('td', { class: 'num' }, formatMoney(b.income)), h('td', { class: 'num' }, formatMoney(b.outgoing)),
				h('td', { class: 'num' }, formatMoney(b.committed)), h('td', { class: 'num' }, formatMoney(b.balance)))))) : null,
		canWrite ? h('div', { class: 'lr-form lr-formgrid' },
			field(_t('Label'), label, 6), field(_t('Amount'), amount, 3), field(_t('Category'), cat, 3),
			formActions(add)) : null,
		h('table', { class: 'lr-table' },
			h('thead', {}, h('tr', {}, h('th', {}, _t('Label')), h('th', { class: 'num' }, _t('Amount')), h('th', {}, _t('Category')), canWrite ? h('th', {}, '') : null)),
			h('tbody', {}, ...costs.map(k => h('tr', {},
				h('td', {}, k.label), h('td', { class: 'num' }, formatMoney(k.amount)), h('td', {}, k.category || ''),
				canWrite ? h('td', { class: 'lr-actions' }, h('button', { class: 'lr-btn quiet', onclick: () => remove(k) }, _t('Delete'))) : null)))));
}

async function regsTab(c, e, api, canWrite) {
	let rows = [];
	try { rows = await api('/registrations?event_id=' + e.id); }
	catch { put(c, h('p', {}, _t('No access to registrations.'))); return; }
	const reload = () => regsTab(c, e, api, canWrite);
	const patch = async (r, body) => {
		try { await api('/registrations/' + r.id, { method: 'PUT', json: body }); toast(_t('Saved'), false); reload(); }
		catch (er) { toast(er.message); reload(); }
	};
	put(c, rows.length ? h('table', { class: 'lr-table' },
		h('thead', {}, h('tr', {}, h('th', {}, _t('Name')), h('th', {}, _t('Email')), h('th', {}, _t('Status')), h('th', {}, _t('Payment')), h('th', {}, _t('Reference')), h('th', {}, _t('Obligation')), canWrite ? h('th', {}, '') : null)),
		h('tbody', {}, ...rows.map(r => h('tr', {},
			h('td', {}, r.name), h('td', {}, r.email || ''),
			canWrite
				? h('td', {}, h('select', { onchange: (ev) => patch(r, { status: ev.target.value }) },
					...['active', 'cancelled'].map(s => h('option', { value: s, selected: r.status === s }, badge(s === 'active' ? 'aktiv' : 'cancelled').textContent))))
				: h('td', {}, badge(r.status === 'active' ? 'aktiv' : 'cancelled')),
			canWrite
				? h('td', {}, h('select', { onchange: (ev) => patch(r, { payment_state: ev.target.value }) },
					...['open', 'paid', 'waived'].map(s => h('option', { value: s, selected: r.payment_state === s }, s))))
				: h('td', {}, badge(r.payment_state)),
			h('td', {}, r.payment_ref || ''),
			h('td', {}, r.obligation_id ? '#' + r.obligation_id : '–'),
			canWrite ? h('td', { class: 'lr-actions' }, h('button', { class: 'lr-btn quiet', onclick: async () => {
				if (!confirm(_t('Delete registration for {name}?', { name: r.name }))) return;
				try { await api('/registrations/' + r.id, { method: 'DELETE' }); toast(_t('Deleted'), false); reload(); }
				catch (er) { toast(er.message); }
			} }, _t('Delete'))) : null))))
		: emptyState(_t('No registrations.')));
}

/* ---- Termine (internal recurring appointments) ---- */

const WD = [...Array(7)].map((_, i) => new Date(2024, 0, 1 + i).toLocaleDateString(lrLocale(), { weekday: 'long' }));
const POS = { 1: _t('1st'), 2: _t('2nd'), 3: _t('3rd'), 4: _t('4th'), '-1': _t('Last') };

const ruleLabel = (t) => {
	switch (t.freq) {
		case 'weekly': return _t('Weekly · {wd}', { wd: WD[(t.weekday || 1) - 1] });
		case 'monthly_weekday': return _t('Monthly · {pos} {wd}', { pos: POS[t.month_pos] || t.month_pos, wd: WD[(t.weekday || 1) - 1] });
		case 'monthly_date': return _t('Monthly · day {d}', { d: t.month_day });
		default: return _t('Once');
	}
};
const timeLabel = (t) => t.all_day ? _t('All day') : (t.start_time || '') + (t.end_time ? '–' + t.end_time : '');
const audLabel = (t, roles) => {
	if (t.audience_roles === undefined && t.audience_persons === undefined) return '–';
	const rs = JSON.parse(t.audience_roles || '[]'), ps = JSON.parse(t.audience_persons || '[]');
	if (!rs.length && !ps.length) return _t('Editors only');
	return rs.map(r => _t(roles[r] || r)).concat(ps.length ? [_n('%n person', '%n persons', ps.length)] : []).join(', ');
};

async function viewTermine(v, ME, { api }) {
	const [termine, meta] = await Promise.all([api('/termine'), api('/termine/audience-meta').catch(() => ({ roles: {} }))]);
	const canWrite = ME.caps['events.write'];
	const lv = listView({
		scope: 'termine',
		search: { placeholder: _t('Search appointments …'), text: t => [t.title, t.location].filter(Boolean).join(' ') },
		sorts: [
			{ key: 'next', label: _t('Next date'), value: t => t.next_dates[0] || '9999' },
			{ key: 'title', label: _t('Title'), value: t => t.title || '' },
		],
		headers: [_t('Title'), _t('Next date'), _t('Time'), _t('Repetition'), _t('Audience')],
		row: (t) => h('tr', {},
			h('td', {}, h('a', { href: '#/termin/' + t.id }, t.title)),
			h('td', {}, t.next_dates[0] ? formatDate(t.next_dates[0]) : _t('past')),
			h('td', {}, timeLabel(t)),
			h('td', {}, ruleLabel(t)),
			h('td', { class: 'lr-muted' }, audLabel(t, meta.roles))),
		empty: _t('No appointments.'),
	});
	lv.setRows(termine);
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Termine'), title: _t('Termine'),
		actions: canWrite ? [h('button', { class: 'lr-btn', onclick: () => location.hash = '#/termin-neu' }, _t('New appointment'))] : [] }),
		lv.el);
}

function terminForm(t, meta, persons) {
	const audRoles = JSON.parse(t.audience_roles || '[]');
	const audPersons = (JSON.parse(t.audience_persons || '[]') || []).map(Number);
	const titleI = h('input', { value: t.title || '' });
	const loc = h('input', { value: t.location || '' });
	const desc = h('textarea', { rows: 3 }, t.description || '');
	const start = h('input', { type: 'date', value: t.start_date || '' });
	const until = h('input', { type: 'date', value: t.recur_until || '' });
	const allDay = h('input', { type: 'checkbox' });
	allDay.checked = t.all_day !== false && t.all_day !== 0;
	const sTime = h('input', { type: 'time', value: t.start_time || '' });
	const eTime = h('input', { type: 'time', value: t.end_time || '' });
	const freq = h('select', {}, ...[['once', _t('Once')], ['weekly', _t('Weekly')], ['monthly_weekday', _t('Monthly · by weekday')], ['monthly_date', _t('Monthly · by date')]]
		.map(([val, l]) => h('option', { value: val, selected: (t.freq || 'once') === val }, l)));
	const wdSel = h('select', {}, ...WD.map((n, i) => h('option', { value: i + 1, selected: t.weekday === i + 1 }, n)));
	const posSel = h('select', {}, ...[1, 2, 3, 4, -1].map(p => h('option', { value: p, selected: t.month_pos === p }, POS[p])));
	const mDay = h('input', { type: 'number', min: 1, max: 31, value: t.month_day || '' });
	const wdField = field(_t('Weekday'), wdSel, 3);
	const posField = field(_t('Position'), posSel, 3);
	const mdayField = field(_t('Day of month'), mDay, 3);
	const untilField = field(_t('Recurring until (optional)'), until, 3);
	const timeField = field(_t('Time'), h('span', {}, sTime, ' – ', eTime), 4);
	const applyFreq = () => {
		const f = freq.value;
		wdField.style.display = (f === 'weekly' || f === 'monthly_weekday') ? '' : 'none';
		posField.style.display = (f === 'monthly_weekday') ? '' : 'none';
		mdayField.style.display = (f === 'monthly_date') ? '' : 'none';
		untilField.style.display = (f === 'once') ? 'none' : '';
	};
	freq.addEventListener('change', applyFreq); applyFreq();
	const applyDay = () => { timeField.style.display = allDay.checked ? 'none' : ''; };
	allDay.addEventListener('change', applyDay); applyDay();

	const roleIds = Object.keys(meta.roles);
	const roleBoxes = roleIds.map(r => { const c = h('input', { type: 'checkbox' }); c.checked = audRoles.includes(r); return c; });
	const chips = h('div', { class: 'lr-chips' });
	const pname = (pid) => { const p = persons.find(x => +x.id === +pid); return p ? p.last_name + ', ' + p.first_name : '#' + pid; };
	const renderChips = () => {
		chips.textContent = '';
		for (const pid of audPersons) chips.append(h('span', { class: 'lr-chip' }, pname(pid),
			h('button', { class: 'lr-chip-x', type: 'button', onclick: () => { audPersons.splice(audPersons.indexOf(pid), 1); renderChips(); } }, '×')));
	};
	renderChips();
	const picker = personPicker({ persons, onChange: (id) => { if (id && !audPersons.includes(+id)) { audPersons.push(+id); renderChips(); } } });

	const el = h('div', { class: 'lr-form lr-formgrid' },
		field(_t('Title'), titleI, 8), field(_t('Location'), loc, 4),
		field(_t('Date / series start'), start, 3), untilField,
		h('div', { class: 'lr-f col-2' }, h('label', { class: 'lr-flabel' }, ' '), checkField(_t('All day'), allDay)),
		timeField,
		field(_t('Repetition'), freq, 4), wdField, posField, mdayField,
		h('div', { class: 'lr-f col-12' },
			h('label', { class: 'lr-flabel' }, _t('Audience')),
			h('p', { class: 'lr-small lr-muted' }, _t('Visible to selected roles and people only. With nothing selected only editors can see this appointment.')),
			h('div', {}, ...roleIds.map((r, i) => h('label', { class: 'lr-check', style: 'display:inline-flex;margin:0 14px 6px 0' }, roleBoxes[i], ' ' + _t(meta.roles[r])))),
			h('div', { style: 'max-width:340px' }, picker), chips),
		field(_t('Description'), desc, 12));

	const collect = () => {
		const body = {
			title: titleI.value, location: loc.value || null, description: desc.value || null,
			start_date: start.value, all_day: allDay.checked, freq: freq.value,
			audience_roles: JSON.stringify(roleIds.filter((r, i) => roleBoxes[i].checked)),
			audience_persons: JSON.stringify(audPersons),
		};
		if (!allDay.checked) { body.start_time = sTime.value || null; body.end_time = eTime.value || null; }
		if (freq.value === 'weekly' || freq.value === 'monthly_weekday') body.weekday = +wdSel.value;
		if (freq.value === 'monthly_weekday') body.month_pos = +posSel.value;
		if (freq.value === 'monthly_date') body.month_day = +mDay.value;
		if (freq.value !== 'once') body.recur_until = until.value || null;
		return body;
	};
	return { el, collect };
}

async function viewTerminNeu(v, ME, { api, coreApi }) {
	const [meta, persons] = await Promise.all([
		api('/termine/audience-meta'),
		coreApi('/persons/options').catch(() => []),
	]);
	const { el, collect } = terminForm({ freq: 'once', all_day: true }, meta, persons);
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Termine'), title: _t('New appointment') }), el,
		formActions(
			h('button', { class: 'lr-btn', onclick: async () => {
				try {
					const t = await api('/termine', { method: 'POST', json: collect() });
					toast(_t('Created'), false); location.hash = '#/termin/' + t.id;
				} catch (e) { toast(e.message); }
			} }, _t('Create')),
			h('button', { class: 'lr-btn secondary', onclick: () => location.hash = '#/termine' }, _t('Cancel'))));
}

async function viewTermin(v, ME, ctx) {
	const { api, coreApi } = ctx;
	const id = location.hash.split('/')[2];
	const t = await api('/termine/' + id);
	const canWrite = ME.caps['events.write'];
	const dates = h('ul', {}, ...(t.next_dates || []).map(d => h('li', {}, formatDate(d))));
	if (!canWrite) {
		put(v, lrPageHeader({ kicker: _t('Leiterrunde · Termine'), title: t.title,
			lede: [ruleLabel(t), timeLabel(t), t.location || ''].filter(Boolean).join(' · ') }),
			t.description ? h('p', {}, t.description) : null,
			h('h3', { class: 'lr-subhead' }, _t('Next dates')), dates);
		return;
	}
	const [meta, persons] = await Promise.all([
		api('/termine/audience-meta'),
		coreApi('/persons/options').catch(() => []),
	]);
	const { el, collect } = terminForm(t, meta, persons);
	const del = async () => {
		if (!confirm(_t('Delete appointment "{title}"? This cannot be undone.', { title: t.title }))) return;
		try { await api('/termine/' + id, { method: 'DELETE' }); toast(_t('Deleted'), false); location.hash = '#/termine'; }
		catch (er) { toast(er.message); }
	};
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Termine'), title: t.title,
		lede: ruleLabel(t),
		actions: [h('button', { class: 'lr-btn quiet', onclick: del }, _t('Delete'))] }),
		h('h3', { class: 'lr-subhead' }, _t('Next dates')), dates, el,
		formActions(h('button', { class: 'lr-btn', onclick: async () => {
			try {
				await api('/termine/' + id, { method: 'PUT', json: collect() });
				toast(_t('Saved'), false); viewTermin(v, ME, ctx);
			} catch (er) { toast(er.message); }
		} }, _t('Save'))));
}

mountApp({
	appId: APP,
	title: _t('Events'),
	nav: NAV,
	routes: {
		'#/liste': viewListe,
		'#/neu': eventForm,
		'#/detail': viewDetail,
		'#/termine': viewTermine,
		'#/termin-neu': viewTerminNeu,
		'#/termin': viewTermin,
	},
	defaultRoute: '#/liste',
});
