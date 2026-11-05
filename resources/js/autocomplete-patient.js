let timer;
const input = document.getElementById('searchPatient');
const list = document.getElementById('autocomplete-list');
const textAutocomplete = document.getElementById('text-autocomplete');

if (input) {
    input.addEventListener('keyup', function() {

        let query = this.value;

        clearTimeout(timer);

        if (query.length < 2) {
            list.innerHTML = '';
            return;
        }

        timer = setTimeout(() => {

            fetch(`/patients/autocomplete?q=${encodeURIComponent(query)}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(res => {
                    if (isSessionExpiredResponse(res)) {
                        handleSessionExpired();
                        return null;
                    }
                    return res.json();
                })
                .then(data => {
                    if (!data) return;

                    let html = '';
                    if (!data.length) {
                        html = `<div class="list-group-item text-muted list-group-item-action item row-autocomplete">Nenhum paciente encontrado.</div>`;
                    } else {
                        data.forEach(item => {
                            html += `
                            <a class="list-group-item list-group-item-action item row-autocomplete"
                               data-id="${item.id}" data-phone="${item.phone}" data-name="${item.name}">
                               ${item.name} - ${formatWhatsApp(item.phone)}
                            </a>
                        `;
                        });
                    }

                    list.innerHTML = html;

                });

        }, 300);

    });
}
