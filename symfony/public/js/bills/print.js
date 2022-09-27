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
    $('.bill-select:checked').addClass('is-valid');
    $('#printBillsForm').submit();
  });

  //Скрытие/отображение панели управления несколькими строками
  $('.bill-select').change(function() {
    if ($('.bill-select:checked').length > 0) {
      $('#setStatusBlock').show('fast');
    } else {
      $('#setStatusBlock').hide('fast');
    }
  });

  //Изменение статуса нескольких заявок
  $('#setBtn').click(function() {
    if ($('#setStatus').val() != '') {
      //Блокируем форму
      appForm = $(this).closest('form');
      freezeForm(appForm);

      var bills = [];
      $('.bill-select:checked').each(function() {
        bills.push($(this).val());
      });

      //Отправляем запрос на добавление комментарий
      $.post('/applications/bills/set-bill-status', { bills: JSON.stringify(bills), status: $('#setStatus').val(), token: $('input[name="token"]').val() })
      .done(function( data ) {
        if ($.isArray(data) && data[0] == 1) {
          //Все хорошо
          freezeForm(appForm, false);
          location.reload();
        } else {
          showFormAlert(appForm, data[1]);
          freezeForm(appForm, false);
        }
      });
    }
  });

  //Подсказка о количестве выбранных позиций
  $('body').on('change', 'input[name="bills[]"]', function() {
    //Количество выбраных позиций
    var cnt = $('input[name="bills[]"]:checked').length;

    $(this).attr('data-bs-container', 'body');
    $(this).attr('data-bs-toggle', 'popover');
    $(this).attr('data-bs-placement', 'left');
    $(this).attr('data-bs-content',  'Выбрано: ' + cnt);

    //Инициализируем popover
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
      return new bootstrap.Popover(popoverTriggerEl);
    });

    $('.popover').popover('hide');
    $(this).popover('show');
  });
});