$(document).ready(function() {
  //Рисуем стрелки для логистики
  $('.arrowsContainer').each(function() {
    var container = $(this);
    container.css('height', container.parent().height() + 'px');
  });

  function drawLogArrow(arrow, x1, y1, x2, y2, w) {
    var posnALeft = {x: x1 + 1, y: y1 + 1};
    var posnARight = {x: x2 + 5, y: y2};
    var dStrLeft =
        'M' +
        (posnALeft.x) + ',' + (posnALeft.y) + " " +
        'C' +
        (posnALeft.x + w) + ',' + (posnALeft.y) + " " +
        (posnARight.x - w) + ',' + (posnARight.y) + " " +
        (posnARight.x) + ',' + (posnARight.y);
    
    arrow.attr("d", dStrLeft);
  }

  $('.arrowsCell').each(function() {
    var td = $(this);
    var y1 = td.height() / 2;
    // var defH = $('#logTable tr:first td:first p:first').height() + parseInt($('#logTable tr:first td:first').css('padding-top'));
    var defW = 41;
    var id = $(this).find('g').data('id');

    var y = 0;
    td.find('.logArrows').each(function(index) {
      var dH = $('.parent' + id).eq(index).closest('td').height() + 16;
      drawLogArrow($(this), 0, y1, defW, (y + dH / 2 + 1), 35);
      y += dH;
    });
  });

  var billForm = $('#saveBillForm');

  //Инициализация fileinput для предпросмотра файлов
  $('#attachments').fileinput(paramsPreview);
  $('.filePreview').click(function(event) {
    event.preventDefault();
    var previewId = $(this).data('id');
    $('#attachments').fileinput('zoom', previewId);
  });

  //Плагин загрузки документов
  var params = {
      browseClass: 'btn btn-outline-secondary',
      language: 'ru',
      uploadUrl: '/applications/bills/upload-file',
      maxFileSize: 10240,
      dropZoneEnabled: false,
      fileActionSettings: {
          showRemove: true,
          showUpload: false,
          showZoom: true,
          showDrag: false,
      }
  };

  var fileUploadError = false;
  $('#attach').fileinput(params)
  .on('fileselect', function(event, numFiles, label) {
    checkButtonState();
  }).on('fileremoved', function(event) {
    checkButtonState();
  }).on('fileuploaded', function(event, data, index, fileId) {
      //Файл успешно загружен
      document.getElementById(index).setAttribute('data-id', data.response.id);
  }).on('filesuccessremove', function(event, id) {
      //Удаление файла если он загружен
      var db_id = document.getElementById(id).getAttribute('data-id');
      //Отправляем запрос на удаление файла
      $.post('/applications/bills/delete-file', {key: db_id});
  }).on('filebatchuploadcomplete', function(event, data, previewId, index) {
      if (!fileUploadError) {
        //Загрузка всех файлов окончена, деактивируем выбор файлов, отправляем форму
        //Подтягиваем значения идентификаторов загруженных файлов
        if ($('#documentsInput .file-preview-thumbnails .file-preview-success').length > 0) {
            var arrFiles = [];
            $('#documentsInput .file-preview-thumbnails .file-preview-success').each(function() {
              arrFiles.push($(this).data('id'));
            });

            $('input[name="files"]').val(JSON.stringify(arrFiles));
        } else {
            $('input[name="files"]').val('');
        }

        filesUploaded = true;
        uploadFiles();
      }
  }).on('fileuploaderror', function(event, data, msg) {
      fileUploadError = true;
  });

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

        photosUploaded = true;
        uploadFiles();
      }
  }).on('fileuploaderror', function(event, data, msg) {
    photoUploadError = true;
  });

  //Функция, отвечающая за последовательную загрузку фотографий и документов
  var filesUploaded = false;
  var photosUploaded = false;
  function uploadFiles() {
    if ($('#documentsInput .file-preview-thumbnails .kv-preview-thumb').not('.file-preview-success').not('.file-preview-initial').length == 0 && $('#photosInput .file-preview-thumbnails .kv-preview-thumb').not('.file-preview-success').not('.file-preview-initial').length == 0) {
      sendForm();
      return true;
    }
    
    if (filesUploaded && photosUploaded) {
      sendForm();
      return true;
    }

    //Вызываем загрузку файлов
    if (!filesUploaded && $('#documentsInput .file-preview-thumbnails .kv-preview-thumb').not('.file-preview-success').not('.file-preview-initial').length > 0) {
      fileUploadError = false;
      $('#attach').fileinput('upload');
    } else {
      filesUploaded = true;
    }

    //Вызываем загрузку фотографий
    if (!photosUploaded && $('#photosInput .file-preview-thumbnails .kv-preview-thumb').not('.file-preview-success').not('.file-preview-initial').length > 0) {
      fileUploadError = false;
      $('#photos').fileinput('upload');
    } else {
      photosUploaded = true;
    }

    return false;
  }

  //Автозаполнение поля "Способ отправки"
  $('input[name="way"]').autocomplete({
      serviceUrl: '/logistics/way'
  });

  //Автозаполнение поля "Номер для отслеживания"
  $('input[name="track"]').autocomplete({
    serviceUrl: '/logistics/track'
  });

  //Состояние панели
  var tab = 0; //Получение
  $('#reciept-tab').click(function() {
    tab = 0; 
    checkButtonState(); 
    $('input[name="type"]').val(tab);
  }); //Получение
  $('#ship-tab').click(function() {
    tab = 1; 
    checkButtonState(); 
    $('input[name="type"]').val(tab);
  }); //Отгрузка

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

  //Функция проверки номера для отслеживания
  function checkTrack(quiet = false) {
    var subject = $('#track');
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
      valid = valid & checkTrack(quiet);
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

  $('#track').keyup(function() {
    checkTrack(false);
    checkButtonState();
  });

  //Удаление счета
  $('.remove-document-btn').click(function() {
    var btn = $(this);
    var id = $(this).data('id');
    var name = $(this).data('name');

    //Вызываем подтверждение
    modalConfirm(function(confirm) {
      if (confirm) {
        $.post('/applications/bills/delete-file', {id: id})
        .done(function(data) {
          //Все хорошо
          var td = btn.closest('td');
          btn.closest('.btn-group').hide('fast', function() {
            btn.closest('.btn-group').remove(); 
            if (td.find('.btn-group').length == 0) {
              td.parent().hide('fast', function() { td.parent().remove(); });
            }
          });
        }).fail(function() {
          addToast('Произошла ошибка.<br />Попробуйте обновить страницу.', 'bg-danger', 'text-white');
          showToasts();
        });
      }
    }, 
    'Вы уверены что хотите удалить данный документ?<br /><span class="text-muted">Файл: ' + name + '</span>',
    'Удаление документа'
    );
  });

  //Редактирование информации о поставщике
  $('#saveProviderBtn').click(function() {
    var form = $('#providerForm');
    
    formData = form.serialize();
    freezeForm(form);

    //Получаем instance окна
    var providerModal = bootstrap.Modal.getInstance(document.getElementById('providerModal'));

    $.ajax({
      type: form.attr('method'),
      url: form.attr('action'),
      data: formData
    }).done(function(data) {
      if ($.isArray(data) && data[0] == 1) {
        //Все хорошо
        freezeForm(form, false);

        //Собираем таблицу с информацией
        var info = '<table><tbody>';
        if (form.find('input[name="title"]').val() != '') {
          info += '<tr><td class="text-muted pe-2">Наименование:</td><td>' + form.find('input[name="title"]').val() + '</td></tr>';
        }
        info += '<tr><td class="text-muted pe-2">ИНН:</td><td>' + form.find('input[name="inn"]').val() + '</td></tr>';
        if (form.find('input[name="address"]').val() != '') {
          info += '<tr><td class="text-muted pe-2">Почтовый адрес:</td><td>' + form.find('input[name="address"]').val() + '</td></tr>';
        }
        if (form.find('input[name="phone"]').val() != '') {
          info += '<tr><td class="text-muted pe-2">Телефон:</td><td>' + form.find('input[name="phone"]').val() + '</td></tr>';
        }
        if (form.find('textarea[name="comment"]').val() != '') {
          info += '<tr><td class="text-muted pe-2">Комментарий:</td><td>' + form.find('textarea[name="comment"]').val() + '</td></tr>';
        }
        info += '</tbody></table>';

        $('#provider-info').html(info);
        providerModal.hide();
      } else {
        showFormAlert(form, data[1]);
        freezeForm(form, false);
      }
    });
  });

  //Редактирование дополнительной информации о счете
  $('#saveCommentBtn').click(function() {
    var form = $('#commentForm');
    
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

        //Собираем таблицу с информацией
        if (form.find('textarea[name="comment"]').val() != '') {
          $('#comment-info-none').addClass('d-none');
          $('#comment-info').html(form.find('textarea[name="comment"]').val());
        } else {
          $('#comment-info-none').removeClass('d-none');
          $('#comment-info').html('');
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

  //Выделение всех позиций в заявке
  $('body').on('change', '.material-select-all', function() {
    $(this).closest('table').find('.material-select').prop('checked', $(this).prop('checked'));
    $(this).closest('table').find('.material-select:last').trigger('change');
    checkButtonState();

    //Скрываем/отображаем панель с информацией об отгрузке
    if ($('.material-select:checked').length > 0) {
      $('#meta-info').show('fast');
    } else {
      $('#meta-info').hide('fast');
    }
  });

  //Выделение позиций с клавишей Shift
  $('body').on('click', '.material-select', function(e) {
    //Сбрасываем галочку "Выделить все", если выделение снято
    if (!$(this).prop('checked')) {
      $(this).closest('table').find('.material-select-all').prop('checked', false);
    }

    checkButtonState();

    $('.material-select-all').prop("indeterminate", true);

    //Скрываем/отображаем панель с информацией об отгрузке
    if ($('.material-select:checked').length > 0) {
      $('#meta-info').show('fast');
    } else {
      $('#meta-info').hide('fast');
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

  //Проверка состояния кнопки отправки формы
  function checkButtonState() {
    $('.amount-input').attr('name', '');

    if ($('.material-select:checked').length == 0) {
      if ($('#documentsInput .file-preview-thumbnails .kv-preview-thumb').length > 0) {
        $('#sendBtn').prop('disabled', false);
        return true;
      } else {
        $('#sendBtn').prop('disabled', true);
        return false;            
      }
    } else {
      if (checkMetaInfo(true)) {
        $('#sendBtn').prop('disabled', false);
        //Прописываем имена input чтобы избежать отправки ненужных данных
        $('.material-select:checked').each(function() {
          $(this).closest('tr').find('.amount-input').attr('name', 'amount[]');
        });
        return true;
      } else {
        if ($('#documentsInput .file-preview-thumbnails .kv-preview-thumb').length > 0) {
          $('#sendBtn').prop('disabled', false);
          return true;
        } else {
          $('#sendBtn').prop('disabled', true);
          return false;            
        }
      }
    }
  }

  //Отправка формы
  $('#sendBtn').click(function() {
    //Загружаем файлы
    filesUploaded = false;
    photosUploaded = false;
    uploadFiles();
  });

  function sendForm() {
    formData = billForm.serialize();
    freezeForm(billForm);
    $('#attach').fileinput('disable');
    $('#photos').fileinput('disable');
    
    $.ajax({
      type: billForm.attr('method'),
      url: billForm.attr('action'),
      data: formData
    }).done(function(data) {
      if ($.isArray(data) && data[0] == 1) {
        $.redirectPost('/applications', {'msg': 'Изменения успешно внесены', 'bg-color': 'bg-success', 'text-color': 'text-white'});
      } else {
        showFormAlert(billForm, data[1]);
        freezeForm(billForm, false);
      }
    }).fail(function() {
      showFormAlert(billForm, 'Ошибка отправки данных');
      freezeForm(billForm, false);
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