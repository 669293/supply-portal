$(document).ready(function() {
  var formData;
  var appForm;
  var fileUploadError = false;

  //Плагин загрузки файлов
  $('#attach').fileinput(params)
  .on('fileuploaded', function(event, data, index, fileId) {
    //Файл успешно загружен
    document.getElementById(index).setAttribute('data-id', data.response.id);
  }).on('filesuccessremove', function(event, id) {
    //Удаление файла если он загружен
    var db_id = document.getElementById(id).getAttribute('data-id');
    //Отправляем запрос на удаление файла
    $.post('/applications/delete-file', {key: db_id});
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

      formData = appForm.serialize();
      freezeForm(appForm);
      $('#attach').fileinput('disable');
      sendForm();
    }
  }).on('fileuploaderror', function(event, data, msg) {
    fileUploadError = true;
  });

  //Автозаполнение поля "Вид техники"
  function toeAutocomplete() {
    $('.toe-autocomplete').autocomplete({
        serviceUrl: '/autocomplete/toe'
    });
  }
  toeAutocomplete();

  //Автозаполнение поля "Наименование"
  function materialAutocomplete() {
    $('.material-autocomplete').autocomplete({
        serviceUrl: '/autocomplete/material'
    });
  }
  materialAutocomplete();

  //Выделение всех позиций как срочные
  $('#urgent0').change(function() {
    $('.urgent:not(:disabled)').prop('checked', $(this).prop('checked'));
  });

  //Изменение статуса сторки
  function setDatabaseRowStatus(row, deleted = true) {
    if (deleted) {
      row.find('input').addClass('deleted').addClass('freeze-ignore').prop('disabled', true);
      row.find('select').addClass('deleted').addClass('freeze-ignore').prop('disabled', true);
      row.find('td').slice(0,6).addClass('text-decoration-line-through').addClass('freeze-ignore');
      row.find('td:last i:first').removeClass('delete-database').removeClass('delete-row').removeClass('bi-trash').removeClass('text-danger').addClass('bi-arrow-repeat').addClass('text-success').addClass('repeat-row');
    } else {
      row.find('input').removeClass('deleted').removeClass('freeze-ignore').prop('disabled', false);
      row.find('select').removeClass('deleted').removeClass('freeze-ignore').prop('disabled', false);
      row.find('td').slice(0,6).removeClass('text-decoration-line-through').removeClass('freeze-ignore');
      row.find('td:last i:first').addClass('delete-database').addClass('delete-row').addClass('bi-trash').addClass('text-danger').removeClass('bi-arrow-repeat').removeClass('text-success').removeClass('repeat-row');
    }
  }

  //Функция изменения статуса материала в заявке
  function setMaterialStatus(btn, status) {
    var row = btn.closest('tr');
    var id = btn.data('id');

    btn.fadeOut('fast', function() {
      row.find('td:last .spinner-border').css('display', 'none').removeClass('d-none').fadeIn('fast', function() {
        $.post('/applications/material-status', {id: id, status: status})
        .done(function(data) {
          row.find('td:last .spinner-border').fadeOut('fast', function() {
            row.find('td:last .spinner-border').css('display', '').addClass('d-none');
            btn.fadeIn('fast', function() {
              if (data) {
                setDatabaseRowStatus(row, status);
              } else {
                addToast('Произошла ошибка.<br />Попробуйте обновить страницу.', 'bg-danger', 'text-white');
                showToasts();
              }    
            });
          });
        }).fail(function() {
          addToast('Произошла ошибка.<br />Попробуйте обновить страницу.', 'bg-danger', 'text-white');
          showToasts();

          row.find('td:last .spinner-border').fadeOut('fast', function() {
            row.find('td:last .spinner-border').css('display', '').addClass('d-none');
            btn.fadeIn('fast');
          });
        });
      });
    });
  }

  //Восстановление позиции в заявке
  $('body').on('click', '.repeat-row', function() {
    if (!$(this).prop('disabled')) {
      var btn = $(this);

      //Делаем запрос на восстановление в базе
      setMaterialStatus(btn, false);
    }
  });

  //Удаление позиций из заявки
  $('body').on('click', '.delete-row', function() {
    if (!$(this).prop('disabled')) {
      if ($(this).hasClass('delete-database')) {
        var row = $(this).closest('tr');
        var btn = $(this);
        var id = $(this).data('id');
        var positionTitle = row.find('input[name="titleContentApp[]"]').val();

        //Вызываем подтверждение
        modalConfirm(function(confirm) {
          if (confirm) {
            //Делаем запрос на удаление из базы
            setMaterialStatus(btn, true);
          }
        }, 
        'Вы уверены что хотите удалить &laquo;' + positionTitle + '&raquo; из заявки?',
        'Удаление позиции из заявки'
        );
      } else {
        var row = $(this).closest('tr');

        //Проверяем, вдруг осталась одна строка
        if ($('#materialsTable tbody tr:visible').length == 1) {
          //Просто очищаем ее
          row.find('input[type="text"]').val('');
          row.find('input[type="number"]').val('');
          row.find('input[type="checkbox"]').prop('checked', false);
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

  //Добавление позиции в заявку
  $('#addRowBtn').click(function() {
    var row = $('#materialsTable tbody tr:visible:last').clone();
    row.find('.text-decoration-line-through').removeClass('text-decoration-line-through');
    row.find('input[type="text"]').val('').removeClass('freeze-ignore');
    row.find('input[type="number"]').val('').removeClass('freeze-ignore');
    row.find('input[type="checkbox"]').prop('checked', false).removeClass('freeze-ignore');
    row.find('input[name="idContentApp[]"]').remove();
    row.find('select').removeClass('freeze-ignore').val(row.find('select option').filter(function() {return $(this).text() === 'шт';}).first().attr('value'));
    row.find('.delete-row').removeClass('delete-database');
    row.attr('style', 'none');
    row.css('display', 'none');
    row.appendTo('#materialsTable tbody').show('fast');
    enumerate();
    toeAutocomplete();
    materialAutocomplete();
  });

  //Проверка названия заявки
  function checkTitle(subject, quiet = false) {
    var valid = true;

    if (subject.val() == '') {
      valid = false;
      if (!quiet) {
        subject.addClass('is-invalid');
        subject.parent().find('.invalid-feedback').text('Обязательное поле').removeClass('d-none');
      }
    } else {
      if (!quiet) {
        subject.removeClass('is-invalid').addClass('is-valid');
      }
      subject.parent().find('.invalid-feedback').text('').addClass('d-none');
    }

    return valid;
  }

  $('body').on('keyup', '#titleApp', function() {
    checkTitle($(this));
    checkButtonState();
  });
  
  //Проверка единицы измерения
  $('body').on('change', 'select[name="unitContentApp[]"]', function() {
    $(this).removeClass('is-invalid');
  });

  //Проверка комментария
  function checkComment(subject, quiet = false) {
    if (subject.val() == '') {
      if (!quiet) {
        subject.removeClass('is-valid');
      }
    } else {
      if (!quiet) {
        subject.removeClass('is-invalid').addClass('is-valid');
      }
    }

    return true;
  }

  $('body').on('keyup', '#commentApp', function() {
    checkComment($(this));
    checkButtonState();
  });

  //Проверка дополнительного номера заявки
  function checkNumber(subject, quiet = false) {
    if (subject.val() == '') {
      if (!quiet) {
        subject.removeClass('is-valid');
      }
    } else {
      if (!quiet) {
        subject.removeClass('is-invalid').addClass('is-valid');
      }
    }

    return true;
  }

  $('body').on('keyup', '#additionalNumApp', function() {
    checkNumber($(this));
    checkButtonState();
  });

  //Проверка содержимого
  function checkContent(quiet = false, maxIndex = -1) {
    var valid = true;
  
    if (maxIndex == -1) {
      //Определяем максимальный индекс строки
      maxIndex = $('#materialsTable tbody tr:visible').length;
    }

    var filledRows = 0;
    $('#materialsTable tbody tr:visible').slice(0, maxIndex).each(function(index, element) {
      var row = $(this);

      //Проверяем все до текущего индекса
      if (row.find('input[name="titleContentApp[]"]').val() != '') {
        //Название указано, проверяем указано ли количество
        filledRows++;
        var amount = row.find('input[name="amountContentApp[]"]');
        if (amount.val() == '') {
          valid = false;

          if (!quiet) {amount.addClass('is-invalid');}
        } else {
          if (!quiet) {
            amount.removeClass('is-invalid');
          }
        }
      }
    });

    if (filledRows == 0 && maxIndex == $('#materialsTable tbody tr:visible').length) {
      valid = false;

      if (!quiet) {
        var firstInput = $('#materialsTable tbody tr:visible:first input[name="titleContentApp[]"]');
        firstInput.addClass('is-invalid');
        if (firstInput.closest('td').find('.invalid-feedback').length == 0) {
          firstInput.closest('td').append('<div class="invalid-feedback">Заявка не может быть пустой</div>');
        }
      }
    }

    return valid;
  }

  $('body').on('keyup', 'input[name="titleContentApp[]"]', function() {
    var index = $(this).closest('tr').find('td:first').text();

    if ($(this).val() != '') {
      $(this).removeClass('is-invalid');
      $(this).closest('td').find('.invalid-feedback').remove();
    }
    checkContent(false, index - 1);
  });

  $('body').on('keyup', 'input[name="amountContentApp[]"]', function() {
    if ($(this).val() != '') {$(this).removeClass('is-invalid');}
  });

  $('body').on('change', 'input[name="amountContentApp[]"]', function() {
    if ($(this).val() != '') {$(this).removeClass('is-invalid');}
  });

  //Проверка формы
  function checkForm(form, quiet = false, checkContentFlag = true) {
    var valid = true;

    valid &= checkTitle($('#titleApp'), quiet);
    valid &= checkComment($('#commentApp'), quiet);
    valid &= checkNumber($('#additionalNumApp'), quiet);
    if (checkContentFlag) {valid &= checkContent(quiet);}

    return valid;
  }

  //Проверка состояния кнопки отправки формы
  function checkButtonState() {
    if (checkForm($('#sendBtn').closest('form'), true, false)) {
      $('#sendBtn').prop('disabled', false); 
    } else {
      $('#sendBtn').prop('disabled', true);
    }
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
    appForm = $(this).closest('form');

    if (appForm.find('.form-message').length == 0) {
      prepareForm();
    } else {
      appForm.find('.form-message').hide('fast', function() {
        $(this).remove();
        prepareForm();
      });
    }
  });

  function prepareForm() {
    if (checkForm(appForm, false)) {
      //Вызываем загрузку всех файлов
      if ($('.file-preview-thumbnails .kv-preview-thumb').not('.file-preview-success').not('.file-preview-initial').length > 0) {
        fileUploadError = false;
        $('#attach').fileinput('upload');
      } else {
        //Загружать нечего/все загружено, отправляем форму
        formData = appForm.serialize();
        freezeForm(appForm);
        $('#attach').fileinput('disable');
        sendForm();
      }
    } else {
      showFormAlert(appForm, 'Не заполнены необходимые поля');
      return false;
    }
  }

  function sendForm() {
    $.ajax({
      type: appForm.attr('method'),
      url: appForm.attr('action'),
      data: formData
    }).done(function(data) {
      if ($.isArray(data) && data[0] == 1) {
        //Все хорошо
        if (appForm.attr('id') == 'createAppForm') {
          $.redirectPost('/applications', {'msg': 'Заявка №' + data[1] + ' успешно добавлена', 'bg-color': 'bg-success', 'text-color': 'text-white'});
        } else {
          location.href = '/applications/view?number=' + data[1];
          // $.redirectPost('/applications', {'msg': 'Заявка №' + data[1] + ' успешно сохранена', 'bg-color': 'bg-success', 'text-color': 'text-white'});
        }
      } else {
        showFormAlert(appForm, data[1]);
        freezeForm(appForm, false);
        $('#attach').fileinput('enable');
      }
    });
  }

  //Активация кнопки загрузки файла из шаблона
  $('#templateUpload').change(function() {
    if ($(this).val() == '') {
      $('#uploadBtn').prop('disabled', true);
    } else {
      $('#uploadBtn').prop('disabled', false);
    }
  });

  //Загрузка заявки из шаблона
  $('#uploadBtn').click(function() {
    $('#templateForm').submit();
  });    
});