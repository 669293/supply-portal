//Функция заморозки формы на время отправки
function freezeForm(form, disable = true) {
  form.find('input').not('.freeze-ignore').prop('disabled', disable);
  form.find('textarea').not('.freeze-ignore').prop('disabled', disable);
  form.find('select').not('.freeze-ignore').prop('disabled', disable);
  form.find('button').not('.freeze-ignore').prop('disabled', disable);

  if (disable) {
    form.find('i.delete-row').addClass('text-muted').prop('disabled', disable);
  } else {
    form.find('i.delete-row').removeClass('text-muted').prop('disabled', disable);
  }

  if (disable) {
    form.find('.spinner-border:last').css('display', 'none').removeClass('d-none').fadeIn('fast');
    form.addClass('freezed');
  } else {
    form.find('.spinner-border:last').fadeOut('fast', function() {form.find('.spinner-border:last').addClass('d-none');});
    form.removeClass('freezed');
  }
}

//Добавление служебного уведомления
function addToast(text, bgColor = '', textColor = '') {
  var styles = '';
  var id = Date.now();
  if (bgColor != '') {styles += ' ' + bgColor;}
  if (textColor != '') {styles += ' ' + textColor;}

  $('#toastPlacement').append('\
  <div class="toast' + styles + '" id="' + id + '">\
    <div class="d-flex">\
      <div class="toast-body">' + text + '</div>\
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Закрыть"></button>\
    </div>\
  </div>\
  ');

  return true;
}

//Отображение системных уведомлений
function showToasts() {
  var toastElList = [].slice.call($('.toast'));
  var toastList = toastElList.map(function(toastEl) {
    return new bootstrap.Toast(toastEl);
  });

  toastList.forEach(function(item, i, arr) {
    item.show();
    var myToastEl = document.getElementById(item._element.id)
    myToastEl.addEventListener('hidden.bs.toast', function () {this.remove();});
  });

  return true;
}

//Проверка стойкости пароля
function passwordCheck(password) {
  var strength = 0;
  var arr = [/.{8,}/, /\D+/g, /[0-9]+/];
  jQuery.map(arr, function(regexp) { if (password.match(regexp)) {strength++;} });
  if (strength < 3) 
    return false;
  else
    return true;
}

//Функция редиректа с отправкой параметров методом POST
$.extend({
  redirectPost: function(location, args) {
    var form = '';
    $.each(args, function(key, value) {
      form += '<input type="hidden" name="' + key + '" value="' + value + '">';
    });
    $('<form action="' + location + '" method="POST">' + form + '</form>').appendTo('body').submit();
  }
});

//Выделение нескольких checkbox с нажатой клавишей SHIFT
var $chkboxes = $('.shift-control');
var lastChecked = null;

$chkboxes.click(function(e) {
  if (!lastChecked) {
    lastChecked = this;
    return;
  }

  if (e.shiftKey) {
    var start = $chkboxes.index(this);
    var end = $chkboxes.index(lastChecked);

    $chkboxes.slice(Math.min(start,end), Math.max(start,end)+ 1).prop('checked', lastChecked.checked);
  }

  lastChecked = this;
});

//Диалог подтверждения
var modalConfirm = function(callback, text, title, btnText = 'Да') {
  $('body').append('\
  <div class="modal fade" id="modalConfirm" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">\
    <div class="modal-dialog">\
      <div class="modal-content">\
        <div class="modal-header">\
          <h5 class="modal-title" id="exampleModalLabel">' + title + '</h5>\
          <button type="button" class="btn-close btn-modal-confirm-close" aria-label="Закрыть"></button>\
        </div>\
        <div class="modal-body">' + text + '</div>\
        <div class="modal-footer">\
          <button type="button" class="btn btn-primary btn-modal-confirm-ok">' + btnText + '</button>\
          <button type="button" class="btn btn-secondary btn-modal-confirm-close">Нет</button>\
        </div>\
      </div>\
    </div>\
  </div>\
  ');

  $('#modalConfirm').modal('show');

  $('.btn-modal-confirm-close').click(function() {
    callback(false);
    $('#modalConfirm').modal('hide');
  });

  $('.btn-modal-confirm-ok').click(function() {
    callback(true);
    $('#modalConfirm').modal('hide');
  });

  $('#modalConfirm').on('hidden.bs.modal', function (e) {
    $('#modalConfirm').remove();
  });
};

//Фильтрация ввода только цифр
$('body').on('keypress', 'input.numbersOnly', function(event) {
  var key, keyChar;
  if(!event) var event = window.event;

  if (event.keyCode) key = event.keyCode;
  else if (event.which) key = event.which;

  if (key==null || key==0 || key==8 || key==13 || key==37 || key==39 || key==46 || key==9 || key==45) return true;
  keyChar=String.fromCharCode(key);

  if (!/\d/.test(keyChar)) return false;
});

//Переключение темной темы
document.querySelector("#darkmode-button").onclick = function(e){
  darkmode.toggleDarkMode();
}