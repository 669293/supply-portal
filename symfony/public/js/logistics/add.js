$(document).ready(function() {
  var logForm = $('#logForm');

  //Плагин загрузки фотографий
  var paramsPhotos = {
    browseClass: 'btn btn-outline-secondary',
    language: 'ru',
    uploadUrl: '/applications/logistics/upload-photo',
    maxFileSize: 10240,
    dropZoneEnabled: false,
    fileActionSettings: {
        showRemove: true,
        showUpload: false,
        showZoom: true,
        showDrag: false,
    }
  };

  var photoUploadError = false;
  $('#photos').fileinput(paramsPhotos)
  .on('fileuploaded', function(event, data, index, fileId) {
    //Файл успешно загружен
    document.getElementById(index).setAttribute('data-id', data.response.id);
  }).on('filesuccessremove', function(event, id) {
    //Удаление файла если он загружен
    var db_id = document.getElementById(id).getAttribute('data-id');
    //Отправляем запрос на удаление файла
    $.post('/applications/logistics/delete-photo', {key: db_id});
  }).on('filebatchuploadcomplete', function(event, data, previewId, index) {
    if (!photoUploadError) {
      //Загрузка всех файлов окончена, деактивируем выбор файлов, отправляем форму
      //Подтягиваем значения идентификаторов загруженных файлов        
      if ($('#photosInput .file-preview-thumbnails .file-preview-success').length > 0) {
        var arrFiles = [];
        $('#photosInput .file-preview-thumbnails .file-preview-success').each(function() {
          arrFiles.push($(this).data('id'));
        });

        $('input[name="photos"]').val(JSON.stringify(arrFiles));
      } else {
        $('input[name="photos"]').val('');
      }

      //Отправляем форму 
      sendForm();
    }
  }).on('fileuploaderror', function(event, data, msg) {
    photoUploadError = true;
  });

  //Автозаполнение поля "Способ отправки"
  $('input[name="way"]').autocomplete({
      serviceUrl: '/logistics/way'
  });

  //Автозаполнение поля "Номер для отслеживания"
  $('input[name="track"]').autocomplete({
    serviceUrl: '/logistics/track'
  });

  //Функция проверки даты получения
  function checkDate(quiet = false) {
    if (tab == 0) {var subject = $('#dateReciept');} else {var subject = $('#dateShip');}
    if (subject.val() == '') {
      if (!quiet) {
        subject.removeClass('is-valid').addClass('is-invalid');
      }
      return false;
    } else {
      if (!quiet) {
        subject.removeClass('is-invalid').addClass('is-valid');
      }
      return true;
    }
  }

  //Функция проверки способа отправки
  function checkWay(quiet = false) {
    var subject = $('#way');
    if (subject.val() == '') {
      if (!quiet) {
        subject.removeClass('is-valid').addClass('is-invalid');
      }
      return false;
    } else {
      if (!quiet) {
        subject.removeClass('is-invalid').addClass('is-valid');
      }
      return true;
    }
  }

  //Функция проверки полей
  function checkMetaInfo(quiet = false) {
    var valid = true;
    if (tab == 0) {
      //Получение
      valid = valid & checkDate(quiet);
    } else {
      //Отгрузка
      valid = valid & checkDate(quiet);
      valid = valid & checkWay(quiet);
    }

    return valid;
  }

  //Инициализация проверок по мере заполнения полей
  $('#dateReciept').keyup(function() {
    checkDate(false);
    checkButtonState();
  });

  $('#dateShip').keyup(function() {
    checkDate(false);
    checkButtonState();
  });

  $('#way').keyup(function() {
    checkWay(false);
    checkButtonState();
  });

  //Выделение всех позиций в заявке
  $('body').on('change', '.material-select-all', function() {
    $(this).closest('table').find('.material-select').prop('checked', $(this).prop('checked'));
    $(this).closest('table').find('.material-select:last').trigger('change');
    checkButtonState();
  });

  //Выделение позиций с клавишей Shift
  $('body').on('click', '.material-select', function(e) {
    //Сбрасываем галочку "Выделить все", если выделение снято
    if (!$(this).prop('checked')) {
      $(this).closest('table').find('.material-select-all').prop('checked', false);
    }

    checkButtonState();

    $('.material-select-all').prop("indeterminate", true);

    if (lastChecked && lastChecked.closest('table') != this.closest('table')) {lastChecked = null;}

    if (!lastChecked) {lastChecked = this; return;}

    if (e.shiftKey) {
        var start = $('.material-select').index(this);
        var end = $('.material-select').index(lastChecked);

        $('.material-select').slice(Math.min(start,end), Math.max(start,end)+ 1).prop('checked', lastChecked.checked);
    }

    lastChecked = this;
  });

  //Проверка состояния кнопки отправки формы
  function checkButtonState() {
    $('.amount-input').attr('name', '');

    if ($('.material-select:checked').length == 0) {
      $('#sendBtn').prop('disabled', true);
      return false;            
    } else {
      if (checkMetaInfo(true)) {
        $('#sendBtn').prop('disabled', false);
        //Прописываем имена input чтобы избежать отправки ненужных данных
        $('.material-select:checked').each(function() {
          $(this).closest('tr').find('.amount-input').attr('name', 'amount[]');
        });
        return true;
      } else {
        $('#sendBtn').prop('disabled', true);
        return false;            
      }
    }
  }

  //Отправка формы
  $('#sendBtn').click(function() {
    //Загружаем файлы
    if ($('#photosInput .file-preview-thumbnails .kv-preview-thumb').not('.file-preview-success').not('.file-preview-initial').length > 0) {
      fileUploadError = false;
      $('#photos').fileinput('upload');
    } else {
      sendForm();
    }
  });

  function sendForm() {
    formData = logForm.serialize();
    freezeForm(logForm);
    $('#photos').fileinput('disable');
    
    $.ajax({
      type: logForm.attr('method'),
      url: logForm.attr('action'),
      data: formData
    }).done(function(data) {
      if ($.isArray(data) && data[0] == 1) {
        // $.redirectPost('/applications', {'msg': 'Изменения успешно внесены', 'bg-color': 'bg-success', 'text-color': 'text-white'});
        location.href = '/applications/logistics/view?id=' + $('input[name="parent"]').val();
      } else {
        showFormAlert(logForm, data[1]);
        freezeForm(logForm, false);
      }
    }).fail(function() {
      showFormAlert(logForm, 'Ошибка отправки данных');
      freezeForm(logForm, false);
      $('#attach').fileinput('enable');
      $('#photos').fileinput('enable');
    }); 
  }
  
  //Вывод сообщения об ошибке
  function showFormAlert(form, text) {
    form.find('fieldset').append('<div class="alert alert-danger alert-dismissible fade show mb-0 mt-4 form-message" role="alert">\
      <span>' + text + '</span>\
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>\
    </div>');
  }
});