$(document).ready(function() {
    // Pré-visualização da imagem
    $('#image').change(function() {
        if (this.files && this.files[0]) {
            var reader = new FileReader();

            reader.onload = function(e) {
                $('#preview').attr('src', e.target.result);
                $('#preview-container').show();
                $('.link-remove-preview').removeClass('d-none');
            }

            reader.readAsDataURL(this.files[0]);
        }
    });

    $('#form-store-strategy').submit(function(e) {
        e.preventDefault();

        var formData = new FormData(this);

        $.ajax({
            url: '/strategies',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            beforeSend: function () {
                $('.btn-save').prop('disabled', true).text('Enviando...');
            },
            success: function(response) {
                return redirectTo('/strategies')
            },
            error: function(xhr, status, error) {

            },
            complete: function() {
                $('.btn-save').prop('disabled', false).text('Enviar');
            }
        });
    });

    $('#form-update-strategy').submit(function(e) {
        e.preventDefault();

        var formData = new FormData(this);
        formData.append('_method', 'PUT');

        $.ajax({
            url: '/strategies/' + $('#id').val(),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            beforeSend: function () {
                $('.btn-save').prop('disabled', true).text('Salvando...');
            },
            success: function(response) {
                return redirectTo('/strategies')
            },
            error: function(xhr, status, error) {

            },
            complete: function() {
                $('.btn-save').prop('disabled', false).text('Salvar');
            }
        });
    });
});

function paginateStrategies(callback = null) {
    sendRequest(
        '/strategies/paginate',
        'GET',
        '#form-search',
        'json',
        function(response) {
            $('.list-paginate').html(response.html);

            if (isFunction(callback)) {
                callback();
            }
        }
    );
}

function removePreviewEdit() {
    $('#remove_image').val('1');
    $('#preview-container').css('display', 'none');
    $('#preview').attr('src', '');
    $('.link-remove-preview').addClass('d-none');
}

function deleteStrategy(id, name) {
    confirmAction('Tem certeza que deseja <strong>apagar</strong> a estratégia <strong>' + name + '</strong>?', function() {
        sendRequestByJson(
            '/strategies',
            'DELETE',
            {
                ids: [id]
            },
            function(response) {
                paginateStrategies(function() {
                    successResponseJson(response.title, response.message);
                });
            },
        )
    }, 'danger', 'Apagar', 'Cancelar');
}
