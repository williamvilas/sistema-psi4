function paginateDocumentIssuer(callback = null) {
    const form = '#form-search';
    totalFilters(form);
    $('#filterModal').modal('hide');

    sendRequest(
        '/document-issuer/paginate',
        'GET',
        form,
        'json',
        function(response) {
            $('.list-paginate').html(response.html);
            if (isFunction(callback)) {
                callback();
            }
        }
    );
}
