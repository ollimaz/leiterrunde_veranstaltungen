(function(){
const t = (s, v) => window.t('leiterrunde_veranstaltungen', s, v, undefined, { escape: false, sanitize: false });
(async () => {
	const root = document.getElementById('lr-veranstaltungen-settings');
	if (!root) return;
	const base = OC.generateUrl('/apps/leiterrunde_veranstaltungen/api');
	const req = async (p, o = {}) => (await fetch(base + p, { headers: { requesttoken: OC.requestToken }, ...o })).json();
	const s = await req('/settings');
	const form = document.getElementById('lr-sync-form');
	const url = Object.assign(document.createElement('input'), { value: s.remote_url || '', placeholder: 'https://…' });
	const urlHint = Object.assign(document.createElement('p'), { className: 'settings-hint', textContent: s.remote_url && !s.remote_url.startsWith('https://') ? t('Note: http allowed in debug mode only.') : '' });
	const fields = Object.assign(document.createElement('textarea'), { rows: 5, value: s.default_reg_fields || '[]' });
	const fieldsHint = Object.assign(document.createElement('p'), { className: 'settings-hint' });
	fields.addEventListener('input', () => {
		try { JSON.parse(fields.value); fieldsHint.textContent = t('JSON valid'); fieldsHint.style.color = 'var(--color-element-success)'; }
		catch { fieldsHint.textContent = t('JSON invalid'); fieldsHint.style.color = 'var(--color-element-error)'; }
	});
	form.append(
		Object.assign(document.createElement('label'), { textContent: t('Website URL (https)') }), url, urlHint,
		Object.assign(document.createElement('label'), { textContent: t('Default registration fields (JSON)') }), fields, fieldsHint);
	// Shared Secret masked
	const secWrap = document.createElement('p');
	const sec = Object.assign(document.createElement('code'), { textContent: '••••••••' });
	const showBtn = Object.assign(document.createElement('button'), { className: 'lr-btn secondary lr-btn-sm', textContent: t('Show') });
	const copyBtn = Object.assign(document.createElement('button'), { className: 'lr-btn secondary lr-btn-sm', textContent: t('Copy') });
	showBtn.addEventListener('click', (e) => { e.preventDefault(); sec.textContent = s.shared_secret || t('(empty)'); });
	copyBtn.addEventListener('click', async (e) => { e.preventDefault(); await navigator.clipboard.writeText(s.shared_secret || ''); });
	const regen = Object.assign(document.createElement('button'), { className: 'lr-btn secondary lr-btn-sm', textContent: t('Regenerate') });
	regen.addEventListener('click', async (e) => {
		e.preventDefault();
		if (!confirm(t('Really regenerate? The secret must then also be updated in WordPress.'))) return;
		const r = await req('/settings/secret/regenerate', { method: 'POST', headers: { requesttoken: OC.requestToken } });
		if (r.shared_secret) { s.shared_secret = r.shared_secret; sec.textContent = r.shared_secret; }
	});
	secWrap.append(document.createTextNode(t('Shared secret: ')), sec, ' ', showBtn, ' ', copyBtn, ' ', regen);
	form.append(secWrap);
	const msg = document.createElement('span'); msg.style.marginLeft = '8px';
	document.getElementById('lr-sync-msg').replaceWith(msg);
	document.getElementById('lr-sync-save').addEventListener('click', async () => {
		const r = await req('/settings', { method: 'PUT', headers: { requesttoken: OC.requestToken, 'Content-Type': 'application/json' },
			body: JSON.stringify({ remote_url: url.value, default_reg_fields: fields.value }) });
		msg.textContent = r.error ? ('Error: ' + r.error) : t('Saved');
	});
	document.getElementById('lr-backfill').addEventListener('click', async () => {
		const d = await req('/backfill-obligations', { method: 'POST', headers: { requesttoken: OC.requestToken } });
		msg.textContent = d.ok ? (d.created + ' ' + t('payments created')) : ('Error: ' + (d.error || ''));
	});
})();

})();
