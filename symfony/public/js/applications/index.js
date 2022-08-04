$(document).ready(function() {
  //Инициализация fileinput для предпросмотра файлов
  $('#attachments').fileinput(params);
  $('.filePreview').click(function(event) {
    event.preventDefault();
    var previewId = $(this).data('id');
    $('#attachments').fileinput('zoom', previewId);
  });

  //Выделение всех позиций в заявке
  $('body').on('change', '.material-select-all', function() {
    $(this).closest('table').find('.material-select').prop('checked', $(this).prop('checked'));
    $(this).closest('table').find('.material-select:last').trigger('change');
  });

  //Выделение позиций с клавишей Shift
  $('body').on('click', '.material-select', function(e) {
    //Сбрасываем галочку "Выделить все", если выделение снято
    if (!$(this).prop('checked')) {
      $(this).closest('table').find('.material-select-all').prop('checked', false);
    }

    if (lastChecked && lastChecked.closest('table') != this.closest('table')) {lastChecked = null;}

    if (!lastChecked) {lastChecked = this; return;}

    if (e.shiftKey) {
        var start = $('.material-select').index(this);
        var end = $('.material-select').index(lastChecked);

        $('.material-select').slice(Math.min(start,end), Math.max(start,end)+ 1).prop('checked', lastChecked.checked);
    }

    lastChecked = this;
  });

  //Скрытие/отображение панели управления несколькими строками
  $('.material-select').change(function() {
    if ($('.material-select:checked').length > 0) {
      $('#makeRequest').show('fast');
    } else {
      $('#makeRequest').hide('fast');
    }
  });

  $('#requestBtn').click(function() {
    //Преобразовываем списки ответственных
    $(this).closest('form').find('input[type="hidden"]').attr('name', '');
    $('input[name="material[]"]:checked').each(function() {
      $(this).closest('td').find('.amounts').attr('name', 'amount[]');
      $(this).closest('td').find('.applications').attr('name', 'application[]');
    });
    $(this).closest('form').submit();
  });
});