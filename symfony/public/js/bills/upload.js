$(document).ready(function() {
  var billForm;
  var formData;
  var lastChecked = null;

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

    if (lastChecked && lastChecked.closest('table') != this.closest('table')) {lastChecked = null;}

    if (!lastChecked) {lastChecked = this; return;}

    if (e.shiftKey) {
        var start = $('.bill-select').index(this);
        var end = $('.bill-select').index(lastChecked);

        $('.bill-select').slice(Math.min(start,end), Math.max(start,end)+ 1).prop('checked', lastChecked.checked);
    }

    lastChecked = this;
  });

  //Выбор валюты
  $('.currency').click(function(event) {
    event.preventDefault();
    var currency = $(this).text();
    $('#billCurrency').val(currency);
    $('#currencyLabel').text(currency);
  });

  //Прокрутка к низу страницы
  $(window).scroll(function() {
    var scroll = $(window).scrollTop();
    var offset = $('#mark').offset().top;

    if (scroll > offset && scroll < ($(document).height() - $(window).height())) {
      $('#scrollDown').fadeIn('medium');
    } else {
      $('#scrollDown').fadeOut('medium');
    }
  });

  $('#scrollDown').click(function(event) {
    $('html, body').animate({scrollTop: $(document).height()}, 'fast');
  });

  //Плагин загрузки файлов
  var fileUploadError = false;
  $('#billFileInput').fileinput({
    theme: 'explorer',
    browseClass: 'btn btn-outline-secondary',
    language: 'ru',
    uploadUrl: '/applications/bills/upload-bill',
    maxFileSize: 10240,
    dropZoneEnabled: false,
    showUpload: false,
    inputGroupClass: 'input-group-sm',
    required: true
  }).on('fileuploaded', function(event, data, index, fileId) {
    //Файл успешно загружен
    document.getElementById(index).setAttribute('data-path', data.response.path);
  }).on('filebatchuploadcomplete', function(event, data, previewId, index) {
    if (!fileUploadError) {
      //Загрузка файла окончена, деактивируем выбор, отправляем форму
      //Подтягиваем значения идентификаторов загруженного файла
      if ($('#billFileField .file-preview-thumbnails .file-preview-success').length > 0) {
        var filePath = '';
        $('#billFileField .file-preview-thumbnails .file-preview-success').each(function() {
          filePath = $(this).data('path');
        });

        $('input[name="billFilePath"]').val(filePath);
      } else {
        $('input[name="billFilePath"]').val('');
      }

      prepareOtherFiles();
    }
  }).on('fileuploaderror', function(event, data, msg) {
    fileUploadError = true;
  });

  //Валидация ИНН
  function validateInn(subject, quiet = false) {
    var inn = subject.val();
    var valid = false;
    
    if (typeof inn === 'number') {
      inn = inn.toString();
    } else if (typeof inn !== 'string') {
      inn = '';
    }

    if (!inn.length) {
      if (!quiet) {
        subject.removeClass('is-valid').addClass('is-invalid');
        subject.closest('tr').find('.form-text').text('Обязательное поле').addClass('text-danger');
      }
    } else if (/[^0-9]/.test(inn)) {
      if (!quiet) {
        subject.removeClass('is-valid').addClass('is-invalid');
        subject.closest('tr').find('.form-text').text('ИНН может состоять только из цифр').addClass('text-danger');
      }
    } else if ([10, 12].indexOf(inn.length) === -1) {
      if (!quiet) {
        subject.removeClass('is-valid').addClass('is-invalid');
        subject.closest('tr').find('.form-text').text('ИНН может состоять только из 10 или 12 цифр').addClass('text-danger');
      }
    } else {
      var checkDigit = function (inn, coefficients) {
        var n = 0;
        for (var i in coefficients) {n += coefficients[i] * inn[i];}
        return parseInt(n % 11 % 10);
      };
      switch (inn.length) {
        case 10:
          var n10 = checkDigit(inn, [2, 4, 10, 3, 5, 9, 4, 6, 8]);
          if (n10 === parseInt(inn[9])) {
            valid = true;
            if (!quiet) {
              subject.removeClass('is-invalid').addClass('is-valid');
              subject.closest('tr').find('.form-text').text('ИНН поставщика').removeClass('text-danger');
            }
          }
          break;
        case 12:
          var n11 = checkDigit(inn, [7, 2, 4, 10, 3, 5, 9, 4, 6, 8]);
          var n12 = checkDigit(inn, [3, 7, 2, 4, 10, 3, 5, 9, 4, 6, 8]);
          if ((n11 === parseInt(inn[10])) && (n12 === parseInt(inn[11]))) {
            valid = true;
            if (!quiet) {
              subject.removeClass('is-invalid').addClass('is-valid');
              subject.closest('tr').find('.form-text').text('ИНН поставщика').removeClass('text-danger');
            }
          }
          break;
      }
      if (!valid) {
        if (!quiet) {
          subject.removeClass('is-valid').addClass('is-invalid');
          subject.closest('tr').find('.form-text').text('Неправильное контрольное число').addClass('text-danger');
        }
      }
    }

    return valid;
  }

  $('#innInput').keyup(function() {
    validateInn($(this));
    checkButtonState();
  });

  //Проверка номера счета
  function validateBillNum(subject, quiet = false) {
    var valid = true;

    if (subject.val() == '') {
      valid = false;
      if (!quiet) {
        subject.removeClass('is-valid').addClass('is-invalid');
        subject.closest('tr').find('.form-text').text('Обязательное поле').addClass('text-danger');
      }
    } else {
      if (!quiet) {
        subject.removeClass('is-invalid').addClass('is-valid');
        subject.closest('tr').find('.form-text').text('Номер счета (используется для контроля оплаты)').removeClass('text-danger');
      }
    }

    return valid;
  }

  $('#billNumInput').keyup(function() {
    validateBillNum($(this));
    checkButtonState();
  });

  //Проверка суммы
  function validateSum(subject, quiet = false) {
    var valid = true;

    if (subject.val() == '') {
      valid = false;
      if (!quiet) {
        subject.removeClass('is-valid').addClass('is-invalid');
        subject.closest('tr').find('.form-text').text('Обязательное поле').addClass('text-danger');
      }
    } else {
      var regex = /^\d+(\.\d{2})?$/;
      if (!regex.test(subject.val())) {
        if (!quiet) {
          subject.removeClass('is-valid').addClass('is-invalid');
          subject.closest('tr').find('.form-text').text('Неправильный формат').addClass('text-danger');
        }  
      } else {
        if (!quiet) {
          subject.removeClass('is-invalid').addClass('is-valid');
          subject.closest('tr').find('.form-text').text('Итого к оплате по счету (используется для контроля оплаты)').removeClass('text-danger');
        }
      }
    }

    return valid;
  }

  $('#billSumInput').keyup(function() {
    validateSum($(this));
    checkButtonState();
  });

  //Проверка даты поступления
  function validateDate(subject, quiet = false) {
    var valid = true;

    if (subject.val() == '') {
      valid = false;
      if (!quiet) {
        subject.removeClass('is-valid').addClass('is-invalid');
        subject.closest('tr').find('.form-text').text('Обязательное поле').addClass('text-danger');
      }
    } else {
      if (!quiet) {
        subject.removeClass('is-invalid').addClass('is-valid');
        subject.closest('tr').find('.form-text').text('Прогнозируемая поставка ТМЦ по счету на склад заказчика').removeClass('text-danger');
      }
    }

    return valid;
  }

  $('#billDateInput').keyup(function() {
    validateDate($(this));
    checkButtonState();
  });

  $('#billDateInput').change(function() {
    validateDate($(this));
    checkButtonState();
  });

  //Проверка файла
  function validateFile(quiet = false) {
    var filesCount = $('#billFileInput').fileinput('getFilesCount');
    var valid = true;
    if (filesCount == 0) {valid = false;}

    //Возможно файл уже загружен
    if ($('.kv-preview-thumb.file-preview-success').length > 0) {valid = true;}

    if (!quiet) {
      if (!valid) {
        $('.kv-fileinput-caption').removeClass('is-valid').addClass('is-invalid');
        $('.kv-fileinput-caption').closest('tr').find('.form-text').text('Необходимо выбрать счет для загрузки').addClass('text-danger');
        return false;
      } else {
        $('.kv-fileinput-caption').removeClass('is-invalid').addClass('is-valid');
        $('.kv-fileinput-caption').closest('tr').find('.form-text').text('Поддерживаются файлы PDF и изображения').removeClass('text-danger');
      }
    }

    return valid;
  }

  //Проверка привязки к позициям
  function validateMaterials() {
    var valid = true;
    if ($('input[name="material[]"]:checked').length == 0) {valid = false;}
    return valid;
  }

  $('body').on('change', 'input[name="material[]"]', function() {
    checkButtonState();
  });

  //Проверка формы
  function checkForm(form, quiet = false) {
    var valid = true;

    valid &= validateInn($('#innInput'), quiet);
    valid &= validateBillNum($('#billNumInput'), quiet);
    valid &= validateSum($('#billSumInput'), quiet);
    valid &= validateDate($('#billDateInput'), quiet);
    valid &= validateFile(quiet);
    valid &= validateMaterials();
    
    return valid;
  }

  //Проверка состояния кнопки отправки формы
  function checkButtonState() {
    if (checkForm($('#sendBtn').closest('form'), true)) {
      $('#sendBtn').prop('disabled', false); 
    } else {
      $('#sendBtn').prop('disabled', true);
    }
  }

  $('#sendBtn').click(function() {
    billForm = $('#sendBtn').closest('form');

    if (billForm.find('.form-message').length == 0) {
      prepareForm();
    } else {
      billForm.find('.form-message').hide('fast', function() {
        $(this).remove();
        prepareForm();
      });
    }
  });

  function prepareForm() {
    if (checkForm(billForm, false)) {
      //Выставляем имена полей содержащих количество материалов, чтобы количество отправленных inputов соответствовало количеству выбранных материалов
      $('.amount-input').attr('name', '');
      $('input[name="material[]"]:checked').each(function() {
        $(this).closest('tr').find('.amount-input').attr('name', 'amount[]');
      });

      //Фиксим количество, чтобы оно не превышало остатки
      $('.amount-input').each(function() {
        if (Number.parseInt($(this).val()) > Number.parseInt($(this).attr('max'))) {$(this).val($(this).attr('max'));}
        if (Number.parseInt($(this).val()) < Number.parseInt($(this).attr('min'))) {$(this).val($(this).attr('min'));}
      });

      //Вызываем загрузку всех файлов
      if ($('#billFileField .file-preview-thumbnails .kv-preview-thumb').not('.file-preview-success').not('.file-preview-initial').length > 0) {
        fileUploadError = false;
        $('#billFileInput').fileinput('upload');
      } else {
        //Загружать нечего/все загружено, отправляем форму
        formData = billForm.serialize();
        freezeForm(billForm);
        $('#billFileInput').fileinput('disable');
        prepareOtherFiles();
      }
    } else {
      showFormAlert(billForm, 'Не заполнены необходимые поля');
      return false;
    }
  }

  function prepareOtherFiles() {
      billForm = $('#sendBtn').closest('form');

      //Вызываем загрузку дополнительных файлов
      if ($('#documentsInput .file-preview-thumbnails .kv-preview-thumb').not('.file-preview-success').not('.file-preview-initial').length > 0) {
        fileUploadError = false;
        $('#attach').fileinput('upload');
      } else {
        //Загружать нечего/все загружено, отправляем форму
        formData = billForm.serialize();
        freezeForm(billForm);
        $('#attach').fileinput('disable');
        sendForm();
      }    
  }

  function sendForm() {
    $.ajax({
      type: billForm.attr('method'),
      url: billForm.attr('action'),
      data: formData
    }).done(function(data) {
      if ($.isArray(data) && data[0] == 1) {
        var sum = new Intl.NumberFormat('ru', {style: 'decimal', useGrouping: true, minimumFractionDigits: 2}).format($('#billSumInput').val());
        $.redirectPost('/applications', {'msg': 'Счет успешно загружен<br />№' + $('#billNumInput').val() + ' на сумму ' + sum + ' ' + $('#billCurrency').val(), 'bg-color': 'bg-success', 'text-color': 'text-white'});
      } else {
        showFormAlert(billForm, data[1]);
        freezeForm(billForm, false);
        $('#billFileInput').fileinput('enable');
      }
    }).fail(function() {
      showFormAlert(billForm, 'Ошибка отправки данных');
      freezeForm(billForm, false);
    }); 
  }

  //Вывод сообщения об ошибке
  function showFormAlert(form, text) {
    form.find('fieldset').append('<div class="alert alert-danger alert-dismissible fade show mb-4 form-message" role="alert">\
    <span>' + text + '</span>\
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>\
  </div>');
  }

  //Подсказка о количестве выбранных позиций
  $('body').on('change', 'input[name="material[]"]', function() {
    //Количество выбраных позиций
    var cnt = $('input[name="material[]"]:checked').not('.freeze-ignore').length;

    $(this).attr('data-bs-container', 'body');
    $(this).attr('data-bs-toggle', 'popover');
    $(this).attr('data-bs-placement', 'left');
    $(this).attr('data-bs-content',  'Количество выбранных позиций: ' + cnt);

    //Инициализируем popover
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
      return new bootstrap.Popover(popoverTriggerEl);
    });

    $('.popover').popover('hide');
    $(this).popover('show');
  });

  //Установка значения ИНН при выборе поставщика
  $('#setBtn').click(function() {
    $('#innInput').val($('#innSelect').val()).trigger('change');
    validateInn($('#innInput'));
    checkButtonState();
    var innModal = new bootstrap.Modal(document.getElementById('innModal'), {keyboard: false});
    innModal.hide();
  });

  //Плагин загрузки документов
  $('#attach').fileinput({
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
  })
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

        formData = billForm.serialize();
        freezeForm(billForm);
        $('#billFileInput').fileinput('disable');
  
        sendForm();
      }
  }).on('fileuploaderror', function(event, data, msg) {
      fileUploadError = true;
  });
});