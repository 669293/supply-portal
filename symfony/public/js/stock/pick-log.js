$(document).ready(function() {
  var lastChecked = null;
  
  $('#pickForm').modal('show');

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

  $('input[name="material[]"]').change(function() {
      checkButtonState();
  });

  //Проверка состояния кнопки "Далее"
  function checkButtonState() {
      if ($('input[name="material[]"]:checked').length > 0) {
          $('#nextBtn').prop('disabled', false); 
          $('#sendBtn').prop('disabled', false); 
      } else {
          $('#nextBtn').prop('disabled', true);
          $('#sendBtn').prop('disabled', true); 
      }
  }

  //Отправка формы
  $('#sendBtn').click(function() {
      //Загружаем файлы
      photosUploaded = false;
      uploadFiles();
  });

  //Функция, отвечающая за последовательную загрузку фотографий и отправку формы
  var photosUploaded = false;
  function uploadFiles() {
      if ($('#photosInput .file-preview-thumbnails .kv-preview-thumb').not('.file-preview-success').not('.file-preview-initial').length == 0) {
          sendForm();
          return true;
      }
      
      if (photosUploaded) {
          sendForm();
          return true;
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

  //Вывод сообщения об ошибке
  function showFormAlert(form, text) {
    form.find('fieldset').append('<div class="alert alert-danger alert-dismissible fade show mt-4 mb-0 form-message" role="alert">\
      <span>' + text + '</span>\
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>\
    </div>');
  }

  //Отправка формы
  function sendForm() {
      frm = $('#recieveForm');

      //Удаляем имена чтобы отправлялись только данные, которые соответствуют выбору
      $('.amount-input').attr('name', '');
      $('.log-input').attr('name', '');
    
      $('.material-select:checked').each(function() {
        $(this).closest('tr').find('.amount-input').attr('name', 'amount[]');
        $(this).closest('tr').find('.log-input').attr('name', 'logistic[]');
      });

      formData = frm.serialize();
      freezeForm(frm);
      $('#photos').fileinput('disable');

      $.ajax({
          type: frm.attr('method'),
          url: frm.attr('action'),
          data: formData
      }).done(function(data) {
          if ($.isArray(data) && data[0] == 1) {
            location.href = 'http://vitim.1gb.ru/model/php/setPMLogistic.php?type=tn&secret=35b7fff08b557bd2909fc91316fc6216&doc=' + docID + '&logistic=' + JSON.stringify(data[1]);
          } else {
              showFormAlert(frm, data[1]);
              freezeForm(frm, false);
          }
      }).fail(function() {
          showFormAlert(frm, 'Ошибка отправки данных');
          freezeForm(frm, false);
          $('#photos').fileinput('enable');
      }); 
  }

  //Автозаполнение поля "Способ отправки"
  $('input[name="way"]').autocomplete({
    serviceUrl: '/logistics/way'
  });
});