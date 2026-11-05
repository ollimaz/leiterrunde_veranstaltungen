/* SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const { mountApp, h, badge, toast, formatDate, formatMoney, put, lrPageHeader, field, formActions, emptyState } = window.LeiterrundeUI;
const { t: _t, n: _n } = window.LeiterrundeUI.l10n('leiterrunde_veranstaltungen');

const APP = 'leiterrunde_veranstaltungen';

const NAV = [
	{ route: '#/liste', label: _t('Events'), cap: 'events.read', icon: 'calendar' },
];

async function viewListe(v, ME, { api }) {
	const events = await api('/events');
	const canWrite = ME.caps['events.write'];
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Events'), title: _t('Events'),
			actions: canWrite ? [h('button', { class: 'lr-btn', onclick: () => location.hash = '#/neu' }, _t('New event'))] : [] }),
		events.length ? h('table', { class: 'lr-table' },
			h('thead', {}, h('tr', {}, h('th', {}, _t('Title')), h('th', {}, _t('Date')), h('th', {}, _t('Location')), h('th', {}, _t('Status')), h('th', {}, _t('Sync')))),
			h('tbody', {}, ...events.map(e => h('tr', {},
				h('td', {}, h('a', { href: '#/detail/' + e.id }, e.title)),
				h('td', {}, formatDate(e.start_date)), h('td', {}, e.location || ''),
				h('td', {}, badge(e.status)),
				h('td', {}, e.sync_pending ? badge('pending') : (e.last_sync_status || '–'))))))
			: emptyState(_t('No events.')));
}

function eventForm(v, ME, { api }) {
	const titleI = h('input', {}), start = h('input', { type: 'date' }), end = h('input', { type: 'date' }),
		loc = h('input', {}), fee = h('input', { type: 'number', step: '0.01' }), cap = h('input', { type: 'number' }),
		dl = h('input', { type: 'date' }), desc = h('textarea', { rows: 3 });
	const fields = h('textarea', { rows: 5 });
	api('/settings').then(s => { fields.value = s.default_reg_fields || '[]'; });
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Events'), title: _t('New event') }), h('div', { class: 'lr-form lr-formgrid' },
		field(_t('Title'), titleI, 8), field(_t('Location'), loc, 4),
		field(_t('Start'), start, 3), field(_t('End'), end, 3),
		field(_t('Registration deadline'), dl, 3), field(_t('Capacity'), cap, 3),
		field(_t('Fee (EUR)'), fee, 3),
		field(_t('Registration fields (JSON)'), fields, 12),
		field(_t('Description'), desc, 12),
		formActions(h('button', { class: 'lr-btn', onclick: async () => {
			try {
				const e = await api('/events', { method: 'POST', json: { title: titleI.value, start_date: start.value, end_date: end.value || null, location: loc.value || null, fee: fee.value ? +fee.value : null, capacity: cap.value ? +cap.value : null, reg_deadline: dl.value || null, reg_fields: fields.value, description: desc.value || null } });
				toast(_t('Created'), false); location.hash = '#/detail/' + e.id;
			} catch (e) { toast(e.message); }
		} }, _t('Create')),
		h('button', { class: 'lr-btn secondary', onclick: () => location.hash = '#/liste' }, _t('Cancel')))));
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

mountApp({
	appId: APP,
	title: _t('Events'),
	nav: NAV,
	routes: {
		'#/liste': viewListe,
		'#/neu': eventForm,
		'#/detail': viewDetail,
	},
	defaultRoute: '#/liste',
});
