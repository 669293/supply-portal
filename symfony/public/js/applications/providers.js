$(document).ready(function() {
  //Получаем instance модального окна
  const providerModal = new bootstrap.Modal(document.getElementById('providerModal'));
  var row;

  $('.editProvider').click(function(event) {
    event.preventDefault();

    row = $(this).closest('tr');

    //Ставим значения для редактирования
    $('#providerForm input[name="inn"]').val(row.find('td:nth-child(1)').text().trim()); //ИНН
    $('#providerForm input[name="title"]').val( (row.find('td:nth-child(2)').text().trim() == 'Не заполнено' ? '' : row.find('td:nth-child(2)').text().trim()) ); //Название организации
    $('#providerForm input[name="address"]').val( (row.find('td:nth-child(3)').text().trim() == 'Не заполнено' ? '' : row.find('td:nth-child(3)').text().trim()) ); //Адрес
    $('#providerForm input[name="phone"]').val( (row.find('td:nth-child(4)').text().trim() == 'Не заполнено' ? '' : row.find('td:nth-child(4)').text().trim()) ); //Телефон
    $('#providerForm textarea[name="comment"]').val( (row.find('td:nth-child(5)').text().trim() == 'Не заполнено' ? '' : row.find('td:nth-child(5)').text().trim()) ); //Комментарий

    providerModal.show();
  });

  //Редактирование информации о поставщике
  $('#saveProviderBtn').click(function() {
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

        //Вносим данные в таблицу
        row.find('td:nth-child(2)').html( ($('#providerForm input[name="title"]').val() == '' ? '<span class="text-muted">Не заполнено</span>' : $('#providerForm input[name="title"]').val()) ); //Название организации
        row.find('td:nth-child(3)').html( ($('#providerForm input[name="address"]').val() == '' ? '<span class="text-muted">Не заполнено</span>' : $('#providerForm input[name="address"]').val()) ); //Адрес
        row.find('td:nth-child(4)').html( ($('#providerForm input[name="phone"]').val() == '' ? '<span class="text-muted">Не заполнено</span>' : $('#providerForm input[name="phone"]').val()) ); //Телефон
        row.find('td:nth-child(5)').html( ($('#providerForm textarea[name="comment"]').val() == '' ? '<span class="text-muted">Не заполнено</span>' : $('#providerForm textarea[name="comment"]').val()) ); //Комментарий

        providerModal.hide();
      } else {
        showFormAlert(form, data[1]);
        freezeForm(form, false);
      }
    });
  });
});