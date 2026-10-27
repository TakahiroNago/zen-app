const questionForm = document.querySelector('.question-form:not([data-account-settings-form], [data-delete-account-form])');
const historyTopicFilter = document.querySelector('[data-history-topic-filter]');
const plansPanel = document.querySelector('.plans-panel');
const accountMenu = document.querySelector('.account-menu');
const accountPopover = accountMenu?.querySelector('.account-popover');
const accountMenuTrigger = accountMenu?.querySelector('.account-menu-trigger');

document.querySelectorAll('[data-auto-grow]').forEach((textarea) => {
	const resizeTextarea = () => {
		textarea.style.height = 'auto';
		textarea.style.height = `${textarea.scrollHeight}px`;
	};

	textarea.addEventListener('input', resizeTextarea);
	resizeTextarea();
});

historyTopicFilter?.addEventListener('change', () => {
	historyTopicFilter.form?.requestSubmit();
});

document.querySelectorAll('[data-local-time]').forEach((timeElement) => {
	const date = new Date(timeElement.dateTime);

	if (Number.isNaN(date.getTime())) {
		return;
	}

	const pad = (value) => String(value).padStart(2, '0');

	timeElement.textContent = `${date.getFullYear()}/${pad(date.getMonth() + 1)}/${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
});

if (plansPanel && accountMenu && accountPopover && accountMenu.classList.contains('is-open')) {
	const dismissAccountPopover = () => {
		accountMenu.classList.remove('is-open');
		accountMenu.classList.add('is-dismissed');
	};

	document.addEventListener('click', (event) => {
		if (!accountPopover.contains(event.target) && !accountMenuTrigger?.contains(event.target)) {
			dismissAccountPopover();
		}
	});

	window.addEventListener('scroll', dismissAccountPopover, { passive: true });
}

const scheduleAutoDismiss = (notice) => {
	window.setTimeout(() => {
		notice.classList.add('is-dismissing');
		window.setTimeout(() => notice.remove(), 250);
	}, 5000);
};

const toastRegions = [...document.querySelectorAll('[data-toast-region]')];
const toastRegion = toastRegions.find((region) => region.hasAttribute('data-account-settings-notice'))
	?? toastRegions[0]
	?? document.createElement('div');

const planChangeDialog = document.querySelector('[data-plan-change-dialog]');

if (planChangeDialog) {
	const planChangeNote = planChangeDialog.querySelector('[data-plan-change-note]');
	const planChangeCancel = planChangeDialog.querySelector('[data-plan-change-cancel]');
	const planChangeConfirm = planChangeDialog.querySelector('[data-plan-change-confirm]');
	const planChangeOptionGroups = [...planChangeDialog.querySelectorAll('[data-plan-change-options]')];
	const planChangeTimingGroups = [...planChangeDialog.querySelectorAll('[data-plan-change-timing]')];
	let pendingPlanChangeForm = null;

	const updatePlanChangeConfirmation = (form) => {
		const confirmationNotes = [];
		if (form.dataset.confirmationNote) {
			confirmationNotes.push(form.dataset.confirmationNote);
		}

		const timingOptions = planChangeDialog.querySelector(
			`[data-plan-change-timing="${form.dataset.planTimingInDialog}"]`,
		);
		const timingOption = timingOptions?.querySelector('input[name="timing"]:checked')
			?? form.querySelector('input[name="timing"]:checked');
		if (timingOption?.dataset.confirmationNote) {
			confirmationNotes.push(timingOption.dataset.confirmationNote);
		}

		const planOptions = planChangeDialog.querySelector(
			`[data-plan-change-options="${form.dataset.planOptionsInDialog}"]`,
		);
		const selectedPlan = planOptions?.querySelector('input[data-plan-dialog-option]:checked');
		if (selectedPlan?.dataset.confirmationNote) {
			confirmationNotes.push(selectedPlan.dataset.confirmationNote);
		}

		if (!form.dataset.planOptionsInDialog) {
			const selectedOption = form.querySelector('input[name="plan"]:checked');
			if (selectedOption?.dataset.confirmationNote) {
				confirmationNotes.push(selectedOption.dataset.confirmationNote);
			}
		}

		if (form.dataset.confirmationPeriodEndLabel && form.dataset.confirmationPeriodEndDate) {
			confirmationNotes.push(`${form.dataset.confirmationPeriodEndLabel}\n${form.dataset.confirmationPeriodEndDate}`);
		}

		planChangeNote.textContent = confirmationNotes.join('\n\n');
	};

	document.querySelectorAll('[data-plan-change-form]').forEach((form) => {
		form.addEventListener('submit', (event) => {
			if (form.dataset.confirmed === 'true') {
				delete form.dataset.confirmed;
				return;
			}

			event.preventDefault();
			pendingPlanChangeForm = form;
			planChangeOptionGroups.forEach((group) => {
				group.hidden = group.dataset.planChangeOptions !== form.dataset.planOptionsInDialog;
			});
			planChangeTimingGroups.forEach((group) => {
				group.hidden = group.dataset.planChangeTiming !== form.dataset.planTimingInDialog;
			});
			updatePlanChangeConfirmation(form);
			planChangeDialog.showModal();
		});
	});

	planChangeDialog.querySelectorAll('input[data-plan-dialog-option]').forEach((option) => {
		option.addEventListener('change', () => {
			if (pendingPlanChangeForm?.dataset.planOptionsInDialog) {
				updatePlanChangeConfirmation(pendingPlanChangeForm);
			}
		});
	});

	planChangeDialog.querySelectorAll('input[data-plan-dialog-timing]').forEach((option) => {
		option.addEventListener('change', () => {
			if (pendingPlanChangeForm) {
				updatePlanChangeConfirmation(pendingPlanChangeForm);
			}
		});
	});

	planChangeCancel.addEventListener('click', () => {
		pendingPlanChangeForm = null;
		planChangeDialog.close();
	});

	planChangeConfirm.addEventListener('click', () => {
		if (!pendingPlanChangeForm) {
			return;
		}

		const form = pendingPlanChangeForm;
		pendingPlanChangeForm = null;
		if (form.dataset.planOptionsInDialog) {
			const planOptions = planChangeDialog.querySelector(
				`[data-plan-change-options="${form.dataset.planOptionsInDialog}"]`,
			);
			const selectedOption = planOptions?.querySelector('input[data-plan-dialog-option]:checked');
			const planInput = form.querySelector('input[name="plan"]');

			if (selectedOption && planInput) {
				planInput.value = selectedOption.value;
			}
		}

		form.dataset.confirmed = 'true';
		planChangeDialog.close();
		form.requestSubmit();
	});
}

if (toastRegions.length === 0) {
	toastRegion.className = 'toast-region';
	toastRegion.setAttribute('data-toast-region', '');
	toastRegion.setAttribute('aria-live', 'polite');
	document.body.append(toastRegion);
} else {
	toastRegions.filter((region) => region !== toastRegion).forEach((region) => {
		while (region.firstChild) {
			toastRegion.append(region.firstChild);
		}

		region.remove();
	});
}

window.showToast = (message, isError = false) => {
	const toast = document.createElement('p');
	toast.className = isError ? 'toast is-error' : 'toast';
	toast.setAttribute('data-auto-dismiss', '');
	toast.setAttribute('role', isError ? 'alert' : 'status');
	toast.textContent = message;
	toastRegion.append(toast);
	scheduleAutoDismiss(toast);
};

document.querySelectorAll('[data-auto-dismiss]').forEach(scheduleAutoDismiss);

const accountSettingsNotice = document.querySelector('[data-account-settings-notice]');

if (accountSettingsNotice) {
	const showNotice = (message, isError = false) => {
		window.showToast(message, isError);
	};

	document.querySelectorAll('[data-account-settings-form]').forEach((form) => {
		form.addEventListener('submit', async (event) => {
			event.preventDefault();

			const submitButton = form.querySelector('button[type="submit"]');
			submitButton.disabled = true;
			form.setAttribute('aria-busy', 'true');

			try {
				const response = await fetch(form.action, {
					method: 'POST',
					body: new FormData(form),
					headers: {
						Accept: 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
					credentials: 'same-origin',
				});
				let data;

				try {
					data = await response.json();
				} catch {
					throw new Error(accountSettingsNotice.dataset.errorMessage);
				}

				if (!response.ok) {
					const firstValidationError = Object.values(data.errors ?? {}).flat()[0];
					throw new Error(firstValidationError ?? data.message ?? accountSettingsNotice.dataset.errorMessage);
				}

				showNotice(data.message);
			} catch (error) {
				showNotice(
					error instanceof Error ? error.message : accountSettingsNotice.dataset.errorMessage,
					true,
				);
			} finally {
				submitButton.disabled = false;
				form.removeAttribute('aria-busy');
			}
		});
	});

	const deleteAccountForm = document.querySelector('[data-delete-account-form]');
	const deleteAccountDialog = document.querySelector('[data-delete-account-dialog]');

	if (deleteAccountForm && deleteAccountDialog) {
		const deleteAccountOpenButton = deleteAccountForm.querySelector('[data-delete-account-open]');
		const deleteAccountPassword = deleteAccountDialog.querySelector('[data-delete-account-password]');
		const deleteAccountError = deleteAccountDialog.querySelector('[data-delete-account-error]');
		const deleteAccountConfirmButton = deleteAccountDialog.querySelector('[data-delete-account-confirm]');

		deleteAccountOpenButton.addEventListener('click', () => {
			deleteAccountError.hidden = true;
			deleteAccountError.textContent = '';
			deleteAccountPassword.value = '';
			deleteAccountDialog.showModal();
			deleteAccountPassword.focus();
		});

		deleteAccountForm.addEventListener('submit', async (event) => {
			event.preventDefault();
			deleteAccountError.hidden = true;
			deleteAccountConfirmButton.disabled = true;
			deleteAccountForm.setAttribute('aria-busy', 'true');

			const verificationData = new FormData(deleteAccountForm);
			verificationData.delete('_method');

			try {
				const response = await fetch(deleteAccountForm.dataset.verifyUrl, {
					method: 'POST',
					body: verificationData,
					headers: {
						Accept: 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
					credentials: 'same-origin',
				});

				if (response.ok) {
					deleteAccountDialog.close();
					deleteAccountForm.submit();
					return;
				}

				let message = accountSettingsNotice.dataset.errorMessage;

				try {
					const data = await response.json();
					message = Object.values(data.errors ?? {}).flat()[0] ?? data.message ?? message;
				} catch {
					// Keep the generic message when the response is not JSON.
				}

				deleteAccountError.textContent = message;
				deleteAccountError.hidden = false;
				deleteAccountPassword.focus();
			} catch {
				deleteAccountError.textContent = accountSettingsNotice.dataset.errorMessage;
				deleteAccountError.hidden = false;
				deleteAccountPassword.focus();
			} finally {
				deleteAccountConfirmButton.disabled = false;
				deleteAccountForm.removeAttribute('aria-busy');
			}
		});

		deleteAccountDialog.querySelector('[data-delete-account-cancel]').addEventListener('click', () => {
			deleteAccountDialog.close();
			deleteAccountPassword.value = '';
			deleteAccountError.hidden = true;
			deleteAccountError.textContent = '';
		});
	}
}

if (questionForm) {
	const submitButton = questionForm.querySelector('button[type="submit"]');
	const submitLabel = questionForm.querySelector('.submit-label');
	const questionInput = questionForm.querySelector('[data-submit-on-enter]');

	questionInput?.addEventListener('keydown', (event) => {
		if (
			event.key !== 'Enter'
			|| event.shiftKey
			|| event.isComposing
			|| event.keyCode === 229
		) {
			return;
		}

		event.preventDefault();
		if (!submitButton.disabled) {
			questionForm.requestSubmit(submitButton);
		}
	});

	questionForm.addEventListener('submit', () => {
		questionForm.classList.add('is-submitting');
		questionForm.setAttribute('aria-busy', 'true');
		submitButton.disabled = true;
		submitLabel.textContent = questionForm.dataset.loadingLabel;
	});
}
