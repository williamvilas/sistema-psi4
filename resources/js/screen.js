function registerScreenResolution() {
    if (!window.indexedDB) {
        return;
    }

    const width = window.screen.width;
    const height = window.screen.height;
    const key = `screen-${width}-${height}`;

    const request = window.indexedDB.open('psi-dashboard', 1);

    request.onupgradeneeded = function(event) {
        const database = event.target.result;

        if (!database.objectStoreNames.contains('screen-resolutions')) {
            database.createObjectStore('screen-resolutions', {
                keyPath: 'key'
            });
        }
    };

    request.onsuccess = function(event) {
        const database = event.target.result;
        const transaction = database.transaction(
            'screen-resolutions',
            'readonly'
        );

        const store = transaction.objectStore('screen-resolutions');
        const lookup = store.get(key);

        lookup.onsuccess = function() {
            // Essa resolução já foi enviada.
            if (lookup.result) {
                database.close();
                return;
            }

            $.ajax({
                url: '/screen-resolution',
                type: 'POST',
                dataType: 'json',
                data: {
                    width: width,
                    height: height,
                },
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    const writeTransaction = database.transaction(
                        'screen-resolutions',
                        'readwrite'
                    );

                    writeTransaction
                        .objectStore('screen-resolutions')
                        .put({
                            key: key,
                            width: width,
                            height: height,
                        });

                    writeTransaction.oncomplete = function() {
                        database.close();
                    };

                    writeTransaction.onerror = function() {
                        database.close();
                    };
                },
                error: function() {
                    database.close();
                }
            });
        };

        lookup.onerror = function() {
            database.close();
        };
    };

    request.onerror = function() {
        // Uma falha no armazenamento local não deve impedir o dashboard de carregar.
    };
}
