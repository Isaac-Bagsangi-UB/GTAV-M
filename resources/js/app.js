document.querySelectorAll('[data-live-search]').forEach((form) => {
	const input = form.querySelector('input[name="search"]');
	let timer;

	input.addEventListener('input', () => {
		window.clearTimeout(timer);
		timer = window.setTimeout(() => form.requestSubmit(), 300);
	});
});
