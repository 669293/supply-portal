$(document).ready(function() {
  //Показываем модальное окно смены пароля
  $('#changePasswordLink').click(function(event) {
    event.preventDefault();
    $('#currentPassword').val('');
    $('#newPassword').val('');
    $('#confirmPassword').val('');
    $('#newPassword').removeClass('is-invalid').removeClass('is-valid').parent().find('.invalid-feedback').addClass('d-none');
    $('#confirmPassword').removeClass('is-invalid').removeClass('is-valid').parent().find('.invalid-feedback').addClass('d-none');
    $('#changePassword').modal('show');
  });

  //Проверка нового пароля
  $('#newPassword').keyup(function() {
    if (!passwordCheck($('#newPassword').val())) {
      $('#newPassword').addClass('is-invalid');
      $('#newPassword').parent().find('.invalid-feedback').text('Пароль должен быть не короче 8 симв. и содержать буквы и цифры').removeClass('d-none');
      $('#changePassword .submit-btn').prop('disabled', true);
    } else {
      $('#newPassword').removeClass('is-invalid').addClass('is-valid');
      $('#newPassword').parent().find('.invalid-feedback').addClass('d-none');
      if ($('#confirmPassword').val() == $('#newPassword').val()) {
        $('#changePassword .submit-btn').prop('disabled', false);
      }
    }

    if ($('#confirmPassword').val() != '') {
      $('#confirmPassword').trigger('keyup');
    }
  });

  //Проверка совпадения паролей
  $('#confirmPassword').keyup(function() {
    if ($('#confirmPassword').val() != $('#newPassword').val()) {
      $('#confirmPassword').addClass('is-invalid');
      $('#confirmPassword').parent().find('.invalid-feedback').text('Пароли не совпадают').removeClass('d-none');
      $('#changePassword .submit-btn').prop('disabled', true);
    } else {
      $('#confirmPassword').removeClass('is-invalid').addClass('is-valid');
      $('#confirmPassword').parent().find('.invalid-feedback').addClass('d-none');
      $('#changePassword .submit-btn').prop('disabled', false);
    }
  });

  //Функция отображения ошибки
  function showFormAlert(form, text) {
    form.find('.modal-body').append('<div class="alert alert-danger alert-dismissible fade show mb-0 mt-3 form-message" role="alert">\
    <span>' + text + '</span>\
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>\
  </div>');
  }

  //Смена пароля
  $('#changePassword .submit-btn').click(function() {
    var form = $('#change-password-form');

    //Проверяем корректность формы
    if ($('#currentPassword').val() == '') {$('#currentPassword').addClass('is-invalid');}
    if ($('#newPassword').val() == '') {$('#newPassword').addClass('is-invalid');}
    if ($('#confirmPassword').val() == '') {$('#confirmPassword').addClass('is-invalid');}

    if ($('#changePassword').find('.is-invalid').length != 0 || $('#currentPassword').val() == 0) {
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
        showFormAlert(form, data[1]);
      } else {
        $('#changePassword').modal('hide');
        addToast('Ваш пароль успешно изменен', 'bg-success', 'text-white');
        showToasts();
      }
      
      freezeForm(form, false);
    }).fail(function() {
      showFormAlert(form, 'Ошибка отправки данных');
      freezeForm(form, false);
    });
  }
});
