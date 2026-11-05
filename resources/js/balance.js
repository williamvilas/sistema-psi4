function activeUpdateBalance() {
    $('.col-form-update').removeClass('d-none');
    $('.row-dash').addClass('d-none');
}

function undoUpdateBalance() {
    $('.col-form-update').addClass('d-none');
    $('.row-dash').removeClass('d-none');
    calculateRiskBalance();
}

function saveUpdateBalance() {
    sendRequest(
        '/balance',
        'POST',
        '#form-update-balance',
        'json',
        function(response) {
            $('.list-paginate').html(response.html);
            successResponseJson(response.title, response.message);
            $('.card-balance-current').text(response.data.value);
            $('.card-stop-gain').text(response.data.gain);
            $('.card-stop-loss').text(response.data.loss);
            $('.card-number-hits').text(response.data.number_of_hits);
            undoUpdateBalance();
        }
    );
}

function calculateRiskBalance() {
    let risk = parseInt($('#risk').val().split(":")[0]);
    let metaGainInPts = parseInt($('#stop_gain').val());
    let valueGain = metaGainInPts * 0.2;
    let valueLoss = (valueGain / risk);

    $('.text-gain').text('R$ ' + trunceDecimal(valueGain));
    $('.text-loss').text('R$ ' + trunceDecimal(valueLoss));
    simulateBalance();
}

function simulateBalance(){
    sendRequest(
        '/balance/simulate',
        'POST',
        '#form-update-balance',
        'json',
        function(response) {
            $('.list-paginate').html(response.html);

            if (isFunction(callback)) {
                callback();
            }
        }
    );
}
