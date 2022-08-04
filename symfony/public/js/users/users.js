$(document).ready(function() {
  //Вывод сообщения об ошибке
  function showFormAlert(form, text) {
    form.find('fieldset').append('<div class="alert alert-danger alert-dismissible fade show mb-3 mt-3 form-message" role="alert">\
    <span>' + text + '</span>\
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>\
  </div>');
  }

  //Проверка имени пользователя
  function checkUserName(subject, quiet = false) {
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

  $('#userName').keyup(function() {
    checkUserName($(this));
    checkButtonState();
  });

  //Проверка логина
  function checkLogin(subject, quiet = false) {
    var valid = true;

    if (subject.val() == '') {
      valid = false;
      if (!quiet) {
        subject.addClass('is-invalid');
        subject.parent().find('.invalid-feedback').text('Обязательное поле').removeClass('d-none');
      }
    } else {
      if (!subject.val().match(/.{5,}/)) {
        valid = false;
        if (!quiet) {
          subject.addClass('is-invalid');
          subject.parent().find('.invalid-feedback').text('Введен некорректный логин. Логин должен быть длиной от 5 до 31 символов.').removeClass('d-none');
        }
      } else {
        if (!subject.val().match(/[\w\.\-_]+$/ig)) {
          valid = false;
          if (!quiet) {
            subject.addClass('is-invalid');
            subject.parent().find('.invalid-feedback').text('Введен некорректный логин. Допустимо использовать только латинские буквы, цифры, знак подчеркивания, точку и минус.').removeClass('d-none');
          }
        } else {
          if (!quiet) {
            subject.removeClass('is-invalid').addClass('is-valid');
          }
          subject.parent().find('.invalid-feedback').text('').addClass('d-none');
        }
      }
    }

    return valid;
  }

  $('#userLogin').keyup(function() {
    checkLogin($(this));
    checkButtonState();
  });

  //Проверка пароля
  function checkUserPassword(subject, quiet = false) {
    var valid = true;

    if (subject.val() == '') {
      valid = false;
      if (!quiet) {
        subject.addClass('is-invalid');
        subject.parent().find('.invalid-feedback').text('Обязательное поле').removeClass('d-none');
      }
    } else {
      if (!passwordCheck(subject.val())) {
        valid = false;
        if (!quiet) {
          subject.addClass('is-invalid');
          subject.parent().find('.invalid-feedback').text('Пароль должен быть не короче 8 симв. и содержать буквы и цифры').removeClass('d-none');
        }
      } else {
        if (!quiet) {
          subject.removeClass('is-invalid').addClass('is-valid');
        }
        subject.parent().find('.invalid-feedback').text('').addClass('d-none');
      }
    }

    return valid;
  }

  $('#userPassword').keyup(function() {
    checkUserPassword($(this));
    checkButtonState();

    if ($('#userConfirm').val() != '') {
      $('#userConfirm').trigger('keyup');
    }
  });

  //Проверка подтверждения
  function checkUserConfirm(subject, quiet = false) {
    var valid = true;

    if (subject.val() != $('#userPassword').val()) {
      valid = false;
      if (!quiet) {
        subject.addClass('is-invalid');
        subject.parent().find('.invalid-feedback').text('Пароли не совпадают').removeClass('d-none');
      }
    } else {
      if (!quiet) {
        subject.removeClass('is-invalid').addClass('is-valid');
      }
      subject.parent().find('.invalid-feedback').text('').addClass('d-none');
    }

    return valid;
  }

  $('#userConfirm').keyup(function() {
    checkUserConfirm($(this));
    checkButtonState();
  });

  //Проверка электронной почты
  function checkEmail(subject, quiet = false) {
    var valid = true;

    if (subject.val() == '') {
      valid = false;
      if (!quiet) {
        subject.addClass('is-invalid');
        subject.parent().find('.invalid-feedback').text('Обязательное поле').removeClass('d-none');
      }
    } else {
      if (!subject.val().match(/^\b[A-Z0-9._%-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b$/i)) {
        valid = false;
        if (!quiet) {
          subject.addClass('is-invalid');
          subject.parent().find('.invalid-feedback').text('Введен некорректный адрес электронной почты.').removeClass('d-none');
        }
      } else {
        if (!quiet) {
          subject.removeClass('is-invalid').addClass('is-valid');
        }
        subject.parent().find('.invalid-feedback').text('').addClass('d-none');
      }
    }

    return valid;
  }

  $('#userEmail').keyup(function() {
    checkEmail($(this));
    checkButtonState();
  });

  //Проверка формы
  function checkForm(form, quiet = false, checkRolesFlag = false) {
    var valid = true;

    valid &= checkUserName($('#userName'), quiet);
    valid &= checkLogin($('#userLogin'), quiet);
    valid &= checkEmail($('#userEmail'), quiet);
    
    if (form.attr('id') == 'user-add-form') {
      valid &= checkUserConfirm($('#userConfirm'), quiet);
      valid &= checkUserPassword($('#userPassword'), quiet);
    }

    return valid;
  }

  //Проверка состояния кнопки отправки формы
  function checkButtonState() {
    if (checkForm($('#sendBtn').parent(), true)) {
      $('#sendBtn').prop('disabled', false); 
    } else {
      $('#sendBtn').prop('disabled', true);
    }
  }

  $('#sendBtn').click(function() {
    var form = $(this).parent();

    if (!checkForm(form, true)) {
      showFormAlert(form, 'Не заполнены необходимые поля');
      return false;
    }

    if (form.find('.form-message').length == 0) {
      sendForm(form);
    } else {
      form.find('.form-message').hide('fast', function() {
        $(this).remove();
        sendForm(form);
      });
    }
  });

  function sendForm(form) {
    var data = form.serialize();
    freezeForm(form);

    $.ajax({
      type: form.attr('method'),
      url: form.attr('action'),
      data: data
    }).done(function(data) {
      if (data[0] == 0) {
        if (data.length == 3) {
          $('#' + data[2]).removeClass('is-valid').addClass('is-invalid');
          $('#' + data[2]).parent().find('.invalid-feedback').text(data[1]).removeClass('d-none');
        } else {
          showFormAlert(form, data[1]);
        }
      } else {
        //Все хорошо
        if ($('#sendBtn').parent().attr('id') == 'user-add-form') {
          $.redirectPost('/users', {'msg': 'Пользователь ' + data[1] + ' успешно добавлен', 'bg-color': 'bg-success', 'text-color': 'text-white'});
        } else {
          $.redirectPost('/users', {'msg': 'Изменения успешно сохранены', 'bg-color': 'bg-success', 'text-color': 'text-white'});
        }
      }

      freezeForm(form, false);
    }).fail(function() {
      showFormAlert(form, 'Ошибка отправки данных');

      freezeForm(form, false);
    }); 
  }
});