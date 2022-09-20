$(document).ready(function() {
  //Инициализация fileinput для предпросмотра файлов
  $('#attachments').fileinput(params);
  $('.filePreview').click(function(event) {
    event.preventDefault();
    var previewId = $(this).data('id');
    $('#attachments').fileinput('zoom', previewId);
  });

  //Инициализируем popover
  var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
  var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
    return new bootstrap.Popover(popoverTriggerEl);
  }); 

  //Возобновление выполнения заявки
  $('#playBtn').click(function() {
    $.redirectPost('/applications/set-status', {'id': id, 'status': 2});
  });

  //Приостановка выполнения заявки
  $('#pauseBtn').click(function() {
    //Вызываем подтверждение
    modalConfirm(function(confirm) {
      if (confirm) {
        $.redirectPost('/applications/set-status', {'id': id, 'status': 4});
      }
    }, 
    'Вы уверены что хотите приостановить выполнение данной заявки?<br />Загрузка счетов и изменение их статусов будет невозможно, пока выполнение не будет возобновлено.',
    'Приостановка выполнения заявки'
    );
  });

  //Выделение всех позиций в заявке
  $('body').on('change', '.material-select-all', function() {
    $(this).closest('table').find('.material-select:visible').prop('checked', $(this).prop('checked'));
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
      $('#setResponsible').show('fast');
      $('#showRequestForm').show('fast');
    } else {
      $('#setResponsible').hide('fast');
      $('#showRequestForm').hide('fast');
    }
  });

  $('#setBtn').click(function() {
    var value = $('#setResponsible option:selected').attr('value');
    if (value !== undefined) {
      $('.material-select:checked').each(function() {
        var row = $(this).closest('tr');
        row.find('select').val(value).change();
        $(this).prop('checked', false);
      });
    }
    $('.material-select-all').prop('checked', false);
    $('#setResponsible').hide('fast');
  });

  //Удаление счета
  $('.remove-bill-btn').click(function() {
    var bid = $(this).data('id');
    var sum = $(this).data('sum');
    var title = $(this).data('name');

    //Вызываем подтверждение
    modalConfirm(function(confirm) {
      if (confirm) {
        $.redirectPost('/applications/bills/remove', {'bid': bid, 'id': id});
      }
    }, 
    'Вы уверены что хотите удалить данный счет?<br /><span class="text-muted">Файл: ' + title + '<br />Сумма: ' + sum + '</span>',
    'Удаление счета'
    );
  });

  //Добавление комментария
  $('.add-comment-btn').click(function() {
    var mid = $(this).data('id');
    var note = '';
    if ($(this).closest('tr').find('.material-note').length != 0) {
      note = $(this).closest('tr').find('.material-note').data('bs-content').replaceAll('<br />', "");
    }
    $('#commentModal input[name="material"]').val(mid);
    $('#commentModal textarea[name="note"]').val(note);
  });

  //Получение материала за наличку
  $('.set-material-cash').click(function() {
    var btn = $(this);
    var mid = $(this).data('id');
    var tr = $(this).closest('tr');

    //Вызываем подтверждение
    modalConfirm(function(confirm) {
      if (confirm) {
        $.post('/applications/set-material-cash', { material: mid })
        .done(function( data ) {
          tr.find('.title-col .bi-x').remove();
          tr.find('.title-col span').removeClass('text-danger').addClass('text-success').after('<i class="bi bi-check text-success"></i>');
          btn.remove();
          tr.find('.set-material-impossible').remove();
        });
      }
    }, 
    'Вы уверены что хотите отметить данный материал в заявке как полученный за наличные?',
    'Получение материала за наличные'
    );
  });

  //Отметка о том что данный материал поставить невозможно
  $('.set-material-impossible').click(function() {
    var btn = $(this);
    var mid = $(this).data('id');
    var tr = $(this).closest('tr');

    //Вызываем подтверждение
    modalConfirm(function(confirm) {
      if (confirm) {
        $.post('/applications/set-material-impossible', { material: mid })
        .done(function( data ) {
          tr.find('.title-col .bi-check').remove();
          tr.find('.title-col span').removeClass('text-success').addClass('text-danger').after('<i class="bi bi-x text-danger"></i>');
          btn.remove();
          tr.find('.set-material-cash').remove();
        });
      }
    }, 
    'Вы уверены что хотите отметить, данный материал невозможно поставить?<br />Если это так, то рекомендуется также добавить комментарий с указанием причин.',
    'Уведомление о невозможности поставки'
    );
  });

  //Функция nl2br
  function nl2br (str) {
    if (typeof str === 'undefined' || str === null) {
        return '';
    }
    return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1<br />$2');
  }

  //Сохранение комментария
  $('#saveCommentBtn').click(function() {
    var form = $('#commentForm');
    var id = $('#commentModal input[name="material"]').val();
    
    formData = form.serialize();
    freezeForm(form);

    //Получаем instance окна
    var commentModal = bootstrap.Modal.getInstance(document.getElementById('commentModal'));

    $.ajax({
      type: form.attr('method'),
      url: form.attr('action'),
      data: formData
    }).done(function(data) {
      if ($.isArray(data) && data[0] == 1) {
        //Все хорошо
        freezeForm(form, false);

        if ($('#commentModal textarea[name="note"]').val() != '') {
          //Добавляем или изменяем комментарий к материалу
          if ($('.material' + id).find('.material-note').length != 0) {
            $('.material' + id).find('.material-note').attr('data-bs-content', nl2br($('#commentModal textarea[name="note"]').val()));
          } else {
            $('.material' + id).find('.title-col').append('<a role="button" class="text-primary material-note" data-bs-html="true" data-bs-toggle="popover" title="Комментарий к позиции" data-bs-content="' + nl2br($('#commentModal textarea[name="note"]').val()) + '"><i class="bi bi-chat-text"></i></a>');
          }

          //Инициализируем popover
          var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
          var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
          }); 
        } else {
          //Удаляем коммент
          $('.material' + id).find('.material-note').remove();
        }

        commentModal.hide();
      } else {
        showFormAlert(form, data[1]);
        freezeForm(form, false);
      }
    });
  });

  //Вывод сообщения об ошибке
  function showFormAlert(form, text) {
    form.find('fieldset').append('<div class="alert alert-danger alert-dismissible fade show mt-4 mb-0 form-message" role="alert">\
      <span>' + text + '</span>\
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>\
    </div>');
  }

  //Отправка формы
  $('#sendBtn').click(function() {
    appForm = $(this).closest('form');

    //Преобразовываем списки ответственных
    $('select[name="mresponsible[]"]').each(function() {
      $(this).closest('td').find('input[type="hidden"]').attr('name', 'mid[]');
      if ($(this).val() == null) {
        $(this).closest('td').find('input[type="hidden"]').attr('name', '');
      }
    });

    formData = appForm.serialize();
    $('#setResponsible').hide('fast');
    freezeForm(appForm);

    $.ajax({
      type: appForm.attr('method'),
      url: appForm.attr('action'),
      data: formData
    }).done(function(data) {
      if ($.isArray(data) && data[0] == 1) {
        //Все хорошо
        location.reload();
        // $.redirectPost('/applications', {'msg': 'Заявка №' + $('input[name="aid"]').val() + ' успешно сохранена', 'bg-color': 'bg-success', 'text-color': 'text-white'});
      } else {
        showFormAlert(appForm, data[1]);
        freezeForm(appForm, false);
      }
    });
  });

  //Подстановка исходной позиции в модальное окно по замене позиции на аналоги
  const exampleModal = document.getElementById('splitModal');
  exampleModal.addEventListener('show.bs.modal', event => {
    const button = event.relatedTarget;
    const num = button.getAttribute('data-bs-num');
    const title = button.getAttribute('data-bs-title');
    const count = button.getAttribute('data-bs-count');
    const unit = button.getAttribute('data-bs-unit');
    const id = button.getAttribute('data-bs-id');

    splitModal.querySelector('#originalTable tbody tr td:nth-child(1)').textContent = num;
    splitModal.querySelector('#originalTable tbody tr td:nth-child(2)').textContent = title;
    splitModal.querySelector('#originalTable tbody tr td:nth-child(3)').textContent = unit;
    splitModal.querySelector('#originalTable tbody tr td:nth-child(4)').textContent = count;
    $('#splitForm input[name="material"]').val(id);
  });

  //Добавление позиции в модальном окне по замене позиции на аналоги
  $('#addRowBtn').click(function() {
    var row = $('#splitTable tbody tr:visible:last').clone();
    row.find('input[type="text"]').val('');
    row.find('input[type="number"]').val('');
    row.find('input[type="checkbox"]').prop('checked', false);
    row.find('input[type="hidden"]').remove();
    row.find('select').val(row.find('select option').filter(function() {return $(this).text() === 'шт';}).first().attr('value'));
    row.find('.delete-row').removeClass('delete-database');
    row.attr('style', 'none');
    row.css('display', 'none');
    row.appendTo('#splitTable tbody').show('fast');
    enumerate();
  });

  //Функция восстановления нумерации в позициях в заявке
  function enumerate() {
    $('#splitTable tbody tr:visible').each(function(index, element) {
      $(this).find('td:first').text(index + 1);
    });
  }

  //Проверка содержимого
  function checkContent(maxIndex = -1) {
    var valid = true;
  
    if (maxIndex == -1) {
      //Определяем максимальный индекс строки
      maxIndex = $('#splitTable tbody tr:visible').length;
    }

    var filledRows = 0;
    $('#splitTable tbody tr:visible').slice(0, maxIndex).each(function(index, element) {
      var row = $(this);

      //Проверяем все до текущего индекса
      if (row.find('input[name="titleContentApp[]"]').val() != '') {
        //Название указано, проверяем указано ли количество
        filledRows++;
        var amount = row.find('input[name="amountContentApp[]"]');
        if (amount.val() == '') {
          valid = false;
          amount.addClass('is-invalid');
        } else {
          amount.removeClass('is-invalid');
        }
      }
    });

    if (filledRows > 0 && valid) {
      $('#saveSplitBtn').prop('disabled', false);
      return true;
    } else {
      $('#saveSplitBtn').prop('disabled', true);
      return false;
    }
  }

  $('body').on('keyup', 'input[name="titleContentApp[]"]', function() {
    var index = $(this).closest('tr').find('td:first').text();

    if ($(this).val() != '') {
      $(this).removeClass('is-invalid');
      $(this).closest('td').find('.invalid-feedback').remove();
    }
    checkContent();
  });

  $('body').on('keyup', 'input[name="amountContentApp[]"]', function() {
    if ($(this).val() != '') {$(this).removeClass('is-invalid');}
    checkContent();
  });

  $('body').on('change', 'input[name="amountContentApp[]"]', function() {
    if ($(this).val() != '') {$(this).removeClass('is-invalid');}
    checkContent();
  });

  //Разделение позиции
  $('#saveSplitBtn').click(function() {
    var form = $('#splitForm');
    var data = {};
    formData = form.serializeArray();
    freezeForm(form);

    $.ajax({
      type: form.attr('method'),
      url: form.attr('action'),
      data: formData
    }).done(function(data) {
      if ($.isArray(data) && data[0] == 1) {
        //Все хорошо
        location.reload();
      } else {
        showFormAlert(form, data[1]);
        freezeForm(form, false);
      }
    });
  });

  //Получаем instance модального окна
  const filterModal = new bootstrap.Modal(document.getElementById('filterModal'));

  //Фильтрация по ответственному
  $('#filterBtn').click(function() {
    //Получаем текущее значение
    var filterResponsible = $('#filterSelect').val();

    $('#materialsTable tbody tr').removeClass('d-none');

    if (filterResponsible != 'Выберите') {
      $('#materialsTable tbody tr').each(function() {
        var tr = $(this);
        if (!tr.hasClass('text-muted')) {
          if ($('#materialsTable').hasClass('supervisor')) {
            if (filterResponsible == 'Не назначен') {
              if (tr.find('select option:selected').html() != 'Выберите') {tr.addClass('d-none');}
            } else {
              if (tr.find('select option:selected').html() != filterResponsible) {tr.addClass('d-none');}
            }
          } else {
            if (tr.find('td:nth-child(11)').text().trim() != filterResponsible) {tr.addClass('d-none');}
          }
        } else {
          tr.addClass('d-none');
        }
      });
      $('#toggleFilterModal i').removeClass('bi-funnel').addClass('bi-funnel-fill');
    } else {
      $('#toggleFilterModal i').removeClass('bi-funnel-fill').addClass('bi-funnel');
    }

    filterModal.hide();
    $('#filterCancelBtn').removeClass('d-none');
  });

  $('#filterCancelBtn').click(function() {
    $('#materialsTable tbody tr').removeClass('d-none');
    $('#toggleFilterModal i').removeClass('bi-funnel-fill').addClass('bi-funnel');
    filterModal.hide();
    $('#filterCancelBtn').addClass('d-none');
  });

  //Добавление сообщения к комментарию
  $('#saveMessageBtn').click(function() {
    //Блокируем форму
    appForm = $(this).closest('form');
    freezeForm(appForm);

    var materials = [];
    $('.material-select:checked').each(function() {
      materials.push($(this).val());
    });

    //Отправляем запрос на добавление комментарий
    $.post('/applications/set-material-message', { materials: JSON.stringify(materials), color: $('input[name="color"]').val(), message: $('input[name="message"]').val() })
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
  });

  //Расставляем блоки с комментариями
  function setMessagesPositions() {
    //Сбрасываем offset
    $('#materialsTable tbody tr').data('offset', 8);

    $('.material-message').each(function() {
      var msg = $(this);
      var mid = msg.data('material');
  
      //Ищем материал
      var material = $('tr.material' + mid);
      if (material.length > 0) {
        var _top = material.offset().top;
        var _offset = material.data('offset');
        material.data('offset', _offset + 8);
        var _left = material.offset().left - _offset;
        var _height = material.height() + 2;
  
        msg.css('top', _top + 'px')
        .css('left', _left + 'px')
        .css('height', _height + 'px');
      }
    });
  }

  setMessagesPositions();

  //Получаем instance модального окна
  const filterMessagesModal = new bootstrap.Modal(document.getElementById('filterMessagesModal'));

  //Фильтрация по сообщениям к материалам
  $('#filterMessagesBtn').click(function() {
    //Получаем текущее значение
    var filter = $('#filterMessagesSelect').val();

    $('#materialsTable tbody tr').removeClass('d-none');

    if (filter != 'Выберите') {
      if (filter != 'Нет заметки') {
        //Отображаем все материалы которые соответстуют заметке
        var ids = getMaterialsIDsByMessage(filter);

        $('#materialsTable tbody tr').addClass('d-none');
        $.each(ids, function(index, value) {
          $('#materialsTable tbody tr').each(function() {
            var tr = $(this);
            if (tr.hasClass('material' + value)) {
              tr.removeClass('d-none');
            }
          });
        });
      } else {
        //Отображаем все материалы кроме тех у которых есть хоть какая то заметка
        var ids = getMaterialsIDsByMessage();
        $('#materialsTable tbody tr').each(function() {
          var tr = $(this);
          if ($.inArray(tr.data('material'), ids) !== -1) {
            tr.addClass('d-none');
          }
        });
      }
    }

    setMessagesPositions();
    filterMessagesModal.hide();
  });

  function getMaterialsIDsByMessage(msg = '') {
    var result = [];

    $('div.material-message').each(function() {
      if (msg == '') {
        result.push($(this).data('material'));
      } else {
        if ($(this).data('bs-content') == msg) {
          result.push($(this).data('material'));
        }
      }
    });

    return result;
  }

  //Формирование запроса поставщикам
  var materials = [];
  var amounts = [];
  var applications = [];

  $('#requestBtn').click(function() {
    $('.material-select:checked').each(function() {
      materials.push($(this).val());
      amounts.push($(this).data('amount'));
      applications.push($(this).data('application'));
    });

    $('#requestForm').html('<input type="hidden" name="typeOfRequest" value="" />').submit();
  });

  $('#requestPdfBtn').click(function() {
    $('.material-select:checked').each(function() {
      materials.push($(this).val());
      amounts.push($(this).data('amount'));
      applications.push($(this).data('application'));
    });

    $('#requestForm').html('<input type="hidden" name="typeOfRequest" value="pdf" />').submit();
  });

  $('#requestForm').submit(function() {
    var form = $(this);

    materials.forEach((element) => {form.append('<input type="hidden" name="material[]" value="' + element + '" />')});
    amounts.forEach((element) => {form.append('<input type="hidden" name="amount[]" value="' + element + '" />')});
    applications.forEach((element) => {form.append('<input type="hidden" name="application[]" value="' + element + '" />')});

    return true;
  });
});