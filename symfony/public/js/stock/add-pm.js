$(document).ready(function() {
  //Скрытие выбора поставщика при наличном расчете
  $('input[name="add-pm-tax_"]').change(function() {
    if ($(this).val() == -1) {
      $('#provider-row').hide('fast');
      $('#add-pm-provider option:selected').removeAttr('selected');
      $('#add-pm-provider').append('<option value="0" selected></option>');
    } else {
      $('#provider-row').show('fast');
      if ($('#add-pm-provider').val() == '0') {
        $('#add-pm-provider option:selected').removeAttr('selected');
      }
      $('#add-pm-provider').selectpicker('render');

    }
  });

  //Инициализация поля для поиска поставщика
  $('#add-pm-provider').selectpicker().ajaxSelectPicker({
      ajax: {
          url: '/autocomplete/provider', 
          type: 'GET',
          dataType: 'json',
          data: {
          q: '{{{q}}}'
          }
      },
      locale: {
          emptyTitle: 'Введите название или ИНН'
      },
      preserveSelected: false,
      preprocessData: function (data) {
          var i, l = data.length, array = [];
          if (l) {
              for (i = 0; i < l; i++) {
                  array.push($.extend(true, data[i], {
                      text : data[i].Title,
                      value: data[i].Inn,
                      data : {
                          subtext: data[i].Inn
                      }
                  }));
              }
          }

          return array;
      }
  });

  //Автозаполнение поля "Наименование"
  function materialAutocomplete() {
    $('.material-autocomplete').autocomplete({
        serviceUrl: '/autocomplete/material'
    });
  }
  materialAutocomplete();

  //Добавление позиции в заявку
  $('#addRowBtn').click(function() {
    var row = $('#materialsTable tbody tr:visible:last').clone();
    row.find('input[type="text"]').val('').removeClass('freeze-ignore');
    row.find('input[type="number"]').val('').removeClass('freeze-ignore');
    row.find('select').removeClass('freeze-ignore').val(row.find('select option').filter(function() {return $(this).text() === 'шт';}).first().attr('value'));
    row.find('.delete-row').removeClass('delete-database');
    row.attr('style', 'none');
    row.css('display', 'none');
    row.appendTo('#materialsTable tbody').show('fast');
    enumerate();
    materialAutocomplete();
  });

  //Удаление позиций из заявки
  $('body').on('click', '.delete-row', function() {
    if (!$(this).prop('disabled')) {
      if ($(this).hasClass('delete-database')) {
        var row = $(this).closest('tr');
        var btn = $(this);
        var id = $(this).data('id');
        var positionTitle = row.find('input[name="add-pm-materials[]"]').val();

        //Вызываем подтверждение
        modalConfirm(function(confirm) {
          if (confirm) {
            //Делаем запрос на удаление из базы
            
          }
        }, 
        'Вы уверены что хотите удалить &laquo;' + positionTitle + '&raquo; из приходного ордера?',
        'Удаление позиции из приходного ордера'
        );
      } else {
        var row = $(this).closest('tr');

        //Проверяем, вдруг осталась одна строка
        if ($('#materialsTable tbody tr:visible').length == 1) {
          //Просто очищаем ее
          row.find('input[type="text"]').val('');
          row.find('input[type="number"]').val('');
          row.find('select').val(row.find('select option').filter(function() {return $(this).text() === 'шт';}).first().attr('value'));
        } else {
          row.hide('fast', function() {row.remove(); enumerate();});
        }
      }
    }
  });

  //Функция восстановления нумерации в позициях в заявке
  function enumerate() {
    $('#materialsTable tbody tr:visible').each(function(index, element) {
      $(this).find('td:first').text(index + 1);
      $(this).find('input[name="urgentContentApp[]"]').attr('id', 'urgent' + (index + 1)).val(index + 1);
      $(this).find('input[name="numContentApp[]"]').val(index + 1);
      $(this).find('label').attr('for', 'urgent' + (index + 1));
    });
  }

  //Загрузка данных по счетам в модальное окно
  var appModal = document.getElementById('applicationsModal')
  appModal.addEventListener('shown.bs.modal', function (event) {
    var inn = $('#add-pm-provider').val();
    var request = $.ajax({
        type: 'GET',
        url: '/stock/getbills',
        data: {inn: inn}
    });

    request.done(function(data) {
      $('#applicationsModal .modal-body').html(data);
    });
  });

  appModal.addEventListener('hidden.bs.modal', function (event) {
    $('#applicationsModal .modal-body .accordion').addClass('d-none');
    $('#applicationsModal .modal-body').append('<div class="spinner-border spinner-border-sm text-primary" role="status"></div><span class="text-muted ms-2">Загрузка...</span>');
    $('#pickConfirmBtn').prop('disabled', true);
  });

  //Добавление поставщика
  //Получаем instance модального окна
  const providerModal = new bootstrap.Modal(document.getElementById('providerModal'));

  $('#addProviderBtn').click(function() {
    var form = $('#providerForm');
    
    formData = form.serialize();
    freezeForm(form);

    $.ajax({
      type: form.attr('method'),
      url: form.attr('action'),
      data: formData
    }).done(function(data) {
      if ($.isArray(data) && data[0] == 1) {
        //Все хорошо
        freezeForm(form, false);

        $('#add-pm-provider').html('<option value="' + $('#providerForm input[name="inn"]').val() + '" selected>' + $('#providerForm input[name="title"]').val() + '</option>');
        $('#add-pm-provider').closest('div').find('.filter-option-inner-inner').html( $('#providerForm input[name="title"]').val() );
        $('#add-pm-provider').closest('div').find('button').removeClass('bs-placeholder');
        $('#providerForm').trigger("reset");

        providerModal.hide();
      } else {
        showFormAlert(form, data[1]);
        freezeForm(form, false);
      }
    });
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

  $('body').on('change', 'input[name="material[]"]', function() {
      checkButtonState();
  });

  //Проверка состояния кнопки "Далее"
  function checkButtonState() {
      if ($('input[name="material[]"]:checked').length > 0) {
          $('#pickConfirmBtn').prop('disabled', false); 
      } else {
          $('#pickConfirmBtn').prop('disabled', true);
      }
  }

  //Получаем instance модального окна
  const applicationsModal = new bootstrap.Modal(document.getElementById('applicationsModal'));

  $('#pickConfirmBtn').click(function() {
    applicationsModal.hide();

    //Добавляем скрытые инпуты с данными
    $('.log-input').remove(); //Удаляем все старые
    $('#applicationsModal input[name="material[]"]:checked').each(function() {
      var tr = $(this).closest('tr');

      $('#add-pm-form').append('<input type="hidden" class="log-input" name="material[]" value="' + tr.find('input[name="material[]"]').val() + '" />');
      $('#add-pm-form').append('<input type="hidden" class="log-input" name="amount[]" value="' + tr.find('input[name="amount[]"]').val() + '" />');
      $('#add-pm-form').append('<input type="hidden" class="log-input" name="bill[]" value="' + tr.find('input[name="bill[]"]').val() + '" />');
    });

    $('#pickBtn').removeClass('btn-outline-primary').addClass('btn-outline-success').text('Выбрано: ' + $('input[name="material[]"]:checked').length);
    checkIsCountWarning();
  });

  $('#pickBtn').click(function() {
    $(this).removeClass('btn-outline-success').addClass('btn-outline-primary').text('Выбрать');
    checkIsCountWarning();
  });

  //Проверка соответствия выбранных элементов количетсву записей в документе
  function checkIsCountWarning() {
    if ($('#pickBtn').text() == 'Выбрать') {$('#countWarning').hide('fast'); return true;}

    //Проверяем количество заполненных строк
    var cnt = 0;
    $('#materialsTable tbody tr').each(function() {
      if ($(this).find('input[name="add-pm-materials[]"]').val() != '') {cnt++;}
    });

    if (cnt != $('input[name="material[]"]:checked').length) {
      $('#countWarning').show('fast'); return true;
    } else {
      $('#countWarning').hide('fast'); return true;
    }
  }

  //Действие при изменении содержательной части документа
  $('body').on('change', 'input[name="add-pm-materials[]"]', function() {
    checkIsCountWarning();
  });

  //Активация кнопки загрузки файла из шаблона
  $('#templateUpload').change(function() {
    if ($(this).val() == '') {
      $('#uploadBtn').prop('disabled', true);
    } else {
      $('#uploadBtn').prop('disabled', false);
    }
  });

  //Загрузка заявки из шаблона

  //Получаем instance модального окна
  const templateModal = new bootstrap.Modal(document.getElementById('templateModal'));

  var templateForm = $('#templateForm');

  $('#templateForm').ajaxForm({
    success: function(data) {
      freezeForm(templateForm, false);
      $('#materialsTable tbody').html(data);
      $('#uploadBtn').prop('disabled', true);
      $('#templateForm').trigger("reset");
      templateModal.hide();
    },
    error: function(data) {
      freezeForm(templateForm, false);
      alert(data);
    }
  });

  $('#uploadBtn').click(function() {
    freezeForm(templateForm);
    $('#templateForm').submit();
  });

  //Проверка состояния кнопки добавить
  function checkDoneButtonState() {

  }

  //Проверка формы
  function checkIsCorrect(element, expectFor) {
    if (! expectFor.test(element.val()) ) {
      element.closest('td').find('input').removeClass('is-valid').addClass('is-invalid');
      element.closest('td').find('div').removeClass('text-success').addClass('text-danger');
      return false;
    } else {
      element.closest('td').find('input').removeClass('is-invalid').addClass('is-valid');
      element.closest('td').find('div').removeClass('text-danger').addClass('text-success');
      return true;
    }
  }

  function checkElement(el, quiet = false) {
    if ( el.attr('name') === undefined ) {return true;}
    var expectFor = new RegExp( el.data('sbv-expression') );
    var notificationTarget = $('#' + el.data('sbv-notification-target'));
    var notification = el.data('sbv-notification');

    if ( el.is('input') || el.is('textarea') ) {
      var testValue = el.val();

      if ( expectFor.test(testValue) ) {
        //Валдация пройдена
        if ( !quiet ) {
          el.removeClass('is-invalid').addClass('is-valid');
          notificationTarget.removeClass('text-danger');
          if ( notificationTarget.data('sbv-default-text') !== undefined ) { notificationTarget.html( notificationTarget.data('sbv-default-text') ); }
        }
        return true;
      } else {
        //Ошибка
        if ( !quiet ) {
          el.removeClass('is-valid').addClass('is-invalid');
          if ( notificationTarget.data('sbv-default-text') === undefined ) { notificationTarget.attr( 'data-sbv-default-text', notificationTarget.html() ); }
          notificationTarget.addClass('text-danger').html(notification);
        }
        return false;
      }
    }

    if ( el.is('select') ) {
      var testValue = el.val();

      if ( el.hasClass('selectpicker') ) {
        if ( expectFor.test(testValue) ) {
          //Валдация пройдена
          if ( !quiet ) {
            el.closest('.bootstrap-select').removeClass('btn-outline-danger').addClass('btn-outline-success');
            notificationTarget.removeClass('text-danger');
            if ( notificationTarget.data('sbv-default-text') !== undefined ) { notificationTarget.html( notificationTarget.data('sbv-default-text') ); }
          }
          return true;
        } else {
          //Ошибка
          if ( !quiet ) {
            el.closest('.bootstrap-select').removeClass('btn-outline-success').addClass('btn-outline-danger');
            if ( notificationTarget.data('sbv-default-text') === undefined ) { notificationTarget.attr( 'data-sbv-default-text', notificationTarget.html() ); }
            notificationTarget.addClass('text-danger').html(notification);
          }
          return false;
        }
      }
    }

    return false;
  }

  $('body').on('keyup', 'input.should-be-validated', function() {
    var el = $(this);
    if ( el.data('sbv-depence') !== undefined ) {
      if ( checkElement(el) && el.val() != '' ) {
        //Вызываем проверку всех should-be-validated элементов на этом уровне
        el.closest('.sbv-parent').find('.should-be-validated').each(function() {
          if ( $(this).data('sbv-depence-of') !== undefined ) {
            checkElement($(this));
          }
        });
      } else {
        //Если элемент пустой или не валиден, при этом зависимые от него should-be-validated элементы не валидны - сбросить флаг
        el.closest('.sbv-parent').find('.should-be-validated').each(function() {
          if ( $(this).data('sbv-depence-of') !== undefined ) {
            $(this).removeClass('is-invalid');
          }
        });
      }
    } else {
      if ( el.data('sbv-depence-of') !== undefined ) {
        el.closest('.sbv-parent').find('.should-be-validated').each(function() {
          if ( $(this).data('sbv-depence') !== undefined ) {
            //Главный элемент
            if ( $(this).val() != '' ) {
              checkElement(el);
            }
          }
        });
      } else {
        checkElement(el);
      }
    }

    checkForm( el.closest('form') );
  });

  $('body').on('change', 'select.should-be-validated', function() {
    checkElement($(this)); 
    checkForm( $(this).closest('form') );
  });
  
  function checkForm(form) {
    var valid = true;
    var quiet = true;
    form.find('.should-be-validated').each(function() {
      var el = $(this);
      if ( el.data('sbv-depence-of') === undefined ) {
        if ( (el.data('sbv-depence') !== undefined && el.val() != '') || el.data('sbv-depence') === undefined ) {
          var tmp = checkElement(el, quiet);
          if (!tmp) {console.log(el.attr('name'));}
          valid = valid && tmp;  
        }
      } else {
        el.closest('.sbv-parent').find('.should-be-validated').each(function() {
          if ( $(this).data('sbv-depence') !== undefined ) {
            //Главный элемент
            if ( $(this).val() != '' ) {
              var tmp = checkElement(el, quiet);
              valid = valid && tmp;  
            }
          }
        });
      }
    });

    $('.sbv-submit').prop('disabled', !valid);
    return valid;
  }

  //Вывод сообщения об ошибке
  function showFormAlert(form, text) {
    form.find('fieldset').append('<div class="alert alert-danger alert-dismissible fade show mb-4 form-message" role="alert">\
    <span>' + text + '</span>\
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>\
  </div>');
  }

  //Отправка формы
  $('#sendBtn').click(function() {
    addPmForm = $(this).closest('form');

    if (addPmForm.find('.form-message').length == 0) {
      prepareForm();
    } else {
      addPmForm.find('.form-message').hide('fast', function() {
        $(this).remove();
        prepareForm();
      });
    }
  });

  function prepareForm() {
    if (checkForm(addPmForm)) {
      //Вызываем загрузку всех файлов
      if ($('.file-preview-thumbnails .kv-preview-thumb').not('.file-preview-success').not('.file-preview-initial').length > 0) {
        fileUploadError = false;
        $('#attach').fileinput('upload');
      } else {
        //Загружать нечего/все загружено, отправляем форму
        formData = addPmForm.serialize();
        freezeForm(addPmForm);
        $('#attach').fileinput('disable');
        sendForm();
      }
    } else {
      showFormAlert(addPmForm, 'Не заполнены необходимые поля');
      return false;
    }
  }

  //Плагин загрузки файлов
  var fileUploadError = false;
  $('#attach').fileinput({
      browseClass: 'btn btn-outline-secondary',
      language: 'ru',
      uploadUrl: '/stock/upload-file',
      maxFileSize: 10240,
      fileActionSettings: {
          showRemove: true,
          showUpload: false,
          showZoom: true,
          showDrag: false,
      }
  })
  .on('fileuploaded', function(event, data, index, fileId) {
    //Файл успешно загружен
    document.getElementById(index).setAttribute('data-id', data.response.id);
  }).on('filesuccessremove', function(event, id) {
    //Удаление файла если он загружен
    var db_id = document.getElementById(id).getAttribute('data-id');
    //Отправляем запрос на удаление файла
    $.post('/stock/delete-file', {key: db_id});
  }).on('filebatchuploadcomplete', function(event, data, previewId, index) {
    if (!fileUploadError) {
      //Загрузка всех файлов окончена, деактивируем выбор файлов, отправляем форму
      //Подтягиваем значения идентификаторов загруженных файлов
      if ($('.file-preview-thumbnails .file-preview-success').length > 0) {
        var arrFiles = [];
        $('.file-preview-thumbnails .file-preview-success').each(function() {
          arrFiles.push($(this).data('id'));
        });

        $('input[name="files"]').val(JSON.stringify(arrFiles));
      } else {
        $('input[name="files"]').val('');
      }

      formData = addPmForm.serialize();
      freezeForm(addPmForm);
      $('#attach').fileinput('disable');
      sendForm();
    }
  }).on('fileuploaderror', function(event, data, msg) {
    fileUploadError = true;
  });

  function sendForm() {
    $.ajax({
      type: addPmForm.attr('method'),
      url: addPmForm.attr('action'),
      data: formData
    }).done(function(data) {
      if ($.isArray(data) && data[0] == 1) {
        //Все хорошо
        if (addPmForm.attr('id') == 'add-pm-form') {
          alert('All good!');
          // $.redirectPost('/applications', {'msg': 'Заявка №' + data[1] + ' успешно добавлена', 'bg-color': 'bg-success', 'text-color': 'text-white'});
        } else {
          location.reload();
        }
      } else {
        showFormAlert(addPmForm, data[1]);
        freezeForm(addPmForm, false);
        $('#attach').fileinput('enable');
      }
    });
  }
});