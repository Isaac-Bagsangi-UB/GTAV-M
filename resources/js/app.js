document.querySelectorAll('[data-live-search]').forEach((form) => {
	const input = form.querySelector('input[name="search"]');
	let timer;

	input.addEventListener('input', () => {
		window.clearTimeout(timer);
		timer = window.setTimeout(() => form.requestSubmit(), 300);
	});
});

document.querySelectorAll('[data-select-all]').forEach((selectAll) => {
	const group = selectAll.dataset.selectAll;
	const items = document.querySelectorAll(`[data-select-item="${group}"]`);

	selectAll.addEventListener('change', () => {
		items.forEach((item) => {
			item.checked = selectAll.checked;
		});
	});

	items.forEach((item) => {
		item.addEventListener('change', () => {
			selectAll.checked = [...items].every((candidate) => candidate.checked);
		});
	});
});

document.querySelectorAll('[data-modal-open]').forEach((button) => {
	const modal = document.getElementById(button.dataset.modalOpen);

	button.addEventListener('click', () => modal?.showModal());
});

document.querySelectorAll('[data-modal-close]').forEach((button) => {
	button.addEventListener('click', () => button.closest('dialog')?.close());
});

document.querySelectorAll('dialog.modal').forEach((modal) => {
	modal.addEventListener('click', (event) => {
		if (event.target === modal) {
			modal.close();
		}
	});
});
