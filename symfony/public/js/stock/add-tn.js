$(document).ready(function() {
  //Автозаполнение поля "Перевозка"
  function wayAutocomplete() {
    $('.way-autocomplete').autocomplete({
        serviceUrl: '/autocomplete/way'
    });
  }
  wayAutocomplete();

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
  });

  $('#pickBtn').click(function() {
    $(this).removeClass('btn-outline-success').addClass('btn-outline-primary').text('Выбрать');
  });

  //Удаление позиций из заявки
  $('body').on('click', '.delete-row', function() {
    if (!$(this).prop('disabled')) {
      var row = $(this).closest('tr');

      //Проверяем, вдруг осталась одна строка
      if ($('#materialsTable tbody tr:visible').length == 1) {
        showFormAlert(addTnForm, 'Нельзя удалить последнюю строку');
      } else {
        row.hide('fast', function() {row.remove(); enumerate();});
      }
    }
  });

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
    addTnForm = $(this).closest('form');

    if (addTnForm.find('.form-message').length == 0) {
      prepareForm();
    } else {
      addTnForm.find('.form-message').hide('fast', function() {
        $(this).remove();
        prepareForm();
      });
    }
  });

  function prepareForm() {
    if (checkForm(addTnForm)) {
      //Вызываем загрузку всех файлов
      if ($('.file-preview-thumbnails .kv-preview-thumb').not('.file-preview-success').not('.file-preview-initial').length > 0) {
        fileUploadError = false;
        $('#attach').fileinput('upload');
      } else {
        //Загружать нечего/все загружено, отправляем форму
        formData = addTnForm.serialize();
        freezeForm(addTnForm);
        $('#attach').fileinput('disable');
        sendForm();
      }
    } else {
      showFormAlert(addTnForm, 'Не заполнены необходимые поля');
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

      formData = addTnForm.serialize();
      freezeForm(addTnForm);
      $('#attach').fileinput('disable');
      sendForm();
    }
  }).on('fileuploaderror', function(event, data, msg) {
    fileUploadError = true;
  });

  function sendForm() {
    $.ajax({
      type: addTnForm.attr('method'),
      url: addTnForm.attr('action'),
      data: formData
    }).done(function(data) {
      if ($.isArray(data) && data[0] == 1) {
        //Все хорошо
        if (addTnForm.attr('id') == 'add-pm-form') {
          alert('All good!');
          // $.redirectPost('/applications', {'msg': 'Заявка №' + data[1] + ' успешно добавлена', 'bg-color': 'bg-success', 'text-color': 'text-white'});
        } else {
          location.reload();
        }
      } else {
        showFormAlert(addTnForm, data[1]);
        freezeForm(addTnForm, false);
        $('#attach').fileinput('enable');
      }
    });
  }
});