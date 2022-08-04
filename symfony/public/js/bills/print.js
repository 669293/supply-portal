$(document).ready(function() {
  //Инициализация fileinput для предпросмотра файлов
  $('#attachments').fileinput(params);
  $('.filePreview').click(function(event) {
    event.preventDefault();
    var previewId = $(this).data('id');
    $('#attachments').fileinput('zoom', previewId);
  });

  //Выделение всех позиций в заявке
  $('body').on('change', '.bill-select-all', function() {
    $(this).closest('table').find('.bill-select').prop('checked', $(this).prop('checked'));
    $(this).closest('table').find('.bill-select:last').trigger('change');
    checkButtonState();
  });

  //Выделение позиций с клавишей Shift
  $('body').on('click', '.bill-select', function(e) {
    //Сбрасываем галочку "Выделить все", если выделение снято
    if (!$(this).prop('checked')) {
      $(this).closest('table').find('.bill-select-all').prop('checked', false);
    }

    checkButtonState();
    if (lastChecked && lastChecked.closest('table') != this.closest('table')) {lastChecked = null;}

    if (!lastChecked) {lastChecked = this; return;}

    if (e.shiftKey) {
        var start = $('.bill-select').index(this);
        var end = $('.bill-select').index(lastChecked);

        $('.bill-select').slice(Math.min(start,end), Math.max(start,end)+ 1).prop('checked', lastChecked.checked);
    }

    lastChecked = this;
  });

  //Проверка состояния кнопки отправки формы
  function checkButtonState() {
    if ($('.bill-select:checked').length == 0) {
      $('#sendBtn').prop('disabled', true); 
    } else {
      $('#sendBtn').prop('disabled', false);
    }
  }

  //Отправка формы
  $('#sendBtn').click(function() {
    $('#printBillsForm').submit();
  });
});